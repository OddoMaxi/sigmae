<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pays\StorePaysRequest;
use App\Models\AuditLog;
use App\Models\Pays;
use Illuminate\Http\Request;

class PaysController extends Controller
{
    public function index(Request $request)
    {
        $pays = Pays::query()
            ->when($request->active, fn($q) => $q->active())
            ->when($request->search, fn($q) => $q->where('nom', 'ilike', '%' . $request->search . '%')
                ->orWhere('code_iso', 'ilike', '%' . $request->search . '%'))
            ->orderBy('nom')
            ->get();

        return response()->json($pays);
    }

    public function store(StorePaysRequest $request)
    {
        $this->authorize('create', Pays::class);

        $pays = Pays::create($request->validated());
        AuditLog::record('pays.create', 'Pays', $pays->id, null, $pays->toArray());

        return response()->json($pays, 201);
    }

    public function show(Pays $pays)
    {
        return response()->json($pays->load('ambassades'));
    }

    public function update(StorePaysRequest $request, Pays $pays)
    {
        $this->authorize('update', $pays);

        $old = $pays->toArray();
        $pays->update($request->validated());
        AuditLog::record('pays.update', 'Pays', $pays->id, $old, $pays->fresh()->toArray());

        return response()->json($pays);
    }

    public function destroy(Pays $pays)
    {
        $this->authorize('delete', $pays);

        if ($pays->ambassades()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer un pays lié à des ambassades.'], 422);
        }

        AuditLog::record('pays.delete', 'Pays', $pays->id, $pays->toArray());
        $pays->delete();

        return response()->json(null, 204);
    }
}
