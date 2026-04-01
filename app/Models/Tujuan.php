<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tujuan extends Model
{
    protected $table = 'tujuan';

    protected $fillable = ['misi_id', 'kode', 'uraian'];

    public function misi(): BelongsTo
    {
        return $this->belongsTo(Misi::class);
    }

    public function sasaran(): HasMany
    {
        return $this->hasMany(Sasaran::class);
    }
}
