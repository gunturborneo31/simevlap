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
}
