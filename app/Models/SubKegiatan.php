<?php

namespace App\Models;

use App\Models\Scopes\OpdScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[ScopedBy([OpdScope::class])]
class SubKegiatan extends Model
{
    protected $table = 'sub_kegiatan';

    protected $fillable = [
        'kegiatan_id', 'opd_id', 'kepmen_id', 'document_type', 'kode_rek', 'nama_rincian', 'pagu',
        'tahun_awal', 'tahun_akhir', 'target_t1', 'target_t2', 'target_t3', 'target_t4', 'target_t5',
        'target_tahunan', 'tahun', 'catatan_evaluasi',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function kepmen(): BelongsTo
    {
        return $this->belongsTo(Kepmen::class);
    }

    public function realisasi(): MorphMany
    {
        return $this->morphMany(Realisasi::class, 'realisaseable');
    }

    public function indikator(): MorphToMany
    {
        return $this->morphToMany(Indikator::class, 'indicatorable', 'indikatorables')
                    ->withPivot(['target', 'realisasi', 'tahun', 'triwulan', 'catatan'])
                    ->withTimestamps();
    }
}
