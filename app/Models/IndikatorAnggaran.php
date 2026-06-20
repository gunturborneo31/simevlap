<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorAnggaran extends Model
{
    protected $table = 'indikator_anggaran';
    protected $fillable = [
        'komponen_anggaran_id', 'nama_indikator', 'sifat_indikator', 'target_indikator', 'target_tahunan', 'satuan'
    ];

    protected $casts = [
        'target_tahunan' => 'array',
    ];

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenAnggaran::class, 'komponen_anggaran_id');
    }
}