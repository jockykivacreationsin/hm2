<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineRoute extends Model
{
    protected $table = 'medicine_routes';

    public $timestamps = false;

    protected $fillable = [
        'route_name',
        'snomed_code',
        'snomed_display',
        'sort_order',
        'is_active',
        'created_on',
    ];
}
