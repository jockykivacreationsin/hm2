<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbdmCareContextAttempt extends Model
{
    protected $table = 'abdm_care_context_attempts';

    public $timestamps = false;

    protected $fillable = [
        'opd_id',
        'ipd_id',
        'dcd_id',
        'source_type',
        'source_id',
        'abha_address',
        'hi_type',
        'success',
        'is_duplicate_error',
        'error_message',
        'created_on',
        'user_id',
    ];
}
