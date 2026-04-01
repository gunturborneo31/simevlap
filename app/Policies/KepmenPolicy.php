<?php

namespace App\Policies;

use App\Models\Kepmen;
use App\Models\User;

class KepmenPolicy
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

    public function view(User $user, Kepmen $kepmen): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Kepmen $kepmen): bool
    {
        return false;
    }

    public function delete(User $user, Kepmen $kepmen): bool
    {
        return false;
    }
}
