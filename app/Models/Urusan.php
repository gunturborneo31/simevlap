<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Urusan extends Model
{
    protected $table = 'urusans';
    protected $fillable = [
        'kode',
        'nama',
    ];

    public function bidangUrusans()
    {
        return $this->hasMany(BidangUrusan::class);
    }
}
