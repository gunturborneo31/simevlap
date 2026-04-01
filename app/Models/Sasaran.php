<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Sasaran extends Model
{
    protected $table = 'sasaran';

    protected $fillable = ['tujuan_id', 'kode', 'uraian'];

    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(Tujuan::class);
    }

    public function strategi(): HasMany
    {
        return $this->hasMany(Strategi::class);
    }

    public function indikator(): MorphToMany
    {
        return $this->morphToMany(Indikator::class, 'indicatorable', 'indikatorables')
                    ->withPivot(['target', 'realisasi', 'tahun', 'triwulan', 'catatan'])
                    ->withTimestamps();
    }
}
