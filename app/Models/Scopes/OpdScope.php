<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OpdScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();

        // Superadmin/admin and guest-resume users must be able to read cross-OPD data.
        if ($user->hasAnyRole(['superadmin', 'admin', 'tamu'])) {
            return;
        }

        $opdId = (int) ($user->opd_id ?? 0);
        if ($opdId <= 0) {
            return;
        }

        $builder->where(function ($q) use ($opdId, $model) {
            $q->whereNull($model->getTable() . '.opd_id')
              ->orWhere($model->getTable() . '.opd_id', $opdId);
        });
    }
}
