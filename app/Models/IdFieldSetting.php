<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdFieldSetting extends Model
{
    protected $table = 'id_field_settings';

    public $timestamps = false;

    protected $fillable = [
        'doctor_department_id',
        'nurse_department_id',
        'pathology_department_id',
        'opd_department_id',
        'radiology_department_id',
        'endoscopy_department_id',
        'dialysis_department_id',
        'procedure_department_id',
        'pharmacy_department_id',
        'doctor_designation_id',
        'nurse_designation_id',
    ];
}
