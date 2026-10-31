<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * abdm_hiu_consent_requests — one row per consent REQUEST this app (as HIU) sent to ABDM.
 *
 * Lifecycle (legacy abdm/hiu_consent.php):
 *   REQUESTED -> on-init sets consent_request_id (or UNKNOWN + rejection_error)
 *             -> notify/on-status sets GRANTED (+ consent_ids[], granted_on) / DENIED / REVOKED / EXPIRED
 *
 * A single request can be granted as several consent ARTEFACTS (one per HIP); their ids are in
 * `consent_ids` and the artefacts themselves in abdm_hiu_consent_artefacts.
 * All timestamps are stored in UTC (legacy used UTC_TIMESTAMP()).
 */
class AbdmHiuConsentRequest extends Model
{
    /** A request in one of these states never moves to another status. */
    public const TERMINAL_STATUSES = ['DENIED', 'REVOKED', 'EXPIRED'];

    /** A fresh "request records" attempt is allowed again after these. */
    public const RETRYABLE_STATUSES = ['DENIED', 'EXPIRED', 'REVOKED', 'UNKNOWN'];

    protected $table = 'abdm_hiu_consent_requests';

    public $timestamps = false;

    protected $fillable = [
        'request_id', 'consent_request_id', 'abha_address', 'hip_id',
        'patient_reference', 'care_context_reference', 'hi_types', 'purpose_code',
        'requested_date_from', 'requested_date_to', 'requested_expiry_at',
        'latest_status', 'consent_ids', 'granted_on', 'rejection_error',
        'acknowledged', 'acknowledgement_error', 'status_checked_at',
        'raw_init_response', 'raw_last_callback', 'created_on', 'updated_on', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'hi_types'     => 'array',
            'consent_ids'  => 'array',
            'acknowledged' => 'boolean',
        ];
    }
}
