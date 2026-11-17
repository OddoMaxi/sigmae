<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Anomalie\StoreAnomalieRequest;
use App\Http\Requests\Anomalie\UpdateAnomalieRequest;
use App\Models\Anomalie;
use App\Models\AuditLog;
use App\Models\Passeport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnomalieController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Anomalie::class);

        $user = $request->user();

        $anomalies = Anomalie::with(['passeport', 'lot.ambassade', 'signalePar'])
            ->when($user->isScopedToAmbassade(), fn ($q) => $q->where(function ($q2) use ($user) {
                // Anomalie liée à un lot de son ambassade...
                $q2->whereHas('lot', fn ($q3) => $q3->where('ambassade_id', $user->ambassade_id))
                    // ...ou à un passeport pas encore en lot mais destiné à son ambassade
                    ->orWhere(fn ($q3) => $q3->whereNull('lot_id')->whereHas(
                        'passeport', fn ($q4) => $q4->where('ambassade_destination_id', $user->ambassade_id)
                    ));
            }))
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->ambassade_id, fn($q) => $q->whereHas(
                'lot', fn($q) => $q->where('ambassade_id', $request->ambassade_id)
            ))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($anomalies);
    }

    public function store(StoreAnomalieRequest $request)
    {
        $data               = $request->validated();
        $data['signale_by'] = auth()->id();
        $anomalie = Anomalie::create($data);
        AuditLog::record('anomalie.create', 'Anomalie', $anomalie->id, null, $anomalie->toArray());

        return response()->json($anomalie->load(['passeport', 'lot', 'signalePar']), 201);
    }

    public function show(Anomalie $anomalie)
    {
        $this->authorize('view', $anomalie);

        return response()->json($anomalie->load(['passeport', 'lot.ambassade', 'signalePar', 'resoluPar']));
    }

    public function update(UpdateAnomalieRequest $request, Anomalie $anomalie)
    {
        $this->authorize('update', $anomalie);

        $old  = $anomalie->toArray();
        $data = $request->validated();

        if (isset($data['statut']) && $data['statut'] === 'resolu') {
            $data['resolu_by'] = auth()->id();
            $data['resolu_at'] = now();
        }

        DB::transaction(function () use ($anomalie, $data) {
            $anomalie->update($data);

            if (($data['statut'] ?? null) === 'resolu') {
                $this->remettreEnStock($anomalie);
            }
        });

        AuditLog::record('anomalie.update', 'Anomalie', $anomalie->id, $old, $anomalie->fresh()->toArray());

        return response()->json($anomalie->fresh()->load(['passeport', 'lot', 'signalePar', 'resoluPar']));
    }

    public function resoudre(Request $request, Anomalie $anomalie)
    {
        $this->authorize('resoudre', $anomalie);
        $request->validate(['note' => 'nullable|string']);

        $old = $anomalie->toArray();

        DB::transaction(function () use ($anomalie) {
            $anomalie->update([
                'statut'    => 'resolu',
                'resolu_by' => auth()->id(),
                'resolu_at' => now(),
            ]);

            $this->remettreEnStock($anomalie);
        });

        AuditLog::record('anomalie.resoudre', 'Anomalie', $anomalie->id, $old, $anomalie->fresh()->toArray());

        return response()->json($anomalie->fresh());
    }

    /**
     * Une fois l'anomalie résolue, le passeport concerné (s'il est toujours
     * au statut ANOMALIE) retourne en stock central pour être réintégré dans
     * un nouveau lot, plutôt que de rester bloqué indéfiniment.
     */
    private function remettreEnStock(Anomalie $anomalie): void
    {
        $passeport = $anomalie->passeport;

        if ($passeport && $passeport->estAnomalie()) {
            $passeport->transitionTo(
                Passeport::STATUT_EN_STOCK,
                "Anomalie résolue — passeport remis en stock central."
            );
            $passeport->update(['lot_id' => null]);
        }
    }
}
