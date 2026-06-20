<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeProgramEvidence extends Model
{
    protected $table = 'resume_program_evidences';

    protected $fillable = [
        'resume_program_annotation_id',
        'sub_kegiatan_kode',
        'sub_kegiatan_nama',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(ResumeProgramAnnotation::class, 'resume_program_annotation_id');
    }
}
