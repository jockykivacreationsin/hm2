<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WellnessObservationType extends Model
{
    protected $table = 'wellness_observation_types';

    public $timestamps = false;

    protected $fillable = [
        'type_code',
        'category',
        'display_name',
        'code_system',
        'code',
        'code_display',
        'panel_code',
        'panel_display',
        'value_type',
        'ucum_code',
        'unit_display',
        'sort_order',
        'is_active',
        'created_on',
    ];

    public function observations()
    {
        return $this->hasMany(WellnessObservation::class, 'type_id');
    }
}
