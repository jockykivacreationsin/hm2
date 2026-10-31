<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalCondition extends Model
{
    protected $table = 'medical_conditions';

    public $timestamps = false;

    protected $fillable = [
        'condition_name',
        'snomed_code',
        'snomed_display',
        'category',
        'sort_order',
        'is_active',
        'created_on',
    ];
}
