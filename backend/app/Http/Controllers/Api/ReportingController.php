<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anomalie;
use App\Models\Lot;
use App\Models\Passeport;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportingController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function dashboard()
    {
        return response()->json([
            'stock'         => $this->stockService->getStockStats(),
            'lots'          => [
                'brouillon'  => Lot::where('statut', 'brouillon')->count(),
                'en_transit' => Lot::whereIn('statut', ['valide', 'expedie'])->count(),
                'recus'      => Lot::whereIn('statut', ['recu', 'recu_partiel'])->count(),
            ],
            'anomalies'     => [
                'ouvertes'   => Anomalie::where('statut', 'ouvert')->count(),
                'en_cours'   => Anomalie::where('statut', 'en_traitement')->count(),
                'resolues'   => Anomalie::where('statut', 'resolu')->count(),
            ],
            'recent_lots'   => Lot::with('ambassade')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'reference', 'statut', 'ambassade_id', 'created_at']),
        ]);
    }

    public function expeditions(Request $request)
    {
        $query = Lot::with(['ambassade', 'transporteur'])
            ->when($request->date_from, fn($q) => $q->whereDate('date_expedition', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('date_expedition', '<=', $request->date_to))
            ->when($request->ambassade_id, fn($q) => $q->where('ambassade_id', $request->ambassade_id))
            ->withCount('passeports');

        $byAmbassade = $query->clone()
            ->select('ambassade_id', DB::raw('count(*) as total_lots'), DB::raw('sum(1) as total'))
            ->groupBy('ambassade_id')
            ->with('ambassade:id,nom,pays')
            ->get();

        return response()->json([
            'lots'        => $query->orderByDesc('date_expedition')->paginate(20),
            'by_ambassade'=> $byAmbassade,
        ]);
    }

    public function receptions(Request $request)
    {
        $ambassades = DB::table('lots')
            ->join('ambassades', 'lots.ambassade_id', '=', 'ambassades.id')
            ->select(
                'ambassades.id',
                'ambassades.nom',
                'ambassades.pays',
                DB::raw("count(*) filter (where lots.statut = 'expedie') as en_attente"),
                DB::raw("count(*) filter (where lots.statut = 'recu') as recus"),
                DB::raw("count(*) filter (where lots.statut = 'recu_partiel') as partiels"),
                DB::raw("avg(extract(epoch from (lots.date_reception_effective - lots.date_expedition))/86400) as delai_moyen_jours")
            )
            ->when($request->ambassade_id, fn($q) => $q->where('ambassades.id', $request->ambassade_id))
            ->groupBy('ambassades.id', 'ambassades.nom', 'ambassades.pays')
            ->get();

        return response()->json($ambassades);
    }

    public function anomalies(Request $request)
    {
        $stats = Anomalie::select('type', 'statut', DB::raw('count(*) as total'))
            ->when($request->ambassade_id, fn($q) => $q->whereHas(
                'lot', fn($q) => $q->where('ambassade_id', $request->ambassade_id)
            ))
            ->groupBy('type', 'statut')
            ->get();

        return response()->json($stats);
    }

    public function performance()
    {
        $perf = DB::table('lots')
            ->whereNotNull('date_reception_effective')
            ->whereNotNull('date_expedition')
            ->select(
                DB::raw("date_trunc('month', date_expedition) as mois"),
                DB::raw("count(*) as total_lots"),
                DB::raw("avg(extract(epoch from (date_reception_effective - date_expedition::timestamp))/86400) as delai_moyen"),
                DB::raw("min(extract(epoch from (date_reception_effective - date_expedition::timestamp))/86400) as delai_min"),
                DB::raw("max(extract(epoch from (date_reception_effective - date_expedition::timestamp))/86400) as delai_max")
            )
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        return response()->json($perf);
    }
}
