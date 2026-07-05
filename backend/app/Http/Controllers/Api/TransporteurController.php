<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transporteur\StoreTransporteurRequest;
use App\Http\Requests\Transporteur\UpdateTransporteurRequest;
use App\Models\AuditLog;
use App\Models\Transporteur;
use Illuminate\Http\Request;

class TransporteurController extends Controller
{
    /**
     * GET /api/transporteurs
     *
     * Paramètres : search, type, actif (true/false), per_page, with_lots_count
     */
    public function index(Request $request)
    {
        $request->validate([
            'type'    => 'nullable|string|in:' . implode(',', Transporteur::TYPES),
            'actif'   => 'nullable|boolean',
            'per_page'=> 'nullable|integer|min:5|max:200',
            'search'  => 'nullable|string|max:100',
        ]);

        $query = Transporteur::query()
            ->when($request->filled('actif'),  fn ($q) => $q->where('is_active', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->type,             fn ($q) => $q->ofType($request->type))
            ->when($request->search,           fn ($q) => $q->search($request->search))
            ->withCount(['lots', 'lots as lots_en_cours_count' => fn ($q) => $q->whereIn('statut', ['valide', 'expedie'])])
            ->orderBy('nom');

        $perPage = (int) ($request->per_page ?? 50);
        $paginate = $request->boolean('paginate', true);

        return response()->json(
            $paginate ? $query->paginate($perPage) : $query->get()
        );
    }

    /**
     * POST /api/transporteurs
     */
    public function store(StoreTransporteurRequest $request)
    {
        $transporteur = Transporteur::create($request->validated());
        AuditLog::record('transporteur.create', 'Transporteur', $transporteur->id, null, $transporteur->toArray());

        return response()->json($this->format($transporteur), 201);
    }

    /**
     * GET /api/transporteurs/{transporteur}
     */
    public function show(Transporteur $transporteur)
    {
        $transporteur->loadCount('lots')
            ->load(['lots' => fn ($q) => $q->latest()->limit(5)->with('ambassade:id,nom,code')]);

        return response()->json($this->format($transporteur));
    }

    /**
     * PUT /api/transporteurs/{transporteur}
     */
    public function update(UpdateTransporteurRequest $request, Transporteur $transporteur)
    {
        $old = $transporteur->toArray();
        $transporteur->update($request->validated());
        AuditLog::record('transporteur.update', 'Transporteur', $transporteur->id, $old, $transporteur->fresh()->toArray());

        return response()->json($this->format($transporteur->fresh()));
    }

    /**
     * PATCH /api/transporteurs/{transporteur}/toggle-status
     *
     * Active ou désactive un transporteur.
     * Un transporteur avec des lots EN_COURS ne peut pas être désactivé.
     */
    public function toggleStatus(Transporteur $transporteur)
    {
        $user = request()->user();

        if (! $user->hasPermission('transporteurs.update')) {
            return response()->json(['message' => 'Permission refusée.'], 403);
        }

        // Empêcher la désactivation si des lots sont en cours
        if ($transporteur->is_active) {
            $lotsEnCours = $transporteur->lots()->whereIn('statut', ['valide', 'expedie'])->count();

            if ($lotsEnCours > 0) {
                return response()->json([
                    'message'       => "Impossible de désactiver ce transporteur : {$lotsEnCours} lot(s) en cours lui sont associés.",
                    'lots_en_cours' => $lotsEnCours,
                ], 422);
            }
        }

        $old = $transporteur->is_active;
        $transporteur->update(['is_active' => ! $transporteur->is_active]);

        AuditLog::record(
            'transporteur.' . ($transporteur->is_active ? 'activate' : 'deactivate'),
            'Transporteur',
            $transporteur->id,
            ['is_active' => $old],
            ['is_active' => $transporteur->is_active]
        );

        return response()->json([
            'message'   => $transporteur->is_active ? 'Transporteur activé.' : 'Transporteur désactivé.',
            'is_active' => $transporteur->is_active,
        ]);
    }

    /**
     * DELETE /api/transporteurs/{transporteur}
     *
     * Supprime un transporteur uniquement s'il n'a jamais été utilisé.
     */
    public function destroy(Transporteur $transporteur)
    {
        $this->authorize('delete', $transporteur);

        if ($transporteur->lots()->exists()) {
            return response()->json([
                'message' => 'Ce transporteur ne peut pas être supprimé : il est lié à des lots existants.',
            ], 422);
        }

        AuditLog::record('transporteur.delete', 'Transporteur', $transporteur->id, $transporteur->toArray(), null);
        $transporteur->delete();

        return response()->json(null, 204);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function format(Transporteur $t): array
    {
        return [
            'id'             => $t->id,
            'nom'            => $t->nom,
            'type'           => $t->type,
            'type_label'     => $t->type_label,
            'contact'        => $t->contact,
            'telephone'      => $t->telephone,
            'email'          => $t->email,
            'adresse'        => $t->adresse,
            'pays_desservis' => $t->pays_desservis ?? [],
            'lien_suivi'     => $t->lien_suivi,
            'is_active'      => $t->is_active,
            'lots_count'     => $t->lots_count ?? null,
            'lots_en_cours_count' => $t->lots_en_cours_count ?? null,
            'lots'           => $t->relationLoaded('lots') ? $t->lots : null,
            'created_at'     => $t->created_at?->toIso8601String(),
            'updated_at'     => $t->updated_at?->toIso8601String(),
        ];
    }
}
