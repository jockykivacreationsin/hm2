<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BedType extends Model
{
    protected $table = 'bed_types';

    public $timestamps = false;

    protected $fillable = [
        'bed_type',
        'image',
    ];
}
