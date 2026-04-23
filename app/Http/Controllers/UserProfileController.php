<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()?->load('role');

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($this->serializeUser($user));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'fullName' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:2000000'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);

        if (array_key_exists('fullName', $data) && !array_key_exists('name', $data)) {
            $data['name'] = $data['fullName'];
        }
        unset($data['fullName']);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated',
            'user' => $this->serializeUser($user->fresh('role')),
        ]);
    }

    protected function serializeUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'fullName' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'role' => $user->role?->nama_role,
            'fakultas_id' => $user->fakultas_id,
            'prodi_id' => $user->prodi_id,
        ];
    }
}
