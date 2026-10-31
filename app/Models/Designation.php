<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $table = 'designations';

    public $timestamps = false;

    protected $fillable = [
        'designation',
    ];
}
