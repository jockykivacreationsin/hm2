<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbdmLinkToken extends Model
{
    protected $table = 'abdm_link_tokens';

    public $timestamps = false;

    protected $fillable = [
        'patient_id', 'abha_address', 'abha_number', 'patient_name', 'gender',
        'year_of_birth', 'link_token', 'request_id', 'issued_at', 'expires_at',
        'status', 'raw_response', 'created_on', 'user_id',
    ];

    protected $hidden = ['link_token', 'raw_response'];
}
