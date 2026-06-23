<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumeProgramAnnotation extends Model
{
    protected $table = 'resume_program_annotations';

    protected $fillable = [
        'view',
        'table_name',
        'basis',
        'tahun',
        'entitas',
        'program_kode',
        'program_nama',
        'faktor_penghambat',
        'faktor_pendorong',
        'faktor_tindak_lanjut',
    ];

    public function evidences(): HasMany
    {
        return $this->hasMany(ResumeProgramEvidence::class, 'resume_program_annotation_id');
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            $val = isset($model->faktor_penghambat) ? trim((string) $model->faktor_penghambat) : '';
            if ($val === '') {
                $model->faktor_penghambat = 'tidak ada hambatan';
            }
            // normalize empty strings for other fields to null
            $model->faktor_pendorong = isset($model->faktor_pendorong) ? trim((string) $model->faktor_pendorong) ?: null : null;
            $model->faktor_tindak_lanjut = isset($model->faktor_tindak_lanjut) ? trim((string) $model->faktor_tindak_lanjut) ?: null : null;
        });
    }
}
