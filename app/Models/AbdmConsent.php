<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbdmConsent extends Model
{
    protected $table = 'abdm_consents';

    public $timestamps = false;

    protected $fillable = [
        'consent_id',
        'status',
        'patient_abha_address',
        'hip_id',
        'purpose_code',
        'hi_types',
        'care_contexts',
        'date_range_from',
        'date_range_to',
        'data_erase_at',
        'acknowledged',
        'acknowledgement_error',
        'raw_notification',
        'created_on',
        'updated_on',
    ];
}
