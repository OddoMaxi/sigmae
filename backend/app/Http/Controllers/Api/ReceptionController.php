<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reception\ConfirmerLotRequest;
use App\Http\Requests\Reception\SignalerAnomalieRequest;
use App\Models\AuditLog;
use App\Models\Anomalie;
use App\Models\Lot;
use App\Models\Passeport;
use App\Models\TrackingEvent;
use App\Services\QrCodeService;
use App\Services\ReceptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceptionController extends Controller
{
    public function __construct(
        private QrCodeService    $qrService,
        private ReceptionService $receptionService
    ) {}

    // ── Scan public ───────────────────────────────────────────────────────────

    /**
     * GET /api/reception/scan/{token}   [public]
     *
     * Valide le token QR et retourne un résumé non-sensible du lot.
     * Aucune liste de passeports — uniquement ce qu'il faut pour que l'agent
     * sache ce qu'il va réceptionner avant de s'authentifier.
     */
    public function scan(string $token)
    {
        $verification = $this->qrService->verifyTokenWithDetails($token);

        if (! $verification['valid']) {
            return response()->json([
                'valid'         => false,
                'error_code'    => $verification['error_code'],
                'error_message' => $verification['error_message'],
            ], 400);
        }

        $payload = $verification['payload'];

        $lot = Lot::with(['ambassade:id,nom,code,ville', 'transporteur:id,nom,type'])
            ->withCount('passeports')
            ->find($payload['lot_id']);

        if (! $lot) {
            return response()->json([
                'valid'      => false,
                'error_code' => 'LOT_NOT_FOUND',
                'error_message' => 'Aucun lot ne correspond à ce QR code.',
            ], 404);
        }

        if ($payload['amb'] !== $lot->ambassade_id || $payload['ref'] !== $lot->reference) {
            return response()->json([
                'valid'      => false,
                'error_code' => 'PAYLOAD_MISMATCH',
                'error_message' => 'Les données du QR code ne correspondent pas à ce lot.',
            ], 400);
        }

        if ($lot->isRecu()) {
            return response()->json([
                'valid'            => true,
                'error_code'       => 'ALREADY_RECEIVED',
                'error_message'    => 'Ce lot a déjà été réceptionné.',
                'lot_reference'    => $lot->reference,
                'lot_statut'       => $lot->statut,
                'lot_statut_label' => $lot->statut_label,
                'ambassade'        => $lot->ambassade?->only(['id', 'nom', 'code', 'ville']),
            ], 409);
        }

        return response()->json([
            'valid'          => true,
            'requires_auth'  => true,
            'lot' => [
                'id'              => $lot->id,
                'reference'       => $lot->reference,
                'statut'          => $lot->statut,
                'statut_label'    => $lot->statut_label,
                'statut_color'    => $lot->statut_color,
                'nb_passeports'   => $lot->passeports_count,
                'date_expedition' => $lot->date_expedition?->toDateString(),
                'date_prevue'     => $lot->date_reception_prevue?->toDateString(),
                'ambassade'       => $lot->ambassade?->only(['id', 'nom', 'code', 'ville']),
                'transporteur'    => $lot->transporteur?->only(['id', 'nom', 'type']),
            ],
            'token_expires_at' => date('Y-m-d', $payload['exp']),
            'token_issued_at'  => date('Y-m-d H:i', $payload['iat']),
        ]);
    }

    // ── Détail authentifié ────────────────────────────────────────────────────

    /**
     * GET /api/reception/detail/{token}   [auth — lots.receive]
     *
     * Retourne le contenu complet du lot avec la liste des passeports et
     * leur statut de réception dans le pivot. Vérifie l'ambassade.
     */
    public function detail(string $token)
    {
        $user         = request()->user();
        $verification = $this->qrService->verifyTokenWithDetails($token);

        if (! $verification['valid']) {
            return response()->json([
                'error_code'    => $verification['error_code'],
                'error_message' => $verification['error_message'],
            ], 400);
        }

        $payload = $verification['payload'];

        $lot = Lot::with(['ambassade', 'transporteur:id,nom,type,telephone,lien_suivi', 'createdBy:id,name'])
            ->withCount([
                'passeports',
                'passeports as en_attente_count' => fn ($q) => $q->where('lot_passeports.statut_reception', 'en_attente'),
                'passeports as confirmes_count'  => fn ($q) => $q->where('lot_passeports.statut_reception', 'confirme'),
                'passeports as anomalies_count'  => fn ($q) => $q->where('lot_passeports.statut_reception', 'anomalie'),
                'passeports as manquants_count'  => fn ($q) => $q->where('lot_passeports.statut_reception', 'manquant'),
            ])
            ->find($payload['lot_id']);

        if (! $lot) {
            return response()->json(['error_code' => 'LOT_NOT_FOUND', 'message' => 'Lot introuvable.'], 404);
        }

        if ($user->isScopedToAmbassade() && $user->ambassade_id !== $lot->ambassade_id) {
            return response()->json([
                'error_code' => 'AMBASSADE_MISMATCH',
                'message'    => "Ce lot est destiné à l'ambassade «{$lot->ambassade?->nom}» et non à la vôtre.",
            ], 403);
        }

        if (! in_array($lot->statut, [Lot::STATUT_EXPEDIE, 'en_transit', Lot::STATUT_RECU_PARTIEL, Lot::STATUT_RECU])) {
            return response()->json([
                'error_code' => 'LOT_NOT_EXPEDITED',
                'message'    => "Ce lot n'est pas en cours d'acheminement (statut : {$lot->statut_label}).",
            ], 422);
        }

        $passeports = $lot->passeports()
            ->withPivot('statut_reception', 'confirme_at', 'confirme_by', 'notes')
            ->with(['paysDestination:id,nom,code_iso'])
            ->get()
            ->map(fn ($p) => [
                'id'               => $p->id,
                'numero'           => $p->numero,
                'nom_complet'      => $p->nom_complet,
                'date_naissance'   => $p->date_naissance?->toDateString(),
                'statut'           => $p->statut,
                'statut_label'     => $p->statut_label,
                'statut_color'     => $p->statut_color,
                'email_citoyen'    => $p->email_citoyen,
                'pays_destination' => $p->paysDestination?->only(['nom', 'code_iso']),
                'disponible_at'    => $p->disponible_at?->toIso8601String(),
                'reception'        => [
                    'statut'      => $p->pivot->statut_reception,
                    'confirme_at' => $p->pivot->confirme_at,
                    'notes'       => $p->pivot->notes,
                ],
            ]);

        return response()->json([
            'lot' => [
                'id'                       => $lot->id,
                'reference'                => $lot->reference,
                'statut'                   => $lot->statut,
                'statut_label'             => $lot->statut_label,
                'statut_color'             => $lot->statut_color,
                'reference_suivi'          => $lot->reference_suivi,
                'tracking_url'             => $lot->tracking_url,
                'date_expedition'          => $lot->date_expedition?->toDateString(),
                'date_prevue'              => $lot->date_reception_prevue?->toDateString(),
                'date_reception_effective' => $lot->date_reception_effective?->toIso8601String(),
                'notes'                    => $lot->notes,
                'commentaire_ambassade'    => $lot->commentaire_ambassade,
                'ambassade'                => $lot->ambassade,
                'transporteur'             => $lot->transporteur?->only(['id', 'nom', 'type', 'telephone']),
                'cree_par'                 => $lot->createdBy?->only(['id', 'name']),
                'nb_passeports'            => $lot->passeports_count,
                'en_attente'               => $lot->en_attente_count,
                'confirmes'                => $lot->confirmes_count,
                'anomalies'                => $lot->anomalies_count,
                'manquants'                => $lot->manquants_count,
            ],
            'passeports'  => $passeports,
            'token_info'  => [
                'issued_at'  => date('Y-m-d H:i:s', $payload['iat']),
                'expires_at' => date('Y-m-d H:i:s', $payload['exp']),
                'version'    => $payload['v'] ?? 1,
            ],
        ]);
    }

    // ── Confirmation en lot (batch) ───────────────────────────────────────────

    /**
     * POST /api/reception/lot/{token}   [auth — lots.receive]
     *
     * Confirme la réception d'un lot (totale ou partielle).
     *
     * statut par passeport : confirme | anomalie | manquant
     * Options :
     *   aller_disponible_retrait (bool) — enchaîner vers DISPONIBLE_RETRAIT + email
     *   commentaire (string) — commentaire global de l'ambassade
     */
    public function confirmerLot(ConfirmerLotRequest $request, string $token)
    {
        $user         = $request->user();
        $verification = $this->qrService->verifyTokenWithDetails($token);

        if (! $verification['valid']) {
            return response()->json([
                'error_code'    => $verification['error_code'],
                'error_message' => $verification['error_message'],
            ], 400);
        }

        $lot  = Lot::with('passeports')->findOrFail($verification['payload']['lot_id']);
        $data = $request->validated();

        if ($user->isScopedToAmbassade() && $user->ambassade_id !== $lot->ambassade_id) {
            return response()->json(['error_code' => 'AMBASSADE_MISMATCH', 'message' => "Vous n'êtes pas autorisé à réceptionner ce lot."], 403);
        }

        if ($lot->isRecu()) {
            return response()->json(['message' => 'Ce lot a déjà été entièrement réceptionné.'], 422);
        }

        if (! in_array($lot->statut, [Lot::STATUT_EXPEDIE, 'en_transit', Lot::STATUT_RECU_PARTIEL])) {
            return response()->json(['error_code' => 'LOT_NOT_EXPEDITED', 'message' => "Ce lot ne peut pas être réceptionné (statut : {$lot->statut_label})."], 422);
        }

        // Vérifier que les passeports soumis appartiennent à ce lot
        $lotIds      = $lot->passeports->pluck('id')->toArray();
        $requestIds  = array_column($data['confirmations'], 'passeport_id');
        $outsiders   = array_diff($requestIds, $lotIds);

        if (! empty($outsiders)) {
            return response()->json([
                'error_code' => 'PASSEPORT_NOT_IN_LOT',
                'message'    => "Certains passeports soumis n'appartiennent pas à ce lot.",
                'ids'        => array_values($outsiders),
            ], 422);
        }

        // Sauvegarder le commentaire ambassade
        if (! empty($data['commentaire'])) {
            $lot->update(['commentaire_ambassade' => $data['commentaire']]);
            TrackingEvent::record($lot, 'lot.commentaire_ambassade', $data['commentaire']);
        }

        $allerDisponible = $request->boolean('aller_disponible_retrait', false);
        $summary = $this->receptionService->confirmerReception($lot, $data['confirmations'], $user, $allerDisponible);

        AuditLog::record('lot.reception', 'Lot', $lot->id, null, $summary);

        return response()->json([
            'message'              => 'Réception enregistrée.',
            'confirmes'            => $summary['confirmes'],
            'anomalies'            => $summary['anomalies'],
            'manquants'            => $summary['manquants'],
            'emails_envoyes'       => $summary['emails_envoyes'],
            'aller_disponible'     => $allerDisponible,
            'lot_statut'           => $summary['statut'],
            'lot_statut_label'     => Lot::STATUT_LABELS[$summary['statut']] ?? $summary['statut'],
            'lot'                  => $lot->fresh()->append(['statut_label', 'statut_color']),
        ]);
    }

    // ── Passage en disponible retrait (batch) ─────────────────────────────────

    /**
     * POST /api/reception/lot/{lot}/disponible   [auth — lots.receive]
     *
     * Passe tous les passeports REÇU_AMBASSADE du lot en DISPONIBLE_RETRAIT
     * et envoie l'email à chaque citoyen.
     *
     * Body optionnel : { "passeport_ids": [1, 2, 3] } pour sélection partielle.
     */
    public function passerDisponible(Request $request, Lot $lot)
    {
        $user = $request->user();

        $request->validate([
            'passeport_ids'   => 'nullable|array',
            'passeport_ids.*' => 'integer|exists:passeports,id',
        ]);

        if ($user->isScopedToAmbassade() && $user->ambassade_id !== $lot->ambassade_id) {
            return response()->json(['message' => "Vous n'êtes pas autorisé pour ce lot."], 403);
        }

        if (! in_array($lot->statut, [Lot::STATUT_RECU, Lot::STATUT_RECU_PARTIEL, Lot::STATUT_EXPEDIE, 'en_transit'])) {
            return response()->json(['message' => "Ce lot n'est pas en état de permettre une mise en disponibilité (statut : {$lot->statut_label})."], 422);
        }

        $result = $this->receptionService->passerTousDisponibles($lot, $user, $request->passeport_ids);

        AuditLog::record('lot.disponible_retrait', 'Lot', $lot->id, null, $result);

        return response()->json([
            'message'       => "{$result['traites']} passeport(s) mis en DISPONIBLE_RETRAIT.",
            'traites'       => $result['traites'],
            'emails_envoyes'=> $result['emails_envoyes'],
            'echecs'        => $result['echecs'],
        ]);
    }

    // ── Confirmation individuelle ─────────────────────────────────────────────

    /**
     * POST /api/reception/lot-detail/{lot}/passeport/{passeport}   [auth — lots.receive]
     *
     * Confirme un passeport individuellement : expedie|en_transit → recu_ambassade.
     */
    public function confirmerPasseport(Request $request, Lot $lot, Passeport $passeport)
    {
        $user = $request->user();

        $request->validate(['note' => 'nullable|string|max:500']);

        if ($user->isScopedToAmbassade() && $user->ambassade_id !== $lot->ambassade_id) {
            return response()->json(['message' => "Vous n'êtes pas autorisé à réceptionner ce lot."], 403);
        }

        if (! $lot->passeports()->where('passeports.id', $passeport->id)->exists()) {
            return response()->json(['message' => "Ce passeport n'appartient pas à ce lot."], 422);
        }

        if (! in_array($passeport->statut, ['expedie', 'en_transit'])) {
            return response()->json(['message' => "Ce passeport ne peut pas être réceptionné (statut : {$passeport->statut_label})."], 422);
        }

        $note = $request->input('note');

        DB::transaction(function () use ($lot, $passeport, $note, $user) {
            $lot->passeports()->updateExistingPivot($passeport->id, [
                'statut_reception' => 'confirme',
                'confirme_at'      => now(),
                'confirme_by'      => $user->id,
                'notes'            => $note,
            ]);

            $passeport->transitionTo(
                Passeport::STATUT_RECU_AMBASSADE,
                "Réceptionné individuellement — lot {$lot->reference}." . ($note ? " Note : {$note}" : ''),
                ['lot_id' => $lot->id]
            );

            // Recalcul statut lot si tout est traité
            if ($lot->passeports()->wherePivot('statut_reception', 'en_attente')->count() === 0) {
                $hasProbleme = $lot->passeports()
                    ->whereIn(\DB::raw('lot_passeports.statut_reception'), ['anomalie', 'manquant'])
                    ->exists();

                $allOk = ! $lot->passeports()
                    ->where(\DB::raw('lot_passeports.statut_reception'), '!=', 'confirme')
                    ->exists();

                $lot->update([
                    'statut'                   => $allOk ? 'recu' : ($hasProbleme ? 'recu_partiel' : 'recu'),
                    'date_reception_effective' => now(),
                ]);

                TrackingEvent::record($lot, 'lot.reception_complete', 'Tous les passeports ont été traités.');
            }
        });

        AuditLog::record('passeport.recu_ambassade', 'Passeport', $passeport->id);

        return response()->json([
            'message'      => 'Passeport réceptionné.',
            'statut'       => $passeport->fresh()->statut,
            'statut_label' => $passeport->fresh()->statut_label,
        ]);
    }

    // ── Signalement d'anomalie ────────────────────────────────────────────────

    /**
     * POST /api/reception/lot-detail/{lot}/passeport/{passeport}/anomalie   [auth — lots.receive]
     */
    public function signalerAnomalie(SignalerAnomalieRequest $request, Lot $lot, Passeport $passeport)
    {
        $user = $request->user();

        if ($user->isScopedToAmbassade() && $user->ambassade_id !== $lot->ambassade_id) {
            return response()->json(['message' => "Vous n'êtes pas autorisé pour ce lot."], 403);
        }

        if (! $lot->passeports()->where('passeports.id', $passeport->id)->exists()) {
            return response()->json(['message' => "Ce passeport n'appartient pas à ce lot."], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($lot, $passeport, $data, $user) {
            $lot->passeports()->updateExistingPivot($passeport->id, [
                'statut_reception' => 'anomalie',
                'confirme_at'      => now(),
                'confirme_by'      => $user->id,
                'notes'            => $data['description'],
            ]);

            if (! $passeport->estAnomalie()) {
                $passeport->transitionTo(
                    Passeport::STATUT_ANOMALIE,
                    "Anomalie signalée à la réception du lot {$lot->reference} : {$data['description']}",
                    ['lot_id' => $lot->id, 'type' => $data['type']]
                );
            }

            Anomalie::create([
                'type'         => $data['type'],
                'passeport_id' => $passeport->id,
                'lot_id'       => $lot->id,
                'description'  => $data['description'],
                'signale_by'   => $user->id,
            ]);
        });

        AuditLog::record('anomalie.signaler', 'Passeport', $passeport->id);

        return response()->json(['message' => 'Anomalie signalée.'], 201);
    }

    // ── Passeport manquant ────────────────────────────────────────────────────

    /**
     * POST /api/reception/lot-detail/{lot}/passeport/{passeport}/manquant   [auth — lots.receive]
     *
     * Marque un passeport comme physiquement absent du lot à l'ouverture.
     */
    public function marquerManquant(Request $request, Lot $lot, Passeport $passeport)
    {
        $user = $request->user();

        $request->validate(['note' => 'nullable|string|max:500']);

        if ($user->isScopedToAmbassade() && $user->ambassade_id !== $lot->ambassade_id) {
            return response()->json(['message' => "Vous n'êtes pas autorisé pour ce lot."], 403);
        }

        if (! $lot->passeports()->where('passeports.id', $passeport->id)->exists()) {
            return response()->json(['message' => "Ce passeport n'appartient pas à ce lot."], 422);
        }

        if (! in_array($passeport->statut, ['expedie', 'en_transit', 'anomalie'])) {
            return response()->json(['message' => "Ce passeport ne peut pas être signalé manquant (statut : {$passeport->statut_label})."], 422);
        }

        $this->receptionService->marquerManquant($lot, $passeport, $user, $request->input('note'));

        AuditLog::record('passeport.manquant', 'Passeport', $passeport->id);

        return response()->json([
            'message'      => 'Passeport signalé comme manquant.',
            'statut'       => $passeport->fresh()->statut,
            'statut_label' => $passeport->fresh()->statut_label,
        ], 201);
    }

    // ── Historique de réception ───────────────────────────────────────────────

    /**
     * GET /api/reception/lot/{lot}/historique   [auth — lots.receive]
     *
     * Timeline complète de la réception : événements lot, confirmations
     * passeport par passeport, anomalies.
     */
    public function historiqueReception(Lot $lot)
    {
        $user = request()->user();

        if ($user->isScopedToAmbassade() && $user->ambassade_id !== $lot->ambassade_id) {
            return response()->json(['message' => "Accès refusé pour ce lot."], 403);
        }

        return response()->json($this->receptionService->historiqueReception($lot));
    }

    // ── Commentaire ambassade ─────────────────────────────────────────────────

    /**
     * POST /api/reception/lot/{lot}/commentaire   [auth — lots.receive]
     *
     * Ajoute ou met à jour le commentaire global de l'ambassade sur ce lot.
     */
    public function commentaireAmbassade(Request $request, Lot $lot)
    {
        $user = $request->user();

        $request->validate(['commentaire' => 'required|string|max:2000']);

        if ($user->isScopedToAmbassade() && $user->ambassade_id !== $lot->ambassade_id) {
            return response()->json(['message' => "Vous n'êtes pas autorisé pour ce lot."], 403);
        }

        $commentaire = $request->input('commentaire');
        $lot->update(['commentaire_ambassade' => $commentaire]);

        TrackingEvent::record($lot, 'lot.commentaire_ambassade', $commentaire, [
            'agent_id' => $user->id,
        ]);

        AuditLog::record('lot.commentaire', 'Lot', $lot->id);

        return response()->json([
            'message'               => 'Commentaire enregistré.',
            'commentaire_ambassade' => $commentaire,
        ]);
    }
}
