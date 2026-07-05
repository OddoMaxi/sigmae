<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /** Liste tous les rôles avec leur nombre de permissions */
    public function index()
    {
        $roles = Role::withCount('permissions')
                     ->with('permissions')
                     ->orderBy('id')
                     ->get()
                     ->map(fn ($r) => [
                         'id'               => $r->id,
                         'name'             => $r->name,
                         'display_name'     => $r->display_name,
                         'description'      => $r->description,
                         'is_system'        => $r->is_system,
                         'permissions_count'=> $r->permissions_count,
                         'permissions_by_module' => $r->permissions
                             ->groupBy('module')
                             ->map(fn ($p) => $p->pluck('action')),
                     ]);

        return response()->json($roles);
    }

    /** Détail d'un rôle avec toutes ses permissions */
    public function show(Role $role)
    {
        $role->load('permissions');
        $allPermissions = Permission::orderBy('module')->orderBy('action')->get();

        return response()->json([
            'role'            => $role,
            'permissions'     => $role->permissions,
            'all_permissions' => $allPermissions->groupBy('module'),
        ]);
    }

    /** Met à jour les permissions d'un rôle (sync complet) */
    public function updatePermissions(Request $request, Role $role)
    {
        if ($role->is_system && ! $request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Seul le super administrateur peut modifier les rôles système.',
            ], 403);
        }

        $data = $request->validate([
            'permission_ids'   => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $old = $role->permissions->pluck('id')->all();
        $role->permissions()->sync($data['permission_ids']);

        AuditLog::record('role.update_permissions', 'Role', $role->id, ['permission_ids' => $old], $data);

        $role->load('permissions');

        return response()->json([
            'message' => 'Permissions mises à jour.',
            'role'    => $role,
        ]);
    }

    /** Crée un rôle personnalisé (non-système) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:50|unique:roles,name|regex:/^[a-z_]+$/',
            'display_name' => 'required|string|max:100',
            'description'  => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create([
            'name'         => $data['name'],
            'display_name' => $data['display_name'],
            'description'  => $data['description'] ?? null,
            'is_system'    => false,
        ]);

        if (! empty($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        AuditLog::record('role.create', 'Role', $role->id, null, $role->toArray());

        return response()->json($role->load('permissions'), 201);
    }

    /** Supprime un rôle non-système sans utilisateurs liés */
    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return response()->json(['message' => 'Les rôles système ne peuvent pas être supprimés.'], 422);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'Ce rôle est assigné à des utilisateurs.'], 422);
        }

        AuditLog::record('role.delete', 'Role', $role->id, $role->toArray());
        $role->delete();

        return response()->json(null, 204);
    }
}
