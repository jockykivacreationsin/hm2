<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineDoseUnit extends Model
{
    protected $table = 'medicine_dose_units';

    public $timestamps = false;

    protected $fillable = [
        'unit_name',
        'ucum_code',
        'ucum_display',
        'sort_order',
        'is_active',
        'created_on',
    ];
}
