<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opd extends Model
{
    protected $table = 'opds';

    protected $fillable = ['kode', 'nama', 'singkatan', 'kepala_opd', 'nip_kepala', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

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
