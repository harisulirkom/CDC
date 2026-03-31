<?php

namespace App\Policies;

use App\Models\Response;
use App\Models\User;

class ResponsePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Response $response): bool
    {
        $response->loadMissing('alumni');
        return $this->canAccessAlumni($user, $response->alumni);
    }

    public function delete(User $user, Response $response): bool
    {
        return $this->view($user, $response);
    }

    protected function isAdmin(User $user): bool
    {
        $role = $this->roleSlug($user);
        return in_array($role, ['super_admin', 'admin_universitas', 'admin_fakultas', 'admin_prodi', 'admin'], true);
    }

    protected function canAccessAlumni(User $user, $alumni): bool
    {
        $role = $this->roleSlug($user);

        // Respon non-alumni (umum/pengguna) tidak memiliki relasi alumni.
        // Untuk kasus ini, izinkan role admin yang memang punya akses modul response.
        if (! $alumni) {
            return in_array($role, ['super_admin', 'admin_universitas', 'admin_fakultas', 'admin_prodi', 'admin'], true);
        }

        if (in_array($role, ['super_admin', 'admin_universitas'], true)) {
            return true;
        }

        if ($role === 'admin_fakultas') {
            return $this->normalizeUnit($user->fakultas) === $this->normalizeUnit($alumni->fakultas)
                || $this->normalizeUnit($user->fakultas) === $this->normalizeUnit($this->stripFakultasPrefix($alumni->fakultas));
        }

        if ($role === 'admin_prodi') {
            return $this->normalizeUnit($user->prodi) === $this->normalizeUnit($alumni->prodi);
        }

        return false;
    }

    protected function roleSlug(User $user): string
    {
        $roleName = $user->role->nama_role ?? $user->role ?? '';
        return str_replace(['-', ' '], '_', strtolower(trim((string) $roleName)));
    }

    protected function stripFakultasPrefix(?string $value): string
    {
        if (! $value) {
            return '';
        }
        return preg_replace('/^fakultas\\s+/i', '', $value) ?? $value;
    }

    protected function normalizeUnit(?string $value): string
    {
        $value = trim((string) ($value ?? ''));
        $value = preg_replace('/\\s+/', ' ', $value);
        return strtolower($value);
    }
}
