<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function view(User $user, Question $question): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Question $question): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->isAdmin($user);
    }

    protected function isAdmin(User $user): bool
    {
        $roleName = $user->role->nama_role ?? $user->role ?? '';
        $roleSlug = str_replace(['-', ' '], '_', strtolower(trim((string) $roleName)));
        return in_array($roleSlug, ['super_admin', 'admin_universitas', 'admin_fakultas', 'admin_prodi'], true);
    }
}
