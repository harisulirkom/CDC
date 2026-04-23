<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::with('role')->paginate(50);

        $transformed = $users->getCollection()->map(function (User $user) {
            return $this->transform($user);
        });

        return response()->json([
            'data' => $transformed,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:2000000'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'role' => ['nullable', 'string', 'max:100'],
            'fakultas_id' => ['nullable', 'integer'],
            'prodi_id' => ['nullable', 'integer'],
            'fakultas' => ['nullable', 'string', 'max:255'],
            'prodi' => ['nullable', 'string', 'max:255'],
            'faculty' => ['nullable', 'string', 'max:255'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['fakultas'] = $data['fakultas'] ?? $data['faculty'] ?? null;
        unset($data['faculty']);
        $this->applyRoleMapping($data);

        $user = User::create($data);

        AuditLogger::log('user.created', 'user', $user->id, [
            'role_id' => $user->role_id,
        ]);

        return response()->json($this->transform($user), 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:2000000'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'string', 'min:8', 'max:128'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'role' => ['nullable', 'string', 'max:100'],
            'fakultas_id' => ['nullable', 'integer'],
            'prodi_id' => ['nullable', 'integer'],
            'fakultas' => ['nullable', 'string', 'max:255'],
            'prodi' => ['nullable', 'string', 'max:255'],
            'faculty' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $data['fakultas'] = $data['fakultas'] ?? $data['faculty'] ?? null;
        unset($data['faculty']);
        $this->applyRoleMapping($data);

        $user->update($data);

        AuditLogger::log('user.updated', 'user', $user->id, [
            'role_id' => $user->role_id,
        ]);

        return response()->json($this->transform($user->fresh('role')));
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        AuditLogger::log('user.deleted', 'user', $user->id);

        return response()->noContent();
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);
        $user->password = Hash::make($data['password']);
        $user->save();

        AuditLogger::log('user.password_reset', 'user', $user->id);

        return response()->json(['message' => 'Password reset']);
    }

    protected function transform(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'fullName' => $user->name,
            'email' => $user->email,
            'username' => $user->username ?: ($user->email ? explode('@', $user->email)[0] : null),
            'avatar' => $user->avatar,
            'role' => $user->role->nama_role ?? null,
            'role_id' => $user->role_id,
            'fakultas_id' => $user->fakultas_id,
            'prodi_id' => $user->prodi_id,
            'faculty' => $user->fakultas,
            'prodi' => $user->prodi,
            'status' => 'Aktif',
            'createdAt' => $user->created_at?->toIso8601String(),
            'lastLogin' => $user->updated_at?->toIso8601String(),
        ];
    }

    protected function applyRoleMapping(array &$data): void
    {
        if (!empty($data['role'])) {
            $role = Role::firstOrCreate(['nama_role' => $data['role']]);
            $data['role_id'] = $role->id;
        }
        unset($data['role']);
    }
}
