<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Strategi extends Model
{
    protected $table = 'strategi';

    protected $fillable = ['sasaran_id', 'kode', 'uraian'];

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(Sasaran::class);
    }

    public function arahKebijakan(): HasMany
    {
        return $this->hasMany(ArahKebijakan::class);
    }
}
