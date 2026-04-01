<?php

namespace App\Models;

use App\Models\Scopes\OpdScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([OpdScope::class])]
class Visi extends Model
{
    protected $table = 'visi';

    protected $fillable = ['opd_id', 'document_type', 'kode', 'uraian', 'tahun_awal', 'tahun_akhir'];

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function misi(): HasMany
    {
        return $this->hasMany(Misi::class);
    }
}
