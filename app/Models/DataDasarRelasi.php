<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataDasarRelasi extends Model
{
    protected $table = 'data_dasar_relasi';

    protected $fillable = [
        'child_type',
        'child_id',
        'parent_type',
        'parent_id',
    ];
}
