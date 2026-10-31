<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbdmHealthInfoPush extends Model
{
    protected $table = 'abdm_health_info_pushes';

    public $timestamps = false;

    protected $fillable = [
        'opd_id',
        'ipd_id',
        'dcd_id',
        'source_type',
        'source_id',
        'consent_id',
        'transaction_id',
        'care_context_reference_number',
        'hi_type',
        'push_http_code',
        'push_succeeded',
        'notify_http_code',
        'notify_succeeded',
        'error_message',
        'notify_error_message',
        'created_on',
    ];
}
