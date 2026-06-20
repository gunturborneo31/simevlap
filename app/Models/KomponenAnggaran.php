<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenAnggaran extends Model
{
    protected $table = 'komponen_anggaran';
    protected $fillable = [
        'opd_id', 'parent_id', 'kode', 'kode_program', 'jenis', 'sub_unit', 'urusan', 'bidang_urusan', 'nama_komponen', 'pagu', 'pagu_tahunan', 'tahun', 'document_type'
    ];

    protected $casts = [
        'pagu_tahunan' => 'array',
    ];

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function urusanRef(): BelongsTo
    {
        return $this->belongsTo(Urusan::class, 'urusan', 'kode');
    }

    public function bidangUrusanRef(): BelongsTo
    {
        return $this->belongsTo(BidangUrusan::class, 'bidang_urusan', 'kode');
    }

    public function parent()
    {
        return $this->belongsTo(KomponenAnggaran::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(KomponenAnggaran::class, 'parent_id');
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(IndikatorAnggaran::class, 'komponen_anggaran_id');
    }
}
