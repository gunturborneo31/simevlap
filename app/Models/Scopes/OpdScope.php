<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OpdScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $user = auth()->user();
            if (!$user->hasRole('superadmin')) {
                $builder->where(function ($q) use ($user, $model) {
                    $q->whereNull($model->getTable() . '.opd_id')
                      ->orWhere($model->getTable() . '.opd_id', $user->opd_id);
                });
            }
        }
    }
}
