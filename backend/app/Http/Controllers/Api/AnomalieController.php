<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Anomalie\StoreAnomalieRequest;
use App\Http\Requests\Anomalie\UpdateAnomalieRequest;
use App\Models\Anomalie;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AnomalieController extends Controller
{
    public function index(Request $request)
    {
        $anomalies = Anomalie::with(['passeport', 'lot.ambassade', 'signalePar'])
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

        $anomalie->update($data);
        AuditLog::record('anomalie.update', 'Anomalie', $anomalie->id, $old, $anomalie->fresh()->toArray());

        return response()->json($anomalie->load(['passeport', 'lot', 'signalePar', 'resoluPar']));
    }

    public function resoudre(Request $request, Anomalie $anomalie)
    {
        $this->authorize('resoudre', $anomalie);
        $request->validate(['note' => 'nullable|string']);

        $old = $anomalie->toArray();
        $anomalie->update([
            'statut'    => 'resolu',
            'resolu_by' => auth()->id(),
            'resolu_at' => now(),
        ]);

        AuditLog::record('anomalie.resoudre', 'Anomalie', $anomalie->id, $old, $anomalie->fresh()->toArray());

        return response()->json($anomalie);
    }
}
