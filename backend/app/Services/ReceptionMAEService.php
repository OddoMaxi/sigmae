<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Passeport;
use App\Models\TrackingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceptionMAEService
{
    // ── Recherche ─────────────────────────────────────────────────────────────

    /**
     * Recherche un passeport par son numéro ou sa référence de demande.
     * Retourne un tableau diagnostic complet pour le frontend.
     */
    public function scanner(string $identifiant): array
    {
        $identifiant = trim($identifiant);

        // Détection du type : les numéros de passeport commencent souvent par lettres
        // On essaie les deux colonnes pour ne pas forcer l'agent à distinguer
        $passeport = Passeport::where('numero', $identifiant)
            ->orWhere('reference_demande', $identifiant)
            ->with(['paysDestination:id,nom,code_iso', 'ambassadeDestination:id,nom,code,ville'])
            ->first();

        if (! $passeport) {
            return [
                'trouve'       => false,
                'code'         => 'non_trouve',
                'message'      => "Aucun passeport trouvé pour «{$identifiant}».",
                'identifiant'  => $identifiant,
                'passeport'    => null,
                'action_requise' => null,
            ];
        }

        return $this->buildDiagnostic($passeport);
    }

    /**
     * Recherche par ID passeport (pour les workflows de réception directe).
     */
    public function scannerParId(int $id): array
    {
        $passeport = Passeport::with(['paysDestination:id,nom,code_iso', 'ambassadeDestination:id,nom,code,ville'])
            ->find($id);

        return $passeport ? $this->buildDiagnostic($passeport) : [
            'trouve' => false, 'code' => 'non_trouve', 'message' => 'Passeport introuvable.',
            'identifiant' => $id, 'passeport' => null, 'action_requise' => null,
        ];
    }

    // ── Transitions unitaires ─────────────────────────────────────────────────

    /**
     * IMPRIMÉ → REÇU_MAE
     * Enregistre la date de réception physique au MAE.
     */
    public function validerReception(Passeport $passeport, array $data): Passeport
    {
        if (! $passeport->estImprime()) {
            throw ValidationException::withMessages([
                'statut' => "Impossible de réceptionner : statut actuel «{$passeport->statut_label}».",
            ]);
        }

        $dateReception = $data['date_reception_mae'] ?? today()->toDateString();
        $notes         = $data['notes'] ?? "Réceptionné au MAE le {$dateReception}.";

        DB::transaction(function () use ($passeport, $dateReception, $notes) {
            $passeport->update([
                'date_reception_mae' => $dateReception,
                'received_at'        => now(),
            ]);

            $passeport->transitionTo(
                Passeport::STATUT_RECU_MAE,
                $notes,
                ['date_reception_mae' => $dateReception, 'agent_id' => auth()->id()]
            );

            AuditLog::record('passeport.recu_mae', 'Passeport', $passeport->id,
                null, ['date_reception_mae' => $dateReception]
            );
        });

        return $passeport->fresh(['paysDestination', 'ambassadeDestination']);
    }

    /**
     * REÇU_MAE → EN_STOCK
     * Valide le traitement interne et intègre au stock.
     */
    public function mettreEnStock(Passeport $passeport, ?string $notes = null): Passeport
    {
        if (! $passeport->estRecuMAE()) {
            throw ValidationException::withMessages([
                'statut' => "Impossible de mettre en stock : statut actuel «{$passeport->statut_label}».",
            ]);
        }

        DB::transaction(function () use ($passeport, $notes) {
            $passeport->transitionTo(
                Passeport::STATUT_EN_STOCK,
                $notes ?? 'Mis en stock après vérification MAE.'
            );

            AuditLog::record('passeport.en_stock', 'Passeport', $passeport->id);
        });

        return $passeport->fresh(['paysDestination', 'ambassadeDestination']);
    }

    /**
     * IMPRIMÉ → EN_STOCK en une étape (workflow rapide MAE).
     * Passe par REÇU_MAE de manière transparente.
     */
    public function receptionComplete(Passeport $passeport, array $data): Passeport
    {
        if (! $passeport->estImprime()) {
            throw ValidationException::withMessages([
                'statut' => "Impossible de réceptionner : statut actuel «{$passeport->statut_label}».",
            ]);
        }

        $dateReception = $data['date_reception_mae'] ?? today()->toDateString();
        $notes         = $data['notes'] ?? "Réceptionné et mis en stock le {$dateReception}.";

        DB::transaction(function () use ($passeport, $dateReception, $notes) {
            // Étape 1 : enregistrement réception physique
            $passeport->update([
                'date_reception_mae' => $dateReception,
                'received_at'        => now(),
            ]);
            $passeport->transitionTo(
                Passeport::STATUT_RECU_MAE,
                "Réceptionné au MAE le {$dateReception}.",
                ['date_reception_mae' => $dateReception]
            );

            // Étape 2 : mise en stock immédiate
            $passeport->transitionTo(Passeport::STATUT_EN_STOCK, $notes);

            AuditLog::record('passeport.reception_complete', 'Passeport', $passeport->id,
                null, ['date_reception_mae' => $dateReception]
            );
        });

        return $passeport->fresh(['paysDestination', 'ambassadeDestination']);
    }

    // ── Traitement en lot ─────────────────────────────────────────────────────

    /**
     * Réceptionne plusieurs passeports en une seule requête.
     *
     * @param  array  $items       [{numero|reference_demande, notes?, date_reception_mae?}]
     * @param  string $dateDefault Date de réception par défaut
     * @param  bool   $allerEnStock  true = IMPRIMÉ→EN_STOCK, false = IMPRIMÉ→REÇU_MAE
     * @return array  {summary, traites, deja_traites, non_trouves, erreurs}
     */
    public function batchReception(array $items, string $dateDefault, bool $allerEnStock): array
    {
        $traites       = [];
        $dejaTraites   = [];
        $nonTrouves    = [];
        $erreurs       = [];

        // Dédupliquer en mémoire (évite de traiter deux fois le même passeport dans le batch)
        $seen = [];

        foreach ($items as $item) {
            $identifiant = trim($item['numero'] ?? $item['reference_demande'] ?? '');

            if (empty($identifiant)) {
                continue;
            }

            // Doublon dans le batch
            if (isset($seen[$identifiant])) {
                $erreurs[] = ['identifiant' => $identifiant, 'raison' => 'Doublon dans la liste envoyée.'];
                continue;
            }
            $seen[$identifiant] = true;

            // Recherche
            $passeport = Passeport::where('numero', $identifiant)
                ->orWhere('reference_demande', $identifiant)
                ->first();

            if (! $passeport) {
                $nonTrouves[] = $identifiant;
                continue;
            }

            // Déjà traité ?
            if (! $passeport->estImprime()) {
                $dejaTraites[] = [
                    'identifiant'  => $identifiant,
                    'numero'       => $passeport->numero,
                    'statut'       => $passeport->statut,
                    'statut_label' => $passeport->statut_label,
                ];
                continue;
            }

            // Traitement
            try {
                $data = [
                    'date_reception_mae' => $item['date_reception_mae'] ?? $dateDefault,
                    'notes'              => $item['notes'] ?? null,
                ];

                $processed = $allerEnStock
                    ? $this->receptionComplete($passeport, $data)
                    : $this->validerReception($passeport, $data);

                $traites[] = [
                    'numero'       => $processed->numero,
                    'nom_complet'  => $processed->nom_complet,
                    'statut'       => $processed->statut,
                    'statut_label' => $processed->statut_label,
                ];
            } catch (\Throwable $e) {
                $erreurs[] = [
                    'identifiant' => $identifiant,
                    'raison'      => $e->getMessage(),
                ];
            }
        }

        return [
            'summary' => [
                'total'        => count($items),
                'traites'      => count($traites),
                'deja_traites' => count($dejaTraites),
                'non_trouves'  => count($nonTrouves),
                'erreurs'      => count($erreurs),
            ],
            'traites'      => $traites,
            'deja_traites' => $dejaTraites,
            'non_trouves'  => $nonTrouves,
            'erreurs'      => $erreurs,
        ];
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statsJour(\DateTimeInterface|string $date = null): array
    {
        $date      = $date ? \Carbon\Carbon::parse($date)->toDateString() : today()->toDateString();
        $yesterday = \Carbon\Carbon::parse($date)->subDay()->toDateString();

        // Reçus aujourd'hui (date_reception_mae = date)
        $reçusAujourdhui = Passeport::whereDate('date_reception_mae', $date)->count();
        $reçusHier       = Passeport::whereDate('date_reception_mae', $yesterday)->count();

        // Par statut (parmi ceux reçus ce jour)
        $parStatut = Passeport::whereDate('date_reception_mae', $date)
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->all();

        // Répartition horaire (de 0h à 23h)
        $parHeure = DB::table('tracking_events')
            ->whereDate('created_at', $date)
            ->where('event', 'statut.recu_mae')
            ->selectRaw("EXTRACT(HOUR FROM created_at)::int AS heure, COUNT(*) AS total")
            ->groupByRaw("EXTRACT(HOUR FROM created_at)::int")
            ->orderBy('heure')
            ->get()
            ->keyBy('heure')
            ->map(fn ($r) => (int) $r->total);

        // Construire tableau 0-23 avec zéros pour heures sans réception
        $distribution = [];
        for ($h = 0; $h <= 23; $h++) {
            $distribution[] = ['heure' => $h, 'label' => sprintf('%02d:00', $h), 'total' => $parHeure->get($h, 0)];
        }

        // Agents ayant réceptionné aujourd'hui
        $agents = DB::table('tracking_events as te')
            ->join('users as u', 'u.id', '=', 'te.triggered_by')
            ->whereDate('te.created_at', $date)
            ->where('te.event', 'statut.recu_mae')
            ->selectRaw('u.id, u.name, u.role, COUNT(*) AS total')
            ->groupBy('u.id', 'u.name', 'u.role')
            ->orderByDesc('total')
            ->get();

        // En attente de réception (encore IMPRIMÉ)
        $enAttente = Passeport::where('statut', Passeport::STATUT_IMPRIME)->count();

        $evolution = $reçusHier > 0
            ? round((($reçusAujourdhui - $reçusHier) / $reçusHier) * 100, 1)
            : ($reçusAujourdhui > 0 ? 100.0 : 0.0);

        return [
            'date'          => $date,
            'recus_total'   => $reçusAujourdhui,
            'par_statut'    => $parStatut,
            'distribution_horaire' => $distribution,
            'agents'        => $agents,
            'evolution'     => [
                'hier'  => $reçusHier,
                'delta' => $reçusAujourdhui - $reçusHier,
                'pct'   => $evolution,
            ],
            'en_attente_imprime' => $enAttente,
        ];
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function buildDiagnostic(Passeport $passeport): array
    {
        $base = [
            'trouve'      => true,
            'passeport'   => [
                'id'              => $passeport->id,
                'numero'          => $passeport->numero,
                'reference_demande'=> $passeport->reference_demande,
                'nom_complet'     => $passeport->nom_complet,
                'nom_titulaire'   => $passeport->nom_titulaire,
                'prenom_titulaire'=> $passeport->prenom_titulaire,
                'statut'          => $passeport->statut,
                'statut_label'    => $passeport->statut_label,
                'statut_color'    => $passeport->statut_color,
                'date_impression' => $passeport->date_impression?->toDateString(),
                'date_reception_mae' => $passeport->date_reception_mae?->toDateString(),
                'pays_destination'   => $passeport->paysDestination?->only(['id', 'nom', 'code_iso']),
                'ambassade_destination' => $passeport->ambassadeDestination?->only(['id', 'nom', 'code', 'ville']),
            ],
        ];

        return match (true) {
            $passeport->estImprime() => array_merge($base, [
                'code'           => 'pret',
                'message'        => 'Passeport prêt à être réceptionné au MAE.',
                'action_requise' => 'valider_reception',
            ]),
            $passeport->estRecuMAE() => array_merge($base, [
                'code'           => 'recu_mae',
                'message'        => "Déjà réceptionné au MAE (statut : REÇU_MAE). En attente de mise en stock.",
                'action_requise' => 'mettre_en_stock',
            ]),
            $passeport->estEnStock() => array_merge($base, [
                'code'           => 'en_stock',
                'message'        => 'Passeport déjà en stock MAE.',
                'action_requise' => null,
            ]),
            default => array_merge($base, [
                'code'           => 'deja_traite',
                'message'        => "Ce passeport a déjà été traité (statut : {$passeport->statut_label}).",
                'action_requise' => null,
            ]),
        };
    }
}
