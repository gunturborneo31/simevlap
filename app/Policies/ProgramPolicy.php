<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Program $program): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;
        if ($user->hasRole('user')) return true;
        return false;
    }

    public function update(User $user, Program $program): bool
    {
        if ($user->hasRole('superadmin')) return true;
        if ($user->hasRole('user') && $program->opd_id !== null && $program->opd_id == $user->opd_id) return true;
        return false;
    }

    public function delete(User $user, Program $program): bool
    {
        return $this->update($user, $program);
    }
}
