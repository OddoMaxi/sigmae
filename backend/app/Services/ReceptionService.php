<?php

namespace App\Services;

use App\Models\Anomalie;
use App\Models\Lot;
use App\Models\Passeport;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReceptionService
{
    public function __construct(private EmailNotificationService $emailService) {}

    // ── Confirmation ──────────────────────────────────────────────────────────

    /**
     * Traite les confirmations d'un lot en une transaction.
     *
     * Chaque confirmation peut avoir le statut :
     *   - confirme  → passeport transitionne vers RECU_AMBASSADE
     *   - anomalie  → passeport transitionne vers ANOMALIE + création Anomalie
     *   - manquant  → passeport transitionne vers ANOMALIE (type=manquant) + création Anomalie
     *
     * Si $allerDisponible = true, les passeports confirmés passent directement
     * en DISPONIBLE_RETRAIT et l'email est envoyé automatiquement.
     *
     * @return array{confirmes: int, anomalies: int, manquants: int, statut: string, emails_envoyes: int}
     */
    public function confirmerReception(Lot $lot, array $confirmations, User $user, bool $allerDisponible = false): array
    {
        return DB::transaction(function () use ($lot, $confirmations, $user, $allerDisponible) {
            $confirmedCount = 0;
            $anomalieCount  = 0;
            $manquantCount  = 0;
            $emailsEnvoyes  = 0;

            foreach ($confirmations as $conf) {
                $passeportId    = $conf['passeport_id'];
                $statut         = $conf['statut'];
                $note           = $conf['note'] ?? null;
                $typeAnomalie   = $conf['type_anomalie'] ?? ($statut === 'manquant' ? 'manquant' : 'autre');

                // Mise à jour pivot
                $lot->passeports()->updateExistingPivot($passeportId, [
                    'statut_reception' => $statut,
                    'confirme_at'      => now(),
                    'confirme_by'      => $user->id,
                    'notes'            => $note,
                ]);

                $passeport = Passeport::find($passeportId);

                if ($statut === 'confirme') {
                    $passeport->transitionTo(
                        Passeport::STATUT_RECU_AMBASSADE,
                        "Réceptionné à l'ambassade — lot {$lot->reference}." . ($note ? " Note : {$note}" : ''),
                        ['lot_id' => $lot->id]
                    );
                    $confirmedCount++;

                    // Si l'option aller_disponible est activée, enchaîner la 2e transition
                    if ($allerDisponible) {
                        $passeport->refresh();
                        $passeport->transitionTo(
                            Passeport::STATUT_DISPONIBLE_RETRAIT,
                            "Disponible pour retrait — mis à disposition lors de la réception du lot {$lot->reference}.",
                            ['lot_id' => $lot->id]
                        );
                        $passeport->update(['disponible_at' => now()]);

                        // Mise à jour pivot pour refléter la disponibilité
                        $lot->passeports()->updateExistingPivot($passeportId, [
                            'statut_reception' => 'confirme',
                        ]);

                        // Envoi email citoyen
                        if ($this->emailService->sendPasseportDisponible($passeport->fresh())) {
                            $emailsEnvoyes++;
                        }
                    }
                } else {
                    // anomalie ou manquant
                    $description = match ($statut) {
                        'manquant' => "Passeport manquant lors de la réception du lot {$lot->reference}." . ($note ? " {$note}" : ''),
                        default    => "Anomalie signalée à la réception du lot {$lot->reference} : " . ($note ?? 'sans détail'),
                    };

                    if (! $passeport->estAnomalie()) {
                        $passeport->transitionTo(
                            Passeport::STATUT_ANOMALIE,
                            $description,
                            ['lot_id' => $lot->id, 'type' => $typeAnomalie]
                        );
                    }

                    Anomalie::create([
                        'type'         => $typeAnomalie,
                        'passeport_id' => $passeportId,
                        'lot_id'       => $lot->id,
                        'description'  => $description,
                        'signale_by'   => $user->id,
                    ]);

                    $statut === 'manquant' ? $manquantCount++ : $anomalieCount++;
                }
            }

            // Recalculer le statut du lot
            $newLotStatut = $this->calculerStatutLot($lot, $confirmedCount, $anomalieCount + $manquantCount);

            $lot->update([
                'statut'                   => $newLotStatut,
                'date_reception_effective' => in_array($newLotStatut, ['recu', 'recu_partiel', 'anomalie'])
                    ? now()
                    : $lot->date_reception_effective,
            ]);

            TrackingEvent::record(
                $lot,
                'lot.reception',
                "Réception : {$confirmedCount} confirmés, {$anomalieCount} anomalies, {$manquantCount} manquants.",
                [
                    'confirmes'        => $confirmedCount,
                    'anomalies'        => $anomalieCount,
                    'manquants'        => $manquantCount,
                    'emails_envoyes'   => $emailsEnvoyes,
                    'aller_disponible' => $allerDisponible,
                    'new_statut'       => $newLotStatut,
                ]
            );

            return [
                'confirmes'      => $confirmedCount,
                'anomalies'      => $anomalieCount,
                'manquants'      => $manquantCount,
                'statut'         => $newLotStatut,
                'emails_envoyes' => $emailsEnvoyes,
            ];
        });
    }

    // ── Passage en disponible retrait (batch) ─────────────────────────────────

    /**
     * Passe tous les passeports REÇU_AMBASSADE d'un lot en DISPONIBLE_RETRAIT
     * et envoie l'email à chaque citoyen.
     *
     * Si $passeportIds est fourni, seuls ces passeports sont traités.
     *
     * @return array{traites: int, emails_envoyes: int, echecs: int}
     */
    public function passerTousDisponibles(Lot $lot, User $user, ?array $passeportIds = null): array
    {
        $query = $lot->passeports()
            ->where('passeports.statut', Passeport::STATUT_RECU_AMBASSADE);

        if ($passeportIds !== null) {
            $query->whereIn('passeports.id', $passeportIds);
        }

        $passeports = $query->get();

        if ($passeports->isEmpty()) {
            return ['traites' => 0, 'emails_envoyes' => 0, 'echecs' => 0];
        }

        $traites       = 0;
        $emailsEnvoyes = 0;
        $echecs        = 0;

        foreach ($passeports as $passeport) {
            DB::transaction(function () use ($passeport, $lot, $user, &$traites, &$emailsEnvoyes, &$echecs) {
                try {
                    $passeport->transitionTo(
                        Passeport::STATUT_DISPONIBLE_RETRAIT,
                        "Disponible pour retrait — lot {$lot->reference}.",
                        ['lot_id' => $lot->id]
                    );
                    $passeport->update(['disponible_at' => now()]);
                    $traites++;

                    if ($this->emailService->sendPasseportDisponible($passeport->fresh())) {
                        $emailsEnvoyes++;
                    }
                } catch (\Throwable) {
                    $echecs++;
                }
            });
        }

        if ($traites > 0) {
            TrackingEvent::record(
                $lot,
                'lot.disponible_retrait',
                "{$traites} passeport(s) passés en DISPONIBLE_RETRAIT, {$emailsEnvoyes} email(s) envoyés.",
                ['traites' => $traites, 'emails_envoyes' => $emailsEnvoyes, 'echecs' => $echecs]
            );
        }

        return ['traites' => $traites, 'emails_envoyes' => $emailsEnvoyes, 'echecs' => $echecs];
    }

    // ── Passeport manquant ────────────────────────────────────────────────────

    /**
     * Marque un passeport comme physiquement absent du lot à la réception.
     */
    public function marquerManquant(Lot $lot, Passeport $passeport, User $user, ?string $note = null): void
    {
        DB::transaction(function () use ($lot, $passeport, $user, $note) {
            $description = "Passeport manquant à la réception du lot {$lot->reference}." . ($note ? " {$note}" : '');

            $lot->passeports()->updateExistingPivot($passeport->id, [
                'statut_reception' => 'manquant',
                'confirme_at'      => now(),
                'confirme_by'      => $user->id,
                'notes'            => $note,
            ]);

            if (! $passeport->estAnomalie()) {
                $passeport->transitionTo(Passeport::STATUT_ANOMALIE, $description, [
                    'lot_id' => $lot->id,
                    'type'   => 'manquant',
                ]);
            }

            Anomalie::create([
                'type'         => 'manquant',
                'passeport_id' => $passeport->id,
                'lot_id'       => $lot->id,
                'description'  => $description,
                'signale_by'   => $user->id,
            ]);

            // Recalcul statut lot
            $this->recalculerStatutLot($lot);
        });
    }

    // ── Historique de réception ───────────────────────────────────────────────

    /**
     * Construit la timeline complète de réception d'un lot.
     *
     * Fusionné :
     * 1. Événements lot depuis tracking_events
     * 2. Confirmations individuelles depuis lot_passeports (avec agent)
     * 3. Anomalies liées au lot
     */
    public function historiqueReception(Lot $lot): array
    {
        // 1. Événements lot
        $lotEvents = TrackingEvent::where('trackable_type', Lot::class)
            ->where('trackable_id', $lot->id)
            ->leftJoin('users', 'users.id', '=', 'tracking_events.triggered_by')
            ->select(
                'tracking_events.event',
                'tracking_events.description',
                'tracking_events.metadata',
                'tracking_events.created_at as date',
                'users.name as agent_nom',
                'users.id as agent_id'
            )
            ->orderBy('tracking_events.created_at')
            ->get()
            ->map(fn ($e) => [
                'type'        => 'lot_event',
                'event'       => $e->event,
                'description' => $e->description,
                'metadata'    => $e->metadata ? json_decode($e->metadata, true) : null,
                'date'        => $e->date,
                'agent'       => $e->agent_id ? ['id' => $e->agent_id, 'nom' => $e->agent_nom] : null,
            ]);

        // 2. Confirmations par passeport depuis le pivot
        $pivotEvents = DB::table('lot_passeports as lp')
            ->join('passeports as p', 'p.id', '=', 'lp.passeport_id')
            ->leftJoin('users as u', 'u.id', '=', 'lp.confirme_by')
            ->where('lp.lot_id', $lot->id)
            ->whereNotNull('lp.confirme_at')
            ->select(
                'p.id as passeport_id',
                'p.numero as passeport_numero',
                'p.nom_titulaire',
                'p.prenom_titulaire',
                'lp.statut_reception',
                'lp.notes',
                'lp.confirme_at as date',
                'u.id as agent_id',
                'u.name as agent_nom'
            )
            ->orderBy('lp.confirme_at')
            ->get()
            ->map(fn ($r) => [
                'type'             => 'passport_confirm',
                'event'            => "passeport.{$r->statut_reception}",
                'description'      => $this->labelConfirmation($r->statut_reception, $r->passeport_numero),
                'passeport'        => [
                    'id'         => $r->passeport_id,
                    'numero'     => $r->passeport_numero,
                    'nom_complet'=> "{$r->prenom_titulaire} {$r->nom_titulaire}",
                ],
                'statut_reception' => $r->statut_reception,
                'notes'            => $r->notes,
                'date'             => $r->date,
                'agent'            => $r->agent_id ? ['id' => $r->agent_id, 'nom' => $r->agent_nom] : null,
            ]);

        // 3. Anomalies liées à ce lot
        $anomalies = DB::table('anomalies as a')
            ->join('passeports as p', 'p.id', '=', 'a.passeport_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.signale_by')
            ->where('a.lot_id', $lot->id)
            ->select(
                'a.id as anomalie_id',
                'a.type',
                'a.description',
                'a.statut as statut_anomalie',
                'a.created_at as date',
                'p.id as passeport_id',
                'p.numero as passeport_numero',
                'u.id as agent_id',
                'u.name as agent_nom'
            )
            ->orderBy('a.created_at')
            ->get()
            ->map(fn ($r) => [
                'type'             => 'anomalie',
                'event'            => "anomalie.{$r->type}",
                'description'      => $r->description,
                'anomalie_id'      => $r->anomalie_id,
                'statut_anomalie'  => $r->statut_anomalie,
                'passeport'        => ['id' => $r->passeport_id, 'numero' => $r->passeport_numero],
                'date'             => $r->date,
                'agent'            => $r->agent_id ? ['id' => $r->agent_id, 'nom' => $r->agent_nom] : null,
            ]);

        // Fusionner et trier par date
        $timeline = $lotEvents
            ->concat($pivotEvents)
            ->concat($anomalies)
            ->sortBy('date')
            ->values();

        return [
            'lot'      => [
                'id'                 => $lot->id,
                'reference'          => $lot->reference,
                'statut'             => $lot->statut,
                'statut_label'       => $lot->statut_label,
                'date_reception_effective' => $lot->date_reception_effective?->toIso8601String(),
                'commentaire_ambassade'    => $lot->commentaire_ambassade,
            ],
            'resume'   => $this->resumeReception($lot),
            'timeline' => $timeline,
        ];
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function calculerStatutLot(Lot $lot, int $confirmedCount, int $problemCount): string
    {
        $totalInLot   = $lot->passeports()->count();
        $totalTraites = $confirmedCount + $problemCount;

        if ($totalTraites < $totalInLot) {
            // Réception encore en cours (partielle) : on ne change pas le statut du
            // lot. 'en_transit' n'existe pas dans l'enum lots.statut (seul le
            // statut du passeport connaît cet état) — l'écrire ferait échouer la
            // contrainte CHECK en base sur toute réception partielle en un appel.
            return $lot->statut;
        }

        if ($problemCount === 0) {
            return 'recu';
        }

        if ($confirmedCount === 0) {
            return 'anomalie';
        }

        return 'recu_partiel';
    }

    private function recalculerStatutLot(Lot $lot): void
    {
        $total    = $lot->passeports()->count();
        $enAttente = $lot->passeports()->wherePivot('statut_reception', 'en_attente')->count();
        $confirmes = $lot->passeports()->wherePivot('statut_reception', 'confirme')->count();
        $problemes = $total - $enAttente - $confirmes;

        if ($enAttente > 0) {
            return; // réception partielle, on ne change pas le statut
        }

        $newStatut = match (true) {
            $problemes === 0               => 'recu',
            $confirmes === 0               => 'anomalie',
            default                        => 'recu_partiel',
        };

        $lot->update([
            'statut'                   => $newStatut,
            'date_reception_effective' => $lot->date_reception_effective ?? now(),
        ]);

        TrackingEvent::record($lot, 'lot.reception_complete', "Réception terminée : statut {$newStatut}.");
    }

    private function resumeReception(Lot $lot): array
    {
        $rows = DB::table('lot_passeports')
            ->where('lot_id', $lot->id)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE statut_reception = 'en_attente') as en_attente,
                COUNT(*) FILTER (WHERE statut_reception = 'confirme') as confirmes,
                COUNT(*) FILTER (WHERE statut_reception = 'anomalie') as anomalies,
                COUNT(*) FILTER (WHERE statut_reception = 'manquant') as manquants
            ")
            ->first();

        return [
            'total'      => (int) $rows->total,
            'en_attente' => (int) $rows->en_attente,
            'confirmes'  => (int) $rows->confirmes,
            'anomalies'  => (int) $rows->anomalies,
            'manquants'  => (int) $rows->manquants,
        ];
    }

    private function labelConfirmation(string $statut, string $numero): string
    {
        return match ($statut) {
            'confirme' => "Passeport {$numero} réceptionné.",
            'anomalie' => "Anomalie signalée pour le passeport {$numero}.",
            'manquant' => "Passeport {$numero} signalé manquant.",
            default    => "Passeport {$numero} : {$statut}.",
        };
    }
}
