<?php

namespace App\Services\Abdm;

use App\Services\AbdmTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles ABDM HIP-side consent notifications (M3).
 * Wraps legacy abdm/consent.php logic.
 */
class HipConsentService
{
    private const VALID_STATUSES    = ['GRANTED', 'REVOKED', 'EXPIRED', 'DENIED'];
    private const TERMINAL_STATUSES = ['REVOKED', 'EXPIRED', 'DENIED'];

    public function __construct(private AbdmTokenService $tokenSvc) {}

    /**
     * Process an incoming HIP consent notification from ABDM.
     * Stores (or updates) the consent, then sends the required acknowledgement back.
     */
    public function handleNotify(array $notification, string $originalRequestId): array
    {
        $store = $this->storeNotification($notification);
        if (!$store['success']) return $store;

        return $this->sendAcknowledgement($store['consent_id'], $originalRequestId);
    }

    // ─── Store ────────────────────────────────────────────────────────────

    private function storeNotification(array $notification): array
    {
        $consentId = $notification['consentId'] ?? null;
        if (!is_string($consentId) || $consentId === '') return ['success' => false, 'error' => 'Missing consentId'];

        $status = strtoupper((string) ($notification['status'] ?? ''));
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return ['success' => false, 'error' => 'Invalid consent status'];
        }

        $detail    = is_array($notification['consentDetail'] ?? null) ? $notification['consentDetail'] : [];
        $hasDetail = $detail !== [];
        // Stored (and shown in the Link-to-ABDM modal) as UTC, like legacy UTC_TIMESTAMP().
        $now       = now('UTC')->toDateTimeString();

        return DB::transaction(function () use ($notification, $consentId, $status, $detail, $hasDetail, $now) {
            $existing = DB::table('abdm_consents')->where('consent_id', $consentId)->lockForUpdate()->first();

            if ($existing) {
                // A REVOKED / EXPIRED / DENIED consent never goes back to another state: ABDM
                // notifications can arrive out of order or be replayed, and we cannot tell
                // which one is newer. Re-acknowledge, change nothing.
                if (in_array(strtoupper((string) $existing->status), self::TERMINAL_STATUSES, true)) {
                    return ['success' => true, 'consent_id' => $consentId, 'status' => $existing->status];
                }

                // REVOKED / EXPIRED (and a detail-less re-notification): only flip the status and
                // keep the stored artefact (care contexts, hi types, date range) intact.
                if ($status !== 'GRANTED' || !$hasDetail) {
                    DB::table('abdm_consents')->where('consent_id', $consentId)
                        ->update(['status' => $status, 'updated_on' => $now]);
                    return ['success' => true, 'consent_id' => $consentId, 'status' => $status];
                }

                DB::table('abdm_consents')->where('consent_id', $consentId)
                    ->update($this->detailColumns($detail) + [
                        'status'           => $status,
                        'raw_notification' => json_encode($notification),
                        'updated_on'       => $now,
                    ]);
                return ['success' => true, 'consent_id' => $consentId, 'status' => $status];
            }

            // A GRANTED consent without its artefact is unusable (no scope to authorise against).
            if ($status === 'GRANTED' && !$hasDetail) {
                return ['success' => false, 'error' => 'GRANTED notification is missing consentDetail'];
            }

            DB::table('abdm_consents')->insert($this->detailColumns($detail) + [
                'consent_id'       => $consentId,
                'status'           => $status,
                'acknowledged'     => 0,
                'raw_notification' => json_encode($notification),
                'created_on'       => $now,
                'updated_on'       => $now,
            ]);

            return ['success' => true, 'consent_id' => $consentId, 'status' => $status];
        });
    }

    /** Columns derived from consentDetail. */
    private function detailColumns(array $detail): array
    {
        return [
            'patient_abha_address' => (string) ($detail['patient']['id'] ?? ''),
            'hip_id'               => (string) ($detail['hip']['id'] ?? ''),
            'purpose_code'         => (string) ($detail['purpose']['code'] ?? ''),
            'hi_types'             => json_encode($detail['hiTypes'] ?? []),
            'care_contexts'        => json_encode($detail['careContexts'] ?? []),
            'date_range_from'      => $this->parseIso($detail['permission']['dateRange']['from'] ?? null),
            'date_range_to'        => $this->parseIso($detail['permission']['dateRange']['to'] ?? null),
            'data_erase_at'        => $this->parseIso($detail['permission']['dataEraseAt'] ?? null),
        ];
    }

    // ─── Acknowledgement ─────────────────────────────────────────────────

    public function sendAcknowledgement(string $consentId, string $originalRequestId = ''): array
    {
        $token = $this->tokenSvc->getAccessToken();
        if (!$token) {
            $this->recordAck($consentId, false, 'Could not get ABDM access token');
            return ['success' => false, 'error' => 'Could not get ABDM access token'];
        }

        $reqId   = (string) Str::uuid();
        $payload = [
            'acknowledgement' => ['status' => 'OK', 'consentId' => $consentId],
            'response'        => ['requestId' => $originalRequestId ?: $reqId],
        ];

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'REQUEST-ID' => $reqId,
                    'TIMESTAMP'  => AbdmTokenService::timestamp(),
                    'X-CM-ID'    => 'sbx',
                    'X-HIP-ID'   => config('abdm.hip_id'),
                ])
                ->post(config('abdm.consent_on_notify_url'), $payload);

            if ($response->successful()) {
                $this->recordAck($consentId, true, null);
                return ['success' => true];
            }

            $error = $response->json('error.message') ?? $response->body();
            $this->recordAck($consentId, false, $error);
            return ['success' => false, 'error' => $error];
        } catch (\Exception $e) {
            $this->recordAck($consentId, false, $e->getMessage());
            Log::error('ABDM HIP consent ack failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function recordAck(string $consentId, bool $success, ?string $error): void
    {
        DB::table('abdm_consents')->where('consent_id', $consentId)->update([
            'acknowledged'          => $success ? 1 : 0,
            'acknowledgement_error' => $error !== null ? substr($error, 0, 500) : null,
            'updated_on'            => now('UTC')->toDateTimeString(),
        ]);
    }

    private function parseIso(?string $iso): ?string
    {
        if (empty($iso)) return null;
        $ts = strtotime($iso);
        return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : null;
    }
}
