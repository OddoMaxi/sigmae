<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Passeport\StoreEnrolementRequest;
use App\Models\Passeport;
use App\Models\TrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrolementController extends Controller
{
    // ── Liste des enrôlements ──────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Passeport::with([
                'ambassadeDestination:id,nom,code,ville',
                'paysDestination:id,nom,code_iso',
                'agentEnrolement:id,name',
            ])
            ->where('statut', Passeport::STATUT_ENROLEE)
            // Un agent d'enrôlement ne voit que son ambassade
            ->when($user->isScopedToAmbassade(), fn ($q) =>
                $q->where('ambassade_destination_id', $user->ambassade_id)
            )
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('nom_titulaire', 'ilike', "%{$request->search}%")
                   ->orWhere('prenom_titulaire', 'ilike', "%{$request->search}%")
                   ->orWhere('reference_demande', 'ilike', "%{$request->search}%")
                   ->orWhere('email_citoyen', 'ilike', "%{$request->search}%")
                   ->orWhere('telephone', 'ilike', "%{$request->search}%");
            }))
            ->when($request->date_from, fn ($q) => $q->whereDate('enrolled_at', '>=', $request->date_from))
            ->when($request->date_to,   fn ($q) => $q->whereDate('enrolled_at', '<=', $request->date_to))
            ->orderByDesc('enrolled_at');

        return response()->json($query->paginate((int) ($request->per_page ?? 50)));
    }

    // ── Détail ─────────────────────────────────────────────────────────────────

    public function show(Passeport $passeport)
    {
        abort_unless($passeport->statut === Passeport::STATUT_ENROLEE, 404);

        $user = request()->user();
        if ($user->isScopedToAmbassade()) {
            abort_unless($passeport->ambassade_destination_id === $user->ambassade_id, 403);
        }

        $passeport->load([
            'ambassadeDestination:id,nom,code,ville',
            'paysDestination:id,nom,code_iso',
            'agentEnrolement:id,name',
        ]);

        return response()->json($passeport);
    }

    // ── Créer un enrôlement ────────────────────────────────────────────────────

    public function store(StoreEnrolementRequest $request)
    {
        $user = $request->user();

        // L'agent d'enrôlement doit être rattaché à une ambassade
        if (! $user->ambassade_id) {
            throw ValidationException::withMessages([
                'ambassade' => 'Votre compte n\'est pas rattaché à une ambassade.',
            ]);
        }

        $ambassade = $user->ambassade;
        if (! $ambassade) {
            throw ValidationException::withMessages([
                'ambassade' => 'Ambassade introuvable.',
            ]);
        }

        $passeport = Passeport::create([
            'numero'                   => null,
            'reference_demande'        => $this->generateReference($ambassade->code),
            'prenom_titulaire'         => $request->prenom_titulaire,
            'nom_titulaire'            => $request->nom_titulaire,
            'date_naissance'           => $request->date_naissance,
            'email_citoyen'            => $request->email_citoyen,
            'telephone'                => $request->telephone,
            'ambassade_destination_id' => $user->ambassade_id,
            'pays_destination_id'      => $ambassade->pays_id,
            'statut'                   => Passeport::STATUT_ENROLEE,
            'enrolled_at'              => now(),
            'enrolled_by'              => $user->id,
        ]);

        TrackingEvent::record(
            $passeport,
            'statut.enrolee',
            "Enrôlement biométrique enregistré par {$user->name} ({$ambassade->nom})",
            ['agent_id' => $user->id, 'ambassade_id' => $user->ambassade_id]
        );

        $passeport->load(['ambassadeDestination:id,nom,code,ville', 'agentEnrolement:id,name']);

        return response()->json($passeport, 201);
    }

    // ── MAE : assigner le numéro de passeport à un enrôlement ─────────────────

    public function assignerNumero(Request $request, Passeport $passeport)
    {
        abort_unless($passeport->statut === Passeport::STATUT_ENROLEE, 422, 'Ce dossier n\'est pas en attente d\'impression.');

        $request->validate([
            'numero'           => ['required', 'string', 'max:50', 'unique:passeports,numero'],
            'date_impression'  => ['nullable', 'date'],
        ]);

        $passeport->update([
            'numero'          => $request->numero,
            'statut'          => Passeport::STATUT_IMPRIME,
            'date_impression' => $request->date_impression ?? now()->toDateString(),
        ]);

        TrackingEvent::record(
            $passeport,
            'statut.imprime',
            "Numéro de passeport {$request->numero} assigné — passeport imprimé.",
            ['numero' => $request->numero, 'assigned_by' => $request->user()->id]
        );

        return response()->json($passeport->fresh());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function generateReference(string $ambassadeCode): string
    {
        // Format : DEM-{CODE_AMB}-{YYYYMMDD}-{5 chars aléatoires}
        // Ex    : DEM-FRPAR-20260601-X7K2P
        $code    = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $ambassadeCode));
        $date    = now()->format('Ymd');
        $suffix  = strtoupper(substr(base_convert(bin2hex(random_bytes(3)), 16, 36), 0, 5));

        $ref = "DEM-{$code}-{$date}-{$suffix}";

        // Garantir l'unicité (collision extrêmement rare mais possible)
        while (Passeport::where('reference_demande', $ref)->exists()) {
            $suffix = strtoupper(substr(base_convert(bin2hex(random_bytes(3)), 16, 36), 0, 5));
            $ref    = "DEM-{$code}-{$date}-{$suffix}";
        }

        return $ref;
    }
}
