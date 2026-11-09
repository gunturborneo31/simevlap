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

    /**
     * Bersihkan teks "undefined" sebelum disimpan. File referensi hasil ekstraksi (referensi/apbd, rkpd, renstra)
     * memuat nilai seperti "undefined undefined" atau "CCundefined" yang ikut terimpor lewat seeder/perintah import.
     */
    protected static function booted(): void
    {
        static::saving(function (IndikatorAnggaran $indikator) {
            if ($indikator->isDirty('target_indikator')) {
                $indikator->target_indikator = self::stripUndefined($indikator->target_indikator);
            }
            if ($indikator->isDirty('satuan')) {
                // Kolom satuan wajib terisi; nilai kosong disimpan sebagai "-" seperti data yang sudah ada.
                $indikator->satuan = self::stripUndefined($indikator->satuan) ?? '-';
            }
            if ($indikator->isDirty('target_tahunan') && is_array($indikator->target_tahunan)) {
                $indikator->target_tahunan = array_map(
                    fn ($value) => is_string($value) ? self::stripUndefined($value) : $value,
                    $indikator->target_tahunan
                );
            }
        });
    }

    private static function stripUndefined(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(preg_replace('/\s+/', ' ', str_ireplace('undefined', '', (string) $value)) ?? '');

        return $text === '' ? null : $text;
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenAnggaran::class, 'komponen_anggaran_id');
    }
}