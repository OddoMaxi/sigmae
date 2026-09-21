<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceptionMAE\BatchReceptionRequest;
use App\Http\Requests\ReceptionMAE\ScannerRequest;
use App\Http\Requests\ReceptionMAE\ValiderReceptionRequest;
use App\Models\Passeport;
use App\Models\TrackingEvent;
use App\Services\ReceptionMAEService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReceptionMAEController extends Controller
{
    public function __construct(private ReceptionMAEService $service) {}

    // ── Scanner ───────────────────────────────────────────────────────────────

    /**
     * POST /api/reception-mae/scanner
     *
     * Recherche un passeport par numéro ou référence de demande.
     * Retourne le statut courant, le diagnostic et l'action recommandée.
     *
     * Utilisé pour : scan de QR code, saisie manuelle, validation avant réception.
     */
    public function scanner(ScannerRequest $request)
    {
        $result = $this->service->scanner($request->identifiant);

        $statusCode = $result['trouve'] ? 200 : 404;

        return response()->json($result, $statusCode);
    }

    // ── Validation unitaire ───────────────────────────────────────────────────

    /**
     * POST /api/reception-mae/valider
     *
     * Réceptionne un passeport (IMPRIMÉ → REÇU_MAE).
     * Option aller_en_stock=true pour passer directement en EN_STOCK.
     *
     * Body :
     * {
     *   "identifiant": "PA1234567",
     *   "date_reception_mae": "2025-01-15",  // optionnel, défaut = aujourd'hui
     *   "notes": "...",
     *   "aller_en_stock": false               // optionnel, défaut = false
     * }
     */
    public function valider(ValiderReceptionRequest $request)
    {
        $data      = $request->validated();
        $diagnostic = $this->service->scanner($data['identifiant']);

        if (! $diagnostic['trouve']) {
            return response()->json([
                'code'    => 'non_trouve',
                'message' => $diagnostic['message'],
            ], 404);
        }

        $passeport = Passeport::find($diagnostic['passeport']['id']);

        // Dossier enrôlé mais numéro pas encore assigné :
        // l'agent tient le passeport physique → assigner le numéro ici, puis réceptionner.
        if ($passeport->estEnrolee()) {
            $numero = trim(strtoupper($request->input('numero', '')));
            if (! $numero) {
                return response()->json([
                    'code'    => 'numero_requis',
                    'message' => 'Ce dossier est enrôlé mais le numéro de passeport n\'a pas encore été assigné. Saisissez le numéro imprimé sur le passeport physique.',
                    'statut'  => $passeport->statut,
                ], 422);
            }
            if (! preg_match('/^[A-Z0-9]{6,15}$/', $numero)) {
                return response()->json([
                    'code'    => 'numero_invalide',
                    'message' => 'Le numéro de passeport doit contenir entre 6 et 15 lettres/chiffres, sans espace ni caractère spécial.',
                    'statut'  => $passeport->statut,
                ], 422);
            }
            if (Passeport::where('numero', $numero)->exists()) {
                return response()->json([
                    'code'    => 'numero_doublon',
                    'message' => "Ce numéro de passeport ({$numero}) existe déjà dans le système.",
                    'statut'  => $passeport->statut,
                ], 422);
            }
            $passeport->update([
                'numero'          => $numero,
                'statut'          => Passeport::STATUT_IMPRIME,
                'date_impression' => $data['date_reception_mae'] ?? now()->toDateString(),
            ]);
            TrackingEvent::record(
                $passeport, 'statut.imprime',
                "Numéro {$numero} assigné lors de la réception MAE.",
                ['numero' => $numero, 'assigned_by' => $request->user()->id]
            );
        }

        // Doublon ou statut incompatible
        if (! $passeport->estImprime()) {
            return response()->json([
                'code'         => 'deja_traite',
                'message'      => "Ce passeport ne peut pas être réceptionné : statut actuel «{$passeport->statut_label}».",
                'statut'       => $passeport->statut,
                'statut_label' => $passeport->statut_label,
                'passeport_id' => $passeport->id,
            ], 422);
        }

        $allerEnStock = $request->boolean('aller_en_stock', false);

        $processed = $allerEnStock
            ? $this->service->receptionComplete($passeport, $data)
            : $this->service->validerReception($passeport, $data);

        return response()->json([
            'code'         => 'succes',
            'message'      => $allerEnStock
                ? 'Passeport réceptionné et mis en stock.'
                : 'Passeport réceptionné au MAE (REÇU_MAE).',
            'passeport'    => $this->formatPasseport($processed),
            'action_suivante' => $allerEnStock ? null : 'mettre_en_stock',
        ]);
    }

    /**
     * POST /api/reception-mae/mettre-en-stock
     *
     * Passe un passeport de REÇU_MAE à EN_STOCK après vérification interne.
     *
     * Body : { "identifiant": "PA1234567", "notes": "..." }
     */
    public function mettreEnStock(Request $request)
    {
        $data = $request->validate([
            'identifiant' => 'required|string|max:50',
            'notes'       => 'nullable|string|max:500',
        ]);

        $diagnostic = $this->service->scanner($data['identifiant']);

        if (! $diagnostic['trouve']) {
            return response()->json(['code' => 'non_trouve', 'message' => $diagnostic['message']], 404);
        }

        $passeport = Passeport::find($diagnostic['passeport']['id']);

        if (! $passeport->estRecuMAE()) {
            return response()->json([
                'code'         => 'transition_invalide',
                'message'      => "Mise en stock impossible : statut actuel «{$passeport->statut_label}».",
                'statut'       => $passeport->statut,
                'statut_label' => $passeport->statut_label,
            ], 422);
        }

        $processed = $this->service->mettreEnStock($passeport, $data['notes'] ?? null);

        return response()->json([
            'code'      => 'succes',
            'message'   => 'Passeport mis en stock.',
            'passeport' => $this->formatPasseport($processed),
        ]);
    }

    // ── Traitement en lot ─────────────────────────────────────────────────────

    /**
     * POST /api/reception-mae/batch
     *
     * Réceptionne plusieurs passeports en une seule requête.
     * Idéal pour l'import d'un bordereau d'arrivée imprimerie.
     *
     * Body :
     * {
     *   "date_reception_mae": "2025-01-15",
     *   "aller_en_stock": true,
     *   "passeports": [
     *     {"identifiant": "PA1234567"},
     *     {"identifiant": "REF-2025-001", "notes": "Légère rayure"},
     *     ...
     *   ]
     * }
     */
    public function batch(BatchReceptionRequest $request)
    {
        $data           = $request->validated();
        $dateDefault    = $data['date_reception_mae'] ?? today()->toDateString();
        $allerEnStock   = (bool) ($data['aller_en_stock'] ?? true);

        // Normaliser : chaque item doit avoir un champ 'identifiant' (déjà validé)
        $items = collect($data['passeports'])->map(fn ($item) => [
            'numero'             => $item['identifiant'],
            'date_reception_mae' => $item['date_reception_mae'] ?? $dateDefault,
            'notes'              => $item['notes'] ?? null,
        ])->all();

        $result = $this->service->batchReception($items, $dateDefault, $allerEnStock);

        $httpCode = $result['summary']['traites'] > 0 ? 200 : 422;

        return response()->json($result, $httpCode);
    }

    // ── Passeports reçus aujourd'hui ──────────────────────────────────────────

    /**
     * GET /api/reception-mae/aujourd-hui
     *
     * Liste des passeports dont la date_reception_mae est aujourd'hui.
     * Inclut l'agent ayant effectué la réception.
     *
     * Paramètres :
     * - date   : date au format YYYY-MM-DD (défaut = aujourd'hui)
     * - statut : filtrer par statut (recu_mae, en_stock)
     * - search : recherche texte
     * - per_page : (défaut 50)
     */
    public function aujourdHui(Request $request)
    {
        $date = $request->date ?? today()->toDateString();

        $query = Passeport::with([
                'paysDestination:id,nom,code_iso',
                'ambassadeDestination:id,nom,code,ville',
            ])
            ->whereDate('date_reception_mae', $date)
            ->when($request->statut, fn ($q) => $q->where('statut', $request->statut))
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->orderByDesc('received_at');

        $passeports = $query->paginate((int) ($request->per_page ?? 50));

        // Enrichir avec l'agent de réception (depuis tracking_events)
        $ids = $passeports->getCollection()->pluck('id');
        $agentsParPasseport = \DB::table('tracking_events as te')
            ->join('users as u', 'u.id', '=', 'te.triggered_by')
            ->whereIn('te.trackable_id', $ids)
            ->where('te.trackable_type', 'App\\Models\\Passeport')
            ->where('te.event', 'statut.recu_mae')
            ->select('te.trackable_id as passeport_id', 'u.id as agent_id', 'u.name as agent_nom', 'te.created_at')
            ->get()
            ->keyBy('passeport_id');

        $passeports->getCollection()->transform(function ($p) use ($agentsParPasseport) {
            $event = $agentsParPasseport->get($p->id);
            $p->agent_reception = $event ? [
                'id'    => $event->agent_id,
                'nom'   => $event->agent_nom,
                'heure' => \Carbon\Carbon::parse($event->created_at)->format('H:i'),
            ] : null;
            $p->statut_label = $p->statut_label;
            $p->statut_color = $p->statut_color;
            return $p;
        });

        return response()->json($passeports);
    }

    // ── Statistiques journalières ─────────────────────────────────────────────

    /**
     * GET /api/reception-mae/statistiques
     *
     * Stats journalières de réception MAE.
     *
     * Paramètres :
     * - date : date au format YYYY-MM-DD (défaut = aujourd'hui)
     *
     * Réponse :
     * {
     *   "date": "2025-01-15",
     *   "recus_total": 42,
     *   "par_statut": {"recu_mae": 4, "en_stock": 38},
     *   "distribution_horaire": [{"heure": 8, "label": "08:00", "total": 5}, ...],
     *   "agents": [{"name": "...", "role": "...", "total": 15}],
     *   "evolution": {"hier": 35, "delta": 7, "pct": 20.0},
     *   "en_attente_imprime": 12
     * }
     */
    public function statistiques(Request $request)
    {
        $date = $request->date ?? today()->toDateString();

        // Validation rapide de la date
        try {
            \Carbon\Carbon::parse($date);
        } catch (\Throwable) {
            return response()->json(['message' => 'Date invalide.'], 422);
        }

        $stats = $this->service->statsJour($date);

        return response()->json($stats);
    }

    // ── Récapitulatif ─────────────────────────────────────────────────────────

    /**
     * GET /api/reception-mae/recap
     *
     * Récapitulatif rapide pour le dashboard MAE :
     * - total en attente (IMPRIMÉ)
     * - total en REÇU_MAE
     * - total en stock
     * - tendance 7 jours
     */
    public function recap()
    {
        $enAttente = Passeport::where('statut', Passeport::STATUT_IMPRIME)->count();
        $recuMAE   = Passeport::where('statut', Passeport::STATUT_RECU_MAE)->count();
        $enStock   = Passeport::where('statut', Passeport::STATUT_EN_STOCK)->count();

        // Réceptions des 7 derniers jours
        $tendance7j = \DB::table('passeports')
            ->whereNotNull('date_reception_mae')
            ->whereDate('date_reception_mae', '>=', now()->subDays(6)->toDateString())
            ->selectRaw("date_reception_mae::date AS jour, COUNT(*) AS total")
            ->groupByRaw("date_reception_mae::date")
            ->orderBy('jour')
            ->get()
            ->keyBy('jour')
            ->map(fn ($r) => (int) $r->total);

        // Remplir les jours sans réceptions avec 0
        $courbe = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $courbe[] = ['date' => $d, 'total' => $tendance7j->get($d, 0)];
        }

        return response()->json([
            'en_attente_imprime'  => $enAttente,
            'en_attente_stock_mae'=> $recuMAE,
            'en_stock'            => $enStock,
            'recus_aujourd_hui'   => $tendance7j->get(today()->toDateString(), 0),
            'tendance_7j'         => $courbe,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function formatPasseport(Passeport $p): array
    {
        return [
            'id'                => $p->id,
            'numero'            => $p->numero,
            'reference_demande' => $p->reference_demande,
            'nom_complet'       => $p->nom_complet,
            'statut'            => $p->statut,
            'statut_label'      => $p->statut_label,
            'statut_color'      => $p->statut_color,
            'date_impression'   => $p->date_impression?->toDateString(),
            'date_reception_mae'=> $p->date_reception_mae?->toDateString(),
            'received_at'       => $p->received_at?->toIso8601String(),
            'pays_destination'  => $p->paysDestination?->only(['id', 'nom', 'code_iso']),
            'ambassade_destination' => $p->ambassadeDestination?->only(['id', 'nom', 'code', 'ville']),
        ];
    }
}
