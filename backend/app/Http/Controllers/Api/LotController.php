<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lot\AddPasseportsRequest;
use App\Http\Requests\Lot\StoreLotRequest;
use App\Http\Requests\Lot\UpdateLotRequest;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\Passeport;
use App\Models\TrackingEvent;
use App\Services\BordereauPdfService;
use App\Services\QrCodeService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LotController extends Controller
{
    public function __construct(
        private QrCodeService       $qrService,
        private BordereauPdfService $bordereauService,
        private StockService        $stockService
    ) {}

    // ── Lecture ───────────────────────────────────────────────────────────────

    /**
     * GET /api/lots
     * Filtres : statut, ambassade_id, transporteur_id, date_from, date_to, search
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Lot::with(['ambassade:id,nom,code,ville', 'transporteur:id,nom,type,is_active'])
            ->when($request->statut,         fn ($q) => $q->where('statut', $request->statut))
            ->when($request->ambassade_id,   fn ($q) => $q->where('ambassade_id', $request->ambassade_id))
            ->when($request->transporteur_id,fn ($q) => $q->where('transporteur_id', $request->transporteur_id))
            ->when($request->date_from,      fn ($q) => $q->whereDate('date_expedition', '>=', $request->date_from))
            ->when($request->date_to,        fn ($q) => $q->whereDate('date_expedition', '<=', $request->date_to))
            ->when($request->search,         fn ($q) => $q->where('reference', 'ilike', '%' . $request->search . '%'))
            ->withCount('passeports')
            ->orderByDesc('created_at');

        // Agents ambassade : seulement leurs lots
        if ($user->isScopedToAmbassade()) {
            $query->where('ambassade_id', $user->ambassade_id);
        }

        $lots = $query->paginate((int) ($request->per_page ?? 20));

        // Enrichir chaque lot avec les accessors
        $lots->getCollection()->transform(fn ($l) => $this->appendAccessors($l));

        return response()->json($lots);
    }

    /**
     * GET /api/lots/{lot}
     */
    public function show(Lot $lot)
    {
        $this->authorize('view', $lot);

        $lot->load(['ambassade', 'transporteur', 'createdBy:id,name', 'anomalies'])
            ->loadCount('passeports');

        return response()->json($this->appendAccessors($lot));
    }

    /**
     * GET /api/lots/{lot}/passeports
     * Liste paginée des passeports du lot avec leur statut de réception dans le pivot.
     */
    public function passeports(Lot $lot, Request $request)
    {
        $this->authorize('view', $lot);

        $passeports = $lot->passeports()
            ->withPivot('statut_reception', 'confirme_at', 'confirme_by', 'notes')
            ->with(['paysDestination:id,nom,code_iso', 'ambassadeDestination:id,nom,code'])
            ->when($request->statut_reception, fn ($q) => $q->wherePivot('statut_reception', $request->statut_reception))
            ->paginate((int) ($request->per_page ?? 50));

        return response()->json($passeports);
    }

    /**
     * GET /api/lots/{lot}/historique
     * Chronologie des événements du lot (TrackingEvents).
     */
    public function historique(Lot $lot)
    {
        $this->authorize('view', $lot);

        $events = TrackingEvent::where('trackable_type', Lot::class)
            ->where('trackable_id', $lot->id)
            ->join('users as u', 'u.id', '=', 'tracking_events.triggered_by', 'left')
            ->select('tracking_events.*', 'u.name as agent_nom', 'u.id as agent_id')
            ->orderBy('tracking_events.created_at')
            ->get()
            ->map(fn ($e) => [
                'event'       => $e->event,
                'description' => $e->description,
                'metadata'    => $e->metadata,
                'agent'       => $e->agent_id ? ['id' => $e->agent_id, 'nom' => $e->agent_nom] : null,
                'date'        => $e->created_at,
                'ip'          => $e->ip_address,
            ]);

        return response()->json([
            'lot'    => ['id' => $lot->id, 'reference' => $lot->reference, 'statut' => $lot->statut],
            'events' => $events,
        ]);
    }

    /**
     * GET /api/lots/{lot}/bordereau
     */
    public function bordereau(Lot $lot)
    {
        $this->authorize('view', $lot);

        if (! $lot->bordereau_path || ! Storage::disk('local')->exists($lot->bordereau_path)) {
            return response()->json(['message' => 'Bordereau non disponible.'], 404);
        }

        $pdf      = Storage::disk('local')->get($lot->bordereau_path);
        $filename = "Bordereau_{$lot->reference}.pdf";

        return response()->json([
            'filename' => $filename,
            'data'     => base64_encode($pdf),
        ]);
    }

    /**
     * GET /api/lots/{lot}/qr-code
     * Retourne l'image PNG du QR code (base64 ou binaire).
     */
    public function qrCode(Lot $lot, Request $request)
    {
        $this->authorize('view', $lot);

        if (! $lot->qr_token) {
            return response()->json(['message' => "Ce lot n'a pas encore de QR code. Il doit être expédié d'abord."], 404);
        }

        if (! $this->qrService->verifyToken($lot->qr_token)) {
            return response()->json(['message' => 'Le QR code de ce lot est expiré ou invalide.'], 422);
        }

        $svgData = $this->qrService->generateQrImage($lot->qr_token);

        if ($request->boolean('base64', false)) {
            return response()->json([
                'data'      => base64_encode($svgData),
                'mime_type' => 'image/svg+xml',
                'lot'       => $lot->reference,
            ]);
        }

        return response($svgData, 200, ['Content-Type' => 'image/svg+xml']);
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    /**
     * POST /api/lots
     */
    public function store(StoreLotRequest $request)
    {
        $this->authorize('create', Lot::class);

        $lot = DB::transaction(function () use ($request) {
            $data               = $request->validated();
            $data['reference']  = Lot::generateReference();
            $data['statut']     = Lot::STATUT_BROUILLON;
            $data['created_by'] = auth()->id();

            $lot = Lot::create($data);

            TrackingEvent::record($lot, 'lot.cree', "Lot {$lot->reference} créé en brouillon.");
            AuditLog::record('lot.create', 'Lot', $lot->id, null, $lot->toArray());

            return $lot;
        });

        return response()->json($this->appendAccessors($lot->load(['ambassade', 'transporteur'])), 201);
    }

    /**
     * PUT /api/lots/{lot}
     */
    public function update(UpdateLotRequest $request, Lot $lot)
    {
        $this->authorize('update', $lot);

        $old = $lot->toArray();
        $lot->update($request->validated());
        AuditLog::record('lot.update', 'Lot', $lot->id, $old, $lot->fresh()->toArray());

        return response()->json($this->appendAccessors($lot->fresh()->load(['ambassade', 'transporteur'])));
    }

    /**
     * DELETE /api/lots/{lot}
     * Remet les passeports en EN_STOCK avant suppression.
     */
    public function destroy(Lot $lot)
    {
        $this->authorize('delete', $lot);

        DB::transaction(function () use ($lot) {
            Passeport::where('lot_id', $lot->id)->update(['statut' => 'en_stock', 'lot_id' => null]);
            $lot->passeports()->detach();
            $lot->delete();
        });

        AuditLog::record('lot.delete', 'Lot', $lot->id);

        return response()->json(null, 204);
    }

    // ── Gestion des passeports ─────────────────────────────────────────────────

    /**
     * POST /api/lots/{lot}/passeports
     * Ajoute des passeports au lot (EN_STOCK uniquement, pas dans un autre lot actif).
     */
    public function addPasseports(AddPasseportsRequest $request, Lot $lot)
    {
        $this->authorize('update', $lot);

        $result = $this->stockService->addPasseportsToLot($lot, $request->validated()['passeport_ids']);

        TrackingEvent::record($lot, 'lot.passeports_ajoutes', "{$result['added']} passeport(s) ajouté(s).", [
            'passeport_ids' => $request->validated()['passeport_ids'],
            'count'         => $result['added'],
        ]);
        AuditLog::record('lot.add_passeports', 'Lot', $lot->id, null, $result);

        return response()->json($result);
    }

    /**
     * DELETE /api/lots/{lot}/passeports/{passeport}
     */
    public function removePasseport(Lot $lot, Passeport $passeport)
    {
        $this->authorize('update', $lot);

        $this->stockService->removePasseportFromLot($lot, $passeport);

        TrackingEvent::record($lot, 'lot.passeport_retire', "Passeport {$passeport->numero} retiré du lot.");
        AuditLog::record('lot.remove_passeport', 'Lot', $lot->id, null, ['passeport_id' => $passeport->id]);

        return response()->json(null, 204);
    }

    // ── Transitions de statut ─────────────────────────────────────────────────

    /**
     * POST /api/lots/{lot}/valider
     * Brouillon → Validé. Vérifie : passeports présents, transporteur actif.
     */
    public function valider(Lot $lot)
    {
        $this->authorize('valider', $lot);

        $count = $lot->passeports()->count();
        if ($count === 0) {
            return response()->json(['message' => 'Le lot doit contenir au moins un passeport.'], 422);
        }

        if (! $lot->transporteur?->is_active) {
            return response()->json([
                'message' => "Le transporteur «{$lot->transporteur?->nom}» est inactif. Veuillez en choisir un autre.",
            ], 422);
        }

        DB::transaction(function () use ($lot, $count) {
            $lot->update(['statut' => Lot::STATUT_VALIDE]);
            TrackingEvent::record($lot, 'lot.valide', "Lot validé avec {$count} passeport(s).", ['count' => $count]);
        });

        AuditLog::record('lot.valider', 'Lot', $lot->id);

        return response()->json($this->appendAccessors($lot->fresh()->load(['ambassade', 'transporteur'])));
    }

    /**
     * POST /api/lots/{lot}/expedier
     * Validé → Expédié. Génère QR, bordereau PDF, passe les passeports à EXPÉDIÉ.
     */
    public function expedier(Lot $lot)
    {
        $this->authorize('expedier', $lot);

        // Garde : transporteur inactif
        if ($lot->transporteur && ! $lot->transporteur->is_active) {
            return response()->json([
                'message' => "Le transporteur «{$lot->transporteur->nom}» est inactif. Veuillez en choisir un autre avant d'expédier.",
            ], 422);
        }

        DB::transaction(function () use ($lot) {
            $token = $this->qrService->generateToken($lot);

            $lot->update([
                'statut'          => Lot::STATUT_EXPEDIE,
                'qr_token'        => $token,
                'date_expedition' => now(),
            ]);

            // Mise à jour en masse des passeports (statut + dispatched_at)
            $passeportIds = $lot->passeports()->pluck('passeports.id');
            Passeport::whereIn('id', $passeportIds)->update([
                'statut'        => 'expedie',
                'dispatched_at' => now(),
            ]);

            // Bulk tracking events pour chaque passeport
            $now    = now();
            $userId = auth()->id();
            $ip     = request()->ip();
            $rows   = $passeportIds->map(fn ($id) => [
                'trackable_type' => Passeport::class,
                'trackable_id'   => $id,
                'event'          => 'statut.expedie',
                'description'    => "Passeport expédié dans le lot {$lot->reference}.",
                'metadata'       => json_encode(['old_statut' => 'en_lot', 'lot_id' => $lot->id]),
                'triggered_by'   => $userId,
                'ip_address'     => $ip,
                'created_at'     => $now,
            ])->all();

            // Insertion par morceaux pour éviter les limites SQL
            foreach (array_chunk($rows, 100) as $chunk) {
                \DB::table('tracking_events')->insert($chunk);
            }

            // Générer le bordereau PDF
            $path = $this->bordereauService->generate($lot);
            $lot->update(['bordereau_path' => $path]);

            TrackingEvent::record($lot, 'lot.expedie', "Lot expédié ({$passeportIds->count()} passeports).", [
                'passeports_count' => $passeportIds->count(),
                'date_expedition'  => now()->toDateString(),
            ]);
        });

        AuditLog::record('lot.expedier', 'Lot', $lot->id);

        return response()->json($this->appendAccessors($lot->fresh()->load(['ambassade', 'transporteur'])));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function appendAccessors(Lot $lot): Lot
    {
        $lot->append(['statut_label', 'statut_color', 'tracking_url']);

        return $lot;
    }
}
