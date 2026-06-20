<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidangUrusan extends Model
{
    protected $table = 'bidang_urusans';
    protected $fillable = [
        'urusan_id',
        'kode',
        'nama',
    ];

    public function urusan()
    {
        return $this->belongsTo(Urusan::class);
    }
}
