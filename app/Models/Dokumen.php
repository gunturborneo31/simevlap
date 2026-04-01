<?php

namespace App\Models;

use App\Models\Scopes\OpdScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([OpdScope::class])]
class Dokumen extends Model
{
    protected $table = 'dokumen';

    protected $fillable = ['opd_id', 'document_type', 'judul', 'file_path', 'tahun', 'uploaded_by'];

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
