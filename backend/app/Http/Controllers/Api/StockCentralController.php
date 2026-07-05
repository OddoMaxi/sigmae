<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambassade;
use App\Models\Passeport;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockCentralController extends Controller
{
    private const STATUTS_STOCK = ['en_stock', 'en_lot', 'expedie', 'anomalie'];
    private const SEUIL_ALERTE  = 100;

    public function __construct(private ExportService $exportService) {}

    /**
     * GET /api/stock-central
     *
     * Liste paginée des passeports du stock central (EN_STOCK, EN_LOT, EXPÉDIÉ, ANOMALIE).
     * Filtres : ambassade_id, pays_id, statut, date_from, date_to, search
     */
    public function index(Request $request)
    {
        $request->validate([
            'statut'       => 'nullable|string|in:' . implode(',', self::STATUTS_STOCK),
            'ambassade_id' => 'nullable|integer|exists:ambassades,id',
            'pays_id'      => 'nullable|integer|exists:pays,id',
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'search'       => 'nullable|string|max:100',
            'per_page'     => 'nullable|integer|min:5|max:200',
            'sort_by'      => 'nullable|string|in:numero,nom_titulaire,statut,date_reception_mae,created_at',
            'sort_dir'     => 'nullable|string|in:asc,desc',
        ]);

        $user = $request->user();

        $query = Passeport::with([
                'paysDestination:id,nom,code_iso',
                'ambassadeDestination:id,nom,code,ville',
                'lot:id,reference,statut',
            ])
            ->whereIn('statut', self::STATUTS_STOCK);

        // Embassy-scoped restriction
        if ($user->isScopedToAmbassade()) {
            $query->where('ambassade_destination_id', $user->ambassade_id);
        }

        // Filters
        $query
            ->when($request->statut,       fn ($q) => $q->where('statut', $request->statut))
            ->when($request->ambassade_id, fn ($q) => $q->where('ambassade_destination_id', $request->ambassade_id))
            ->when($request->pays_id,      fn ($q) => $q->where('pays_destination_id', $request->pays_id))
            ->when($request->date_from,    fn ($q) => $q->whereDate('date_reception_mae', '>=', $request->date_from))
            ->when($request->date_to,      fn ($q) => $q->whereDate('date_reception_mae', '<=', $request->date_to))
            ->when($request->search,       fn ($q) => $q->search($request->search));

        $sortBy  = $request->sort_by  ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $passeports = $query->paginate((int) ($request->per_page ?? 50));

        return response()->json($passeports);
    }

    /**
     * GET /api/stock-central/inventaire
     *
     * Résumé de l'inventaire groupé par ambassade et statut.
     * Chaque ligne = ambassade + counts par statut + total.
     */
    public function inventaire(Request $request)
    {
        $user = $request->user();

        $baseQuery = DB::table('passeports as p')
            ->join('ambassades as a', 'a.id', '=', 'p.ambassade_destination_id')
            ->join('pays as py', 'py.id', '=', 'a.pays_id')
            ->whereIn('p.statut', self::STATUTS_STOCK)
            ->select(
                'a.id as ambassade_id',
                'a.nom as ambassade_nom',
                'a.code as ambassade_code',
                'a.ville',
                'py.nom as pays_nom',
                'py.code_iso as pays_code',
                DB::raw("COUNT(*) FILTER (WHERE p.statut = 'en_stock') AS en_stock"),
                DB::raw("COUNT(*) FILTER (WHERE p.statut = 'en_lot') AS en_lot"),
                DB::raw("COUNT(*) FILTER (WHERE p.statut = 'expedie') AS expedie"),
                DB::raw("COUNT(*) FILTER (WHERE p.statut = 'anomalie') AS anomalie"),
                DB::raw('COUNT(*) AS total')
            )
            ->groupBy('a.id', 'a.nom', 'a.code', 'a.ville', 'py.nom', 'py.code_iso')
            ->orderByDesc('total');

        if ($user->isScopedToAmbassade()) {
            $baseQuery->where('a.id', $user->ambassade_id);
        }

        $rows = $baseQuery->get()->map(fn ($r) => [
            'ambassade' => [
                'id'   => $r->ambassade_id,
                'nom'  => $r->ambassade_nom,
                'code' => $r->ambassade_code,
                'ville'=> $r->ville,
                'pays' => ['nom' => $r->pays_nom, 'code_iso' => $r->pays_code],
            ],
            'en_stock'  => (int) $r->en_stock,
            'en_lot'    => (int) $r->en_lot,
            'expedie'   => (int) $r->expedie,
            'anomalie'  => (int) $r->anomalie,
            'total'     => (int) $r->total,
            'alerte'    => (int) $r->en_stock >= self::SEUIL_ALERTE,
        ]);

        $totaux = [
            'en_stock' => $rows->sum('en_stock'),
            'en_lot'   => $rows->sum('en_lot'),
            'expedie'  => $rows->sum('expedie'),
            'anomalie' => $rows->sum('anomalie'),
            'total'    => $rows->sum('total'),
        ];

        return response()->json([
            'ambassades' => $rows,
            'totaux'     => $totaux,
        ]);
    }

    /**
     * GET /api/stock-central/alertes
     *
     * Ambassades ayant plus de {SEUIL_ALERTE} passeports EN_STOCK
     * (disponibles mais pas encore intégrés dans un lot).
     */
    public function alertes(Request $request)
    {
        $user = $request->user();

        $query = DB::table('passeports as p')
            ->join('ambassades as a', 'a.id', '=', 'p.ambassade_destination_id')
            ->join('pays as py', 'py.id', '=', 'a.pays_id')
            ->where('p.statut', 'en_stock')
            ->select(
                'a.id as ambassade_id',
                'a.nom as ambassade_nom',
                'a.code as ambassade_code',
                'a.ville',
                'py.nom as pays_nom',
                'py.code_iso as pays_code',
                DB::raw('COUNT(*) AS total_en_stock'),
                DB::raw('MIN(p.created_at) AS plus_ancien')
            )
            ->groupBy('a.id', 'a.nom', 'a.code', 'a.ville', 'py.nom', 'py.code_iso')
            ->having(DB::raw('COUNT(*)'), '>=', self::SEUIL_ALERTE)
            ->orderByDesc('total_en_stock');

        if ($user->isScopedToAmbassade()) {
            $query->where('a.id', $user->ambassade_id);
        }

        $alertes = $query->get()->map(fn ($r) => [
            'ambassade' => [
                'id'   => $r->ambassade_id,
                'nom'  => $r->ambassade_nom,
                'code' => $r->ambassade_code,
                'ville'=> $r->ville,
                'pays' => ['nom' => $r->pays_nom, 'code_iso' => $r->pays_code],
            ],
            'total_en_stock' => (int) $r->total_en_stock,
            'plus_ancien'    => $r->plus_ancien,
            'seuil'          => self::SEUIL_ALERTE,
            'niveau'         => (int) $r->total_en_stock >= self::SEUIL_ALERTE * 2 ? 'critique' : 'attention',
        ]);

        return response()->json([
            'seuil'   => self::SEUIL_ALERTE,
            'count'   => $alertes->count(),
            'alertes' => $alertes,
        ]);
    }

    /**
     * GET /api/stock-central/export
     *
     * Export Excel des passeports du stock central avec filtres.
     * Paramètres : statut, ambassade_id, pays_id, date_from, date_to, search, format (xlsx)
     */
    public function export(Request $request)
    {
        $request->validate([
            'statut'       => 'nullable|string|in:' . implode(',', self::STATUTS_STOCK),
            'ambassade_id' => 'nullable|integer|exists:ambassades,id',
            'pays_id'      => 'nullable|integer|exists:pays,id',
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'search'       => 'nullable|string|max:100',
            'format'       => 'nullable|string|in:xlsx,pdf',
        ]);

        $user = $request->user();

        $filters = array_filter([
            'statuts'      => self::STATUTS_STOCK,
            'statut'       => $request->statut,
            'ambassade_id' => $user->isScopedToAmbassade()
                                ? $user->ambassade_id
                                : $request->ambassade_id,
            'pays_id'      => $request->pays_id,
            'date_from'    => $request->date_from,
            'date_to'      => $request->date_to,
            'search'       => $request->search,
            'mode'         => 'stock',
        ], fn ($v) => $v !== null && $v !== '');

        return $this->exportService->exportPasseports($filters, $request->format ?? 'xlsx');
    }
}
