<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ambassade\StoreAmbassadeRequest;
use App\Http\Requests\Ambassade\UpdateAmbassadeRequest;
use App\Models\Ambassade;
use App\Models\AuditLog;
use App\Models\Pays;
use Illuminate\Http\Request;

class AmbassadeController extends Controller
{
    public function index(Request $request)
    {
        $ambassades = Ambassade::query()
            ->when($request->active, fn($q) => $q->active())
            ->when($request->search, fn($q) => $q->where('nom', 'ilike', '%' . $request->search . '%')
                ->orWhere('pays', 'ilike', '%' . $request->search . '%'))
            ->orderBy('pays')->orderBy('ville')
            ->get();

        return response()->json($ambassades);
    }

    public function store(StoreAmbassadeRequest $request)
    {
        $this->authorize('create', Ambassade::class);

        $data = $request->validated();
        $data['pays'] = Pays::findOrFail($data['pays_id'])->nom;

        $ambassade = Ambassade::create($data);
        AuditLog::record('ambassade.create', 'Ambassade', $ambassade->id, null, $ambassade->toArray());

        return response()->json($ambassade, 201);
    }

    public function show(Ambassade $ambassade)
    {
        return response()->json($ambassade->load(['lots' => fn($q) => $q->latest()->limit(5)]));
    }

    public function update(UpdateAmbassadeRequest $request, Ambassade $ambassade)
    {
        $this->authorize('update', $ambassade);

        $old  = $ambassade->toArray();
        $data = $request->validated();
        if (isset($data['pays_id'])) {
            $data['pays'] = Pays::findOrFail($data['pays_id'])->nom;
        }
        $ambassade->update($data);
        AuditLog::record('ambassade.update', 'Ambassade', $ambassade->id, $old, $ambassade->fresh()->toArray());

        return response()->json($ambassade);
    }
}
