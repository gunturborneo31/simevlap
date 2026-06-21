<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Realisasi extends Model
{
    protected $table = 'realisasi';

    protected $fillable = [
        'opd_id', 'document_type', 'tahun', 'tahun_ke', 'triwulan',
        'realisasi_fisik', 'realisasi_keuangan', 'sisa_anggaran', 'catatan',
        'is_verified', 'catatan_verifikator', 'verified_by', 'verified_at',
        'input_by',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function realisaseable(): MorphTo
    {
        return $this->morphTo();
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
