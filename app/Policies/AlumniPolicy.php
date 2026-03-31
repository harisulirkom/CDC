<?php

namespace App\Policies;

use App\Models\Alumni;
use App\Models\User;

class AlumniPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Alumni $alumni): bool
    {
        return $this->canAccessUnit($user, $alumni->fakultas, $alumni->prodi);
    }

    public function update(User $user, Alumni $alumni): bool
    {
        return $this->view($user, $alumni);
    }

    public function delete(User $user, Alumni $alumni): bool
    {
        return $this->view($user, $alumni);
    }

    protected function isAdmin(User $user): bool
    {
        $role = $this->roleSlug($user);
        return in_array($role, ['super_admin', 'admin_universitas', 'admin_fakultas', 'admin_prodi'], true);
    }

    protected function canAccessUnit(User $user, ?string $fakultas, ?string $prodi): bool
    {
        $role = $this->roleSlug($user);
        if (! $this->isAdmin($user)) {
            return false;
        }

        if (in_array($role, ['super_admin', 'admin_universitas'], true)) {
            return true;
        }

        if ($role === 'admin_fakultas') {
            return $this->normalizeUnit($user->fakultas) === $this->normalizeUnit($fakultas)
                || $this->normalizeUnit($user->fakultas) === $this->normalizeUnit($this->stripFakultasPrefix($fakultas));
        }

        if ($role === 'admin_prodi') {
            return $this->normalizeUnit($user->prodi) === $this->normalizeUnit($prodi);
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
