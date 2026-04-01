<?php

namespace App\Policies;

use App\Models\Realisasi;
use App\Models\User;

class RealisasiPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Realisasi $realisasi): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['superadmin', 'user']);
    }

    public function update(User $user, Realisasi $realisasi): bool
    {
        if ($user->hasRole('superadmin')) return true;
        if ($user->hasRole('user') && $realisasi->opd_id == $user->opd_id) return true;
        return false;
    }

    public function delete(User $user, Realisasi $realisasi): bool
    {
        return $this->update($user, $realisasi);
    }

    public function addCatatan(User $user, Realisasi $realisasi): bool
    {
        return $user->hasAnyRole(['superadmin', 'user', 'evaluator']);
    }
}
