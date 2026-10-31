<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientCategory extends Model
{
    protected $table = 'patient_categories';

    public $timestamps = false;

    protected $fillable = [
        'patient_category_name',
    ];
}
