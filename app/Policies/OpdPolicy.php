<?php

namespace App\Policies;

use App\Models\Opd;
use App\Models\User;

class OpdPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->hasRole('superadmin')) return true;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Opd $opd): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Opd $opd): bool
    {
        return false;
    }

    public function delete(User $user, Opd $opd): bool
    {
        return false;
    }
}
