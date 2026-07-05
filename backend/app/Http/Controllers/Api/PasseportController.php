<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Passeport\ImportPasseportsRequest;
use App\Http\Requests\Passeport\ReceptionnerMAERequest;
use App\Http\Requests\Passeport\RemettreAuCitoyenRequest;
use App\Http\Requests\Passeport\StorePasseportRequest;
use App\Http\Requests\Passeport\UpdatePasseportRequest;
use App\Imports\PasseportsImport;
use App\Models\AuditLog;
use App\Models\Passeport;
use App\Models\TrackingEvent;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PasseportController extends Controller
{
    public function __construct(private EmailNotificationService $emailService) {}
    // ── Liste & recherche ──────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('viewAny', Passeport::class);

        $user = $request->user();

        $query = Passeport::with([
                'paysDestination:id,nom,code_iso',
                'ambassadeDestination:id,nom,code,ville',
                'lot:id,reference,statut,ambassade_id',
            ])
            // Restriction ambassade scoped
            ->when($user->isScopedToAmbassade(), fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->where('ambassade_destination_id', $user->ambassade_id)
                       ->orWhereHas('lot', fn ($q3) => $q3->where('ambassade_id', $user->ambassade_id))
                )
            )
            // Filtres
            ->when($request->statut, fn ($q) => $q->where('statut', $request->statut))
            ->when($request->lot_id, fn ($q) => $q->where('lot_id', $request->lot_id))
            ->when($request->pays_id, fn ($q) => $q->where('pays_destination_id', $request->pays_id))
            ->when($request->ambassade_id, fn ($q) =>
                $q->where('ambassade_destination_id', $request->ambassade_id)
            )
            ->when($request->date_from, fn ($q) => $q->whereDate('date_impression', '>=', $request->date_from))
            ->when($request->date_to,   fn ($q) => $q->whereDate('date_impression', '<=', $request->date_to))
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->orderByDesc('created_at');

        // Mode export (sans pagination)
        if ($request->boolean('all')) {
            return response()->json($query->get());
        }

        return response()->json($query->paginate((int) ($request->per_page ?? 50)));
    }

    /** Passeports disponibles pour être ajoutés à un lot */
    public function stock(Request $request)
    {
        $this->authorize('viewAny', Passeport::class);

        $passeports = Passeport::enStock()
            ->with(['paysDestination:id,nom,code_iso', 'ambassadeDestination:id,nom,code,ville'])
            ->when($request->ambassade_id, fn ($q) => $q->where('ambassade_destination_id', $request->ambassade_id))
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->orderBy('nom_titulaire')
            ->get([
                'id', 'numero', 'reference_demande',
                'nom_titulaire', 'prenom_titulaire',
                'pays_destination_id', 'ambassade_destination_id',
                'date_impression', 'received_at',
            ]);

        return response()->json([
            'count'      => $passeports->count(),
            'passeports' => $passeports,
        ]);
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function show(Passeport $passeport)
    {
        $this->authorize('view', $passeport);

        $passeport->load([
            'paysDestination',
            'ambassadeDestination',
            'lot.ambassade',
            'lot.transporteur',
            'anomalies.signaledBy',
            'emailLogs',
        ]);

        return response()->json([
            'passeport'     => $passeport,
            'statut_label'  => $passeport->statut_label,
            'statut_color'  => $passeport->statut_color,
            'transitions'   => Passeport::TRANSITIONS[$passeport->statut] ?? [],
            'est_modifiable'=> $passeport->estModifiable(),
        ]);
    }

    public function store(StorePasseportRequest $request)
    {
        $this->authorize('create', Passeport::class);

        $data            = $request->validated();
        $statutInitial   = $data['statut'] ?? Passeport::STATUT_IMPRIME;
        $data['statut']  = $statutInitial;

        if ($statutInitial === Passeport::STATUT_RECU_MAE || $statutInitial === Passeport::STATUT_EN_STOCK) {
            $data['date_reception_mae'] ??= today()->toDateString();
            $data['received_at']         = now();
        }

        $passeport = Passeport::create($data);

        TrackingEvent::record($passeport, 'created', "Passeport enregistré avec statut : {$passeport->statut_label}");
        AuditLog::record('passeport.create', 'Passeport', $passeport->id, null, $passeport->toArray());

        return response()->json([
            'passeport'    => $passeport->load(['paysDestination', 'ambassadeDestination']),
            'statut_label' => $passeport->statut_label,
        ], 201);
    }

    public function update(UpdatePasseportRequest $request, Passeport $passeport)
    {
        $this->authorize('update', $passeport);

        $old = $passeport->toArray();
        $passeport->update($request->validated());
        AuditLog::record('passeport.update', 'Passeport', $passeport->id, $old, $passeport->fresh()->toArray());

        return response()->json($passeport->load(['paysDestination', 'ambassadeDestination']));
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function import(ImportPasseportsRequest $request)
    {
        $this->authorize('import', Passeport::class);

        $import = new PasseportsImport();
        Excel::import($import, $request->file('file'));

        AuditLog::record('passeport.import', 'Passeport', null, null, [
            'imported'  => $import->getRowCount(),
            'skipped'   => $import->getSkippedCount(),
            'errors'    => $import->failures()->count(),
        ]);

        return response()->json([
            'message'  => "{$import->getRowCount()} passeport(s) importé(s).",
            'imported' => $import->getRowCount(),
            'skipped'  => $import->getSkippedCount(),
            'errors'   => $import->failures(),
        ]);
    }

    // ── Transitions de statut ─────────────────────────────────────────────────

    /**
     * IMPRIMÉ → REÇU_MAE
     * Enregistre la réception physique du passeport au MAE.
     */
    public function receptionnerMAE(ReceptionnerMAERequest $request, Passeport $passeport)
    {
        $this->authorize('create', Passeport::class);

        if (! $passeport->estImprime()) {
            throw ValidationException::withMessages([
                'statut' => "Ce passeport ne peut pas être réceptionné au MAE (statut actuel : {$passeport->statut_label}).",
            ]);
        }

        $dateReception = $request->date_reception_mae ?? today()->toDateString();

        $passeport->update([
            'date_reception_mae' => $dateReception,
            'received_at'        => now(),
        ]);

        $passeport->transitionTo(
            Passeport::STATUT_RECU_MAE,
            $request->notes ?? "Réceptionné au MAE le {$dateReception}",
            ['date_reception_mae' => $dateReception]
        );

        AuditLog::record('passeport.recu_mae', 'Passeport', $passeport->id);

        return response()->json([
            'message'      => 'Passeport réceptionné au MAE.',
            'passeport'    => $passeport->fresh(),
            'statut_label' => $passeport->statut_label,
        ]);
    }

    /**
     * REÇU_MAE → EN_STOCK
     * Mise en stock après vérification et traitement au MAE.
     */
    public function mettreEnStock(Request $request, Passeport $passeport)
    {
        $this->authorize('create', Passeport::class);

        if (! $passeport->estRecuMAE()) {
            throw ValidationException::withMessages([
                'statut' => "Ce passeport doit être au statut REÇU_MAE pour être mis en stock (statut actuel : {$passeport->statut_label}).",
            ]);
        }

        $passeport->transitionTo(
            Passeport::STATUT_EN_STOCK,
            $request->notes ?? 'Mis en stock MAE après vérification.'
        );

        AuditLog::record('passeport.en_stock', 'Passeport', $passeport->id);

        return response()->json([
            'message'      => 'Passeport mis en stock.',
            'passeport'    => $passeport->fresh(),
            'statut_label' => $passeport->statut_label,
        ]);
    }

    /**
     * REÇU_AMBASSADE → DISPONIBLE_RETRAIT
     * L'agent ambassade marque le passeport prêt à être retiré par le citoyen.
     */
    public function disponibleRetrait(Request $request, Passeport $passeport)
    {
        $this->authorize('reception', $passeport->lot ?? $passeport);

        if (! $passeport->estRecuAmbassade()) {
            throw ValidationException::withMessages([
                'statut' => "Ce passeport doit être au statut REÇU_AMBASSADE (statut actuel : {$passeport->statut_label}).",
            ]);
        }

        $passeport->transitionTo(
            Passeport::STATUT_DISPONIBLE_RETRAIT,
            $request->notes ?? 'Disponible pour retrait par le citoyen.'
        );

        // Notifier le citoyen maintenant que le passeport est récupérable
        $this->emailService->sendPasseportDisponible($passeport->fresh());

        AuditLog::record('passeport.disponible_retrait', 'Passeport', $passeport->id);

        return response()->json([
            'message'      => 'Passeport disponible pour retrait.',
            'passeport'    => $passeport->fresh(),
            'statut_label' => $passeport->statut_label,
        ]);
    }

    /**
     * DISPONIBLE_RETRAIT → REMIS_AU_CITOYEN
     * Confirmation de remise physique au titulaire.
     */
    public function remettreAuCitoyen(RemettreAuCitoyenRequest $request, Passeport $passeport)
    {
        if (! $passeport->estDisponibleRetrait()) {
            throw ValidationException::withMessages([
                'statut' => "Ce passeport doit être au statut DISPONIBLE_RETRAIT (statut actuel : {$passeport->statut_label}).",
            ]);
        }

        $dateRemise = $request->date_remise ?? today()->toDateString();

        $passeport->update(['delivered_at' => now()]);

        $passeport->transitionTo(
            Passeport::STATUT_REMIS_CITOYEN,
            $request->notes ?? "Remis au citoyen le {$dateRemise}.",
            ['date_remise' => $dateRemise]
        );

        AuditLog::record('passeport.remis_citoyen', 'Passeport', $passeport->id);

        return response()->json([
            'message'      => 'Passeport remis au citoyen.',
            'passeport'    => $passeport->fresh(),
            'statut_label' => $passeport->statut_label,
        ]);
    }

    // ── Historique ────────────────────────────────────────────────────────────

    public function historique(Passeport $passeport)
    {
        $this->authorize('view', $passeport);

        $events = $passeport->trackingEvents()
            ->with('triggeredBy:id,name,role')
            ->get()
            ->map(fn ($e) => [
                'id'          => $e->id,
                'event'       => $e->event,
                'description' => $e->description,
                'metadata'    => $e->metadata,
                'agent'       => $e->triggeredBy ? [
                    'id'   => $e->triggeredBy->id,
                    'name' => $e->triggeredBy->name,
                    'role' => $e->triggeredBy->role,
                ] : null,
                'date'        => $e->created_at->toIso8601String(),
            ]);

        return response()->json([
            'passeport' => [
                'id'           => $passeport->id,
                'numero'       => $passeport->numero,
                'nom_complet'  => $passeport->nom_complet,
                'statut'       => $passeport->statut,
                'statut_label' => $passeport->statut_label,
                'statut_color' => $passeport->statut_color,
            ],
            'timeline'  => $events,
            'total'     => $events->count(),
        ]);
    }

    // ── Vérification doublon ───────────────────────────────────────────────────

    public function checkDoublon(Request $request)
    {
        $request->validate([
            'numero'            => 'nullable|string',
            'reference_demande' => 'nullable|string',
        ]);

        $result = [
            'numero'            => false,
            'reference_demande' => false,
        ];

        if ($request->numero) {
            $result['numero'] = Passeport::where('numero', $request->numero)->exists();
        }

        if ($request->reference_demande) {
            $result['reference_demande'] = Passeport::where('reference_demande', $request->reference_demande)->exists();
        }

        return response()->json([
            'doublon' => $result['numero'] || $result['reference_demande'],
            'details' => $result,
        ]);
    }
}
