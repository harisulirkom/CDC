<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $user = User::with('role')->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            AuditLogger::log('auth.login_failed', 'user', $user?->id, [
                'email_hash' => hash('sha256', strtolower($credentials['email'])),
            ]);
            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        AuditLogger::log('auth.login', 'user', $user->id);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->nama_role,
                'fakultas_id' => $user->fakultas_id,
                'prodi_id' => $user->prodi_id,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()?->load('role');

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->nama_role,
            'fakultas_id' => $user->fakultas_id,
            'prodi_id' => $user->prodi_id,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        AuditLogger::log('auth.logout', 'user', $user?->id);

        return response()->json(['message' => 'Logged out']);
    }
}
