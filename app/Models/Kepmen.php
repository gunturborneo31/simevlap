<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kepmen extends Model
{
    protected $table = 'kepmen';

    protected $fillable = ['kode', 'nama', 'tahun', 'keterangan'];

    public function program(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class);
    }

    public function subKegiatan(): HasMany
    {
        return $this->hasMany(SubKegiatan::class);
    }
}
