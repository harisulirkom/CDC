<?php

namespace App\Policies;

use App\Models\Questionnaire;
use App\Models\User;

class QuestionnairePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Questionnaire $questionnaire): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Questionnaire $questionnaire): bool
    {
        return $this->isAdmin($user);
    }

    protected function isAdmin(User $user): bool
    {
        $roleName = $user->role->nama_role ?? $user->role ?? '';
        $roleSlug = str_replace(['-', ' '], '_', strtolower(trim((string) $roleName)));
        return in_array($roleSlug, ['super_admin', 'admin_universitas', 'admin_fakultas', 'admin_prodi', 'admin'], true);
    }
}
