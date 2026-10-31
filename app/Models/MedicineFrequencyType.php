<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineFrequencyType extends Model
{
    protected $table = 'medicine_frequency_types';

    public $timestamps = false;

    protected $fillable = [
        'frequency_type',
        'period_value',
        'period_unit',
        'display_text',
        'sort_order',
        'is_active',
        'created_on',
    ];
}
