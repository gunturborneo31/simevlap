<?php

namespace App\Models;

use App\Models\Scopes\OpdScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;

#[ScopedBy([OpdScope::class])]
class Indikator extends Model
{
    protected $table = 'indikator';

    protected $fillable = [
        'opd_id', 'document_type', 'jenis_indikator', 'uraian',
        'satuan', 'jenis', 'sifat', 'keterangan',
    ];

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function program(): MorphedByMany
    {
        return $this->morphedByMany(Program::class, 'indicatorable', 'indikatorables')
                    ->withPivot(['target', 'realisasi', 'tahun', 'triwulan', 'catatan'])
                    ->withTimestamps();
    }

    public function kegiatan(): MorphedByMany
    {
        return $this->morphedByMany(Kegiatan::class, 'indicatorable', 'indikatorables')
                    ->withPivot(['target', 'realisasi', 'tahun', 'triwulan', 'catatan'])
                    ->withTimestamps();
    }

    public function subKegiatan(): MorphedByMany
    {
        return $this->morphedByMany(SubKegiatan::class, 'indicatorable', 'indikatorables')
                    ->withPivot(['target', 'realisasi', 'tahun', 'triwulan', 'catatan'])
                    ->withTimestamps();
    }

    public function hitungCapaian(float $target, float $realisasi): float
    {
        if ($target == 0) return 0;

        return match ($this->sifat) {
            'maximize' => ($realisasi / $target) * 100,
            'minimize' => ($target / $realisasi) * 100,
            'stabilize' => 100 - (abs(($realisasi - $target) / $target) * 100),
            default => 0,
        };
    }
}
