<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    protected $table = 'diseases';

    public $timestamps = false;

    protected $fillable = [
        'disease_name',
    ];
}
