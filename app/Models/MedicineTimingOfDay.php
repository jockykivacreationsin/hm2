<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineTimingOfDay extends Model
{
    protected $table = 'medicine_timing_of_day';

    public $timestamps = false;

    protected $fillable = [
        'timing_name',
        'timing_code',
        'timing_display',
        'sort_order',
        'is_active',
        'created_on',
    ];
}
