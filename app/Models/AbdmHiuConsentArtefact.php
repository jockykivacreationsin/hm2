<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * abdm_hiu_consent_artefacts: id, consent_id, consent_request_id, status, patient_abha_address,
 *   hip_id, hi_types, care_contexts, date_range_from, date_range_to, data_erase_at,
 *   raw_artefact, fetched_at, created_on
 * (the table has no updated_on column — fetched_at is the "last refreshed" marker).
 */
class AbdmHiuConsentArtefact extends Model
{
    protected $table = 'abdm_hiu_consent_artefacts';
    public $timestamps = false;
    protected $fillable = [
        'consent_id', 'consent_request_id', 'status', 'patient_abha_address', 'hip_id',
        'hi_types', 'care_contexts', 'date_range_from', 'date_range_to',
        'data_erase_at', 'raw_artefact', 'fetched_at', 'created_on',
    ];

    /** The consent request this artefact was issued for (matched on ABDM's consentRequestId). */
    public function consentRequest()
    {
        return $this->belongsTo(AbdmHiuConsentRequest::class, 'consent_request_id', 'consent_request_id');
    }
}
