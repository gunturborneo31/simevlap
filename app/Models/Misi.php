<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Misi extends Model
{
    protected $table = 'misi';

    protected $fillable = ['visi_id', 'kode', 'uraian'];

    public function visi(): BelongsTo
    {
        return $this->belongsTo(Visi::class);
    }

    public function tujuan(): HasMany
    {
        return $this->hasMany(Tujuan::class);
    }
}
