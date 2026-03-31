<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $roleName = $user->role->nama_role ?? '';
        $roleSlug = $this->slugify($roleName);
        $allowed = collect($roles)->map(fn ($r) => $this->slugify($r))->all();

        if (!empty($allowed) && !in_array($roleSlug, $allowed, true)) {
            return response()->json(['message' => 'Forbidden: role not allowed'], 403);
        }

        // Set scope info for downstream controllers
        if (in_array($roleSlug, ['admin_fakultas', 'admin_prodi'], true)) {
            $request->attributes->set('scope_fakultas_id', $user->fakultas_id);
        }

        if ($roleSlug === 'admin_prodi') {
            $request->attributes->set('scope_prodi_id', $user->prodi_id);
        }

        return $next($request);
    }

    protected function slugify(string $value): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim($value)));
    }
}
