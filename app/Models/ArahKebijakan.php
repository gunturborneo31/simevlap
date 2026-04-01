<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArahKebijakan extends Model
{
    protected $table = 'arah_kebijakan';

    protected $fillable = ['strategi_id', 'kode', 'uraian'];

    public function strategi(): BelongsTo
    {
        return $this->belongsTo(Strategi::class);
    }
}
