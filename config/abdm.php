<?php
return [
    'env'           => env('ABDM_ENV', 'sandbox'),
    'hip_id'        => env('ABDM_HIP_ID', ''),
    'hiu_id'        => env('ABDM_HIU_ID', ''),
    'client_id'     => env('ABDM_CLIENT_ID', ''),
    'client_secret' => env('ABDM_CLIENT_SECRET', ''),
    'callback_base_url' => env('ABDM_CALLBACK_BASE_URL', ''),

    // Session / token endpoint
    'session_url'   => env('ABDM_SESSION_URL', 'https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions'),
    // ABHA profile API base
    'base_url'      => env('ABDM_BASE_URL', 'https://abhasbx.abdm.gov.in/abha/api'),
    // HIECM gateway base
    'gateway_url'   => env('ABDM_GATEWAY_URL', 'https://dev.abdm.gov.in/api/hiecm/gateway'),

    // M2 Step 1: generate link token (HIP-initiated linking)
    'link_token_generate_url' => 'https://dev.abdm.gov.in/api/hiecm/v3/token/generate-token',

    // M2 Step 2: add care context
    'care_context_add_url'    => 'https://dev.abdm.gov.in/api/hiecm/hip/v3/link/carecontext',

    // M3 HIP: acknowledge consent notification
    'consent_on_notify_url'   => 'https://dev.abdm.gov.in/api/hiecm/consent/v3/request/hip/on-notify',

    // M4 HIP: interim ack for health-info request
    'health_info_hip_on_request_url' => 'https://dev.abdm.gov.in/api/hiecm/data-flow/v3/health-information/hip/on-request',
    // M4 HIP: notify outcome of data push
    'health_info_notify_url'  => 'https://dev.abdm.gov.in/api/hiecm/data-flow/v3/health-information/notify',

    // Profile Share on-share ack
    'profile_on_share_url'    => 'https://dev.abdm.gov.in/api/hiecm/patient-share/v3/on-share',
    // Running token on-status ack
    'running_token_on_status_url' => 'https://dev.abdm.gov.in/api/hiecm/patient-share/v3/running-token/on-status',
    // Average minutes per patient token
    'running_token_avg_service_minutes' => 5,

    // HIU: initiate consent request
    'consent_request_init_url'   => 'https://dev.abdm.gov.in/api/hiecm/consent/v3/request/init',
    // HIU: poll consent request status
    'consent_request_status_url' => 'https://dev.abdm.gov.in/api/hiecm/consent/v3/request/status',
    // HIU: fetch granted consent artefact
    'consent_fetch_url'          => 'https://dev.abdm.gov.in/api/hiecm/consent/v3/fetch',
    // HIU: acknowledge consent notify
    'consent_hiu_on_notify_url'  => 'https://dev.abdm.gov.in/api/hiecm/consent/v3/request/hiu/on-notify',
    // HIU: request health information from HIP
    'health_info_request_url'    => 'https://dev.abdm.gov.in/api/hiecm/data-flow/v3/health-information/request',

    // X-CM-ID header sent on every gateway call (sbx = sandbox, abdm = production)
    'cm_id' => env('ABDM_CM_ID', 'sbx'),

    // Callback guard (App\Http\Middleware\VerifyAbdmCallback) on every ABDM -> us route:
    //   enforce (default) reject callbacks that fail the checks
    //   log               only record what WOULD be rejected - to diagnose a sandbox integration
    //   off               disable the guard
    'callback_auth'       => env('ABDM_CALLBACK_AUTH', 'enforce'),
    'callback_clock_skew' => 120, // seconds of slack when checking a bearer token's exp
    // Demand X-HIP-ID on unsolicited HIP callbacks (consent notify, data request, profile share, running
    // token). Off by default because the legacy bridge accepts a missing header; turn it on once you have
    // confirmed in the sandbox that ABDM always sends it. A header that is present is always validated.
    'callback_require_facility' => (bool) env('ABDM_CALLBACK_REQUIRE_FACILITY', false),

    // Optional allow-list of hosts the HIP may push health data to (lower-case host names).
    // Empty = any public https host is allowed (private/loopback addresses are always refused).
    'trusted_push_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('ABDM_TRUSTED_PUSH_HOSTS', ''))))),

    // ABHA account services (update mobile / email): how long the server-side session state
    // (login txn, X-token, new-mobile txn) stays usable - about the OTP validity (seconds).
    'identity_update_ttl_seconds' => 900,

    // Retry / cooldown durations (seconds). After a failed call to ABDM the same request is not repeated for:
    //   retry     - any other failure (5 min)
    //   duplicate - ABDM says it already has this request ("Duplicate Link token request"): retrying early can get
    //               ABDM to block the whole facility, so wait the legacy 24 h (override with the .env keys below)
    //   block     - ABDM has rate-limited this facility ("You are blocked. Please try again after 24 hours"): the
    //               24 h ABDM itself announces
    'link_token_retry_cooldown'        => 300,
    'link_token_duplicate_cooldown'    => (int) env('ABDM_LINK_TOKEN_DUPLICATE_COOLDOWN', 86400),
    'link_token_block_cooldown'        => 86400,
    'link_token_pending_timeout'       => 600,
    'care_context_retry_cooldown'      => 300,
    'care_context_duplicate_cooldown'  => (int) env('ABDM_CARE_CONTEXT_DUPLICATE_COOLDOWN', 86400),
    'care_context_block_cooldown'      => 86400,
    'care_context_pending_timeout'     => 600,
    // How long the "Link Care Context" request waits for ABDM's async on_carecontext callback before answering
    // "pending" (legacy: 30s). 0 answers immediately and leaves the modal to poll.
    'care_context_wait_seconds'        => (int) env('ABDM_CARE_CONTEXT_WAIT_SECONDS', 30),

    // HIU data-push retry (ported from legacy abdm_execute_data_push_with_retry: 3 retries after the
    // first attempt = 4 attempts in total, exponential backoff 2s / 4s / 8s).
    // push_retry_max_attempts counts ALL attempts; push_retry_delays are seconds waited after
    // failed attempt 1, 2, 3 ... (the last value is reused if there are more retries than entries).
    'push_retry_max_attempts'          => 4,
    'push_retry_delays'                => [2, 4, 8],

    // Multi-facility support (all facilities registered under this bridge)
    'known_facilities' => [
        'IN0210000410' => 'City Health Care',
        'IN0210000405' => 'Bhardwaj Multispeciality Hospital',
        'IN0210000409' => 'Kiva Healthcare',
    ],

    // Patient & care context reference number prefixes
    'patient_ref_prefix'       => 'BMH',
    'opd_ref_prefix'           => 'OPD',
    // IPD/DCD care contexts share ONE reference per admission/visit (IPD0000011, DCD0000006),
    // exactly like the legacy bridge: all HI types hang off that reference. Contexts linked
    // earlier with typed references (IPDDS/IPDRX/IPDLAB/DCDRX/DCDLAB) are resolved from
    // abdm_care_contexts, so no prefix map is needed.

    // HI type sets per module: all 7 HI types hang off ONE care context per encounter.
    // When ANY encounter data exists, all 7 HI types are linked upfront so data added later can be pushed without re-linking.
    'opd_hi_types' => [
        'OPConsultation',
        'Prescription',
        'DiagnosticReport',
        'Invoice',
        'ImmunizationRecord',
        'WellnessRecord',
        'HealthDocumentRecord',
    ],
    'ipd_hi_types' => [
        'DischargeSummary',
        'Prescription',
        'DiagnosticReport',
        'Invoice',
        'ImmunizationRecord',
        'WellnessRecord',
        'HealthDocumentRecord',
    ],
    'dcd_hi_types' => [
        'DischargeSummary',
        'Prescription',
        'DiagnosticReport',
        'Invoice',
        'ImmunizationRecord',
        'WellnessRecord',
        'HealthDocumentRecord',
    ],

    // HI types delivered during HIP data transfer per module.
    // All 7 supported HI types are delivered when linked and consented.
    'opd_delivery_hi_types' => [
        'OPConsultation',
        'Prescription',
        'DiagnosticReport',
        'Invoice',
        'ImmunizationRecord',
        'WellnessRecord',
        'HealthDocumentRecord',
    ],
    'ipd_delivery_hi_types' => [
        'DischargeSummary',
        'Prescription',
        'DiagnosticReport',
        'Invoice',
        'ImmunizationRecord',
        'WellnessRecord',
        'HealthDocumentRecord',
    ],
    'dcd_delivery_hi_types' => [
        'DischargeSummary',
        'Prescription',
        'DiagnosticReport',
        'Invoice',
        'ImmunizationRecord',
        'WellnessRecord',
        'HealthDocumentRecord',
    ],

    // Consent date range vs. the encounter being shared (HIP push):
    //   overlap (default) deliver when the encounter's period [start, discharge/now] overlaps the consent range
    //   off               legacy behaviour: never look at the range, deliver every linked care context
    // 'off' is the quick way to test in the sandbox, where consents are often created with odd ranges.
    'consent_date_range' => env('ABDM_CONSENT_DATE_RANGE', 'overlap'),

    // Profile Share: seconds the token shown in the patient's ABHA app stays valid (legacy: fixed 1800).
    'profile_share_ack_expiry_seconds' => 1800,
];
