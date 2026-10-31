<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbdmCareContext extends Model
{
    protected $table = 'abdm_care_contexts';

    public $timestamps = false;

    protected $fillable = [
        'opd_id',
        'ipd_id',
        'dcd_id',
        'source_type',
        'source_id',
        'patient_id',
        'abha_address',
        'patient_reference_number',
        'care_context_reference_number',
        'care_context_display',
        'hi_type',
        'request_id',
        'status',
        'raw_response',
        'created_on',
        'user_id',
    ];
}
