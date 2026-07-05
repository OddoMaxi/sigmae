<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('ambassade')
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->ambassade_id, fn($q) => $q->where('ambassade_id', $request->ambassade_id))
            ->when($request->search, fn($q) => $q->where(fn($q2) =>
                $q2->where('name', 'ilike', '%' . $request->search . '%')
                   ->orWhere('email', 'ilike', '%' . $request->search . '%')
            ))
            ->orderBy('name')
            ->paginate(20);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $data             = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $user             = User::create($data);

        AuditLog::record('user.create', 'User', $user->id, null, $user->makeHidden('password')->toArray());

        return response()->json($user->load('ambassade'), 201);
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return response()->json($user->load('ambassade'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $old  = $user->makeHidden('password')->toArray();
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        AuditLog::record('user.update', 'User', $user->id, $old, $user->fresh()->makeHidden('password')->toArray());

        return response()->json($user->load('ambassade'));
    }

    public function toggleStatus(User $user)
    {
        $this->authorize('toggleStatus', $user);

        $old = $user->toArray();
        $user->update(['is_active' => ! $user->is_active]);
        AuditLog::record('user.toggle_status', 'User', $user->id, $old, $user->fresh()->toArray());

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        AuditLog::record('user.delete', 'User', $user->id, $user->makeHidden('password')->toArray());
        $user->delete();

        return response()->json(null, 204);
    }
}
