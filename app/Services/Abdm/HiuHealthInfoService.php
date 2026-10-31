<?php

namespace App\Services\Abdm;

use App\Services\AbdmTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ABDM M4 HIU side: request health data + decrypt received FHIR bundles.
 * Ported from bmhabdm/abdm/hiu_health_information.php.
 *
 *   1. requestHealthData() - POST data-flow/v3/health-information/request. A fresh ECDH key pair + nonce
 *      is stored in abdm_hiu_key_material BEFORE the call, because the encrypted data can be pushed
 *      back at any moment after ABDM accepts it.
 *   2. handleOnRequest()   - async ack from ABDM (transactionId).
 *   3. handlePush()        - encrypted bundles pushed by the HIP; decrypted entries are stored in
 *      abdm_hiu_received_records (one row per entry, whether or not decryption succeeded).
 */
class HiuHealthInfoService
{
    /** Key material older than this is never offered as a decryption candidate (we advertise a 1-day key expiry). */
    private const KEY_MAX_AGE_HOURS = 48;

    public function __construct(private AbdmTokenService $tokenSvc) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Step 3: request health data from the HIP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{success:bool, request_id?:string, error?:string, message?:string}
     */
    public function requestHealthData(string $consentId, ?string $fromDate, ?string $toDate, int $userId): array
    {
        $consentId = trim($consentId);
        if ($consentId === '') {
            return ['success' => false, 'error' => 'Consent ID is required'];
        }

        // Consent artefacts are keyed by their own consent id (not by the consent *request*).
        $artefact = DB::table('abdm_hiu_consent_artefacts')->where('consent_id', $consentId)->first();
        if ($artefact) {
            if (strtoupper((string) $artefact->status) !== 'GRANTED') {
                return ['success' => false, 'error' => 'Consent is ' . $artefact->status . ' - health data can only be requested for a GRANTED consent'];
            }
            if (!empty($artefact->data_erase_at) && CarbonImmutable::parse($artefact->data_erase_at, 'UTC')->isPast()) {
                return ['success' => false, 'error' => 'Consent has expired'];
            }
        }

        $range = $this->resolveDateRange($artefact, $fromDate, $toDate);
        if (isset($range['error'])) {
            return ['success' => false, 'error' => $range['error']];
        }

        $callbackBase = rtrim((string) config('abdm.callback_base_url', ''), '/');
        if ($callbackBase === '') {
            return ['success' => false, 'error' => 'ABDM_CALLBACK_BASE_URL is not configured - ABDM would have nowhere to push the data'];
        }
        $dataPushUrl = $callbackBase . '/api/v3/hiu/health-information/push';

        try {
            $keyPair = FideliusCrypto::generateKeyPair();
            $nonce   = FideliusCrypto::generateNonce();
        } catch (\Throwable $e) {
            Log::error('ABDM HIU key generation failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Could not generate encryption key pair'];
        }

        $token = $this->tokenSvc->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Could not get ABDM access token'];
        }

        $requestId = (string) Str::uuid();
        $payload = ['hiRequest' => [
            'consent'     => ['id' => $consentId],
            'dateRange'   => ['from' => $range['from'], 'to' => $range['to']],
            'dataPushUrl' => $dataPushUrl,
            'keyMaterial' => [
                'cryptoAlg'   => 'ECDH',
                'curve'       => 'Curve25519',
                'dhPublicKey' => [
                    'expiry'     => $this->utcStamp(now('UTC')->addDay()),
                    'parameters' => 'Curve25519/32byte random key',
                    'keyValue'   => $keyPair['x509PublicKey'],
                ],
                'nonce' => $nonce,
            ],
        ]];

        // Persist first: the HIP may push before this method even returns.
        $keyRowId = DB::table('abdm_hiu_key_material')->insertGetId([
            'consent_id'           => $consentId,
            'request_id'           => $requestId,
            'transaction_id'       => null,
            'request_error'        => null,
            'request_acknowledged' => 0,
            'our_private_key'      => $keyPair['privateKey'],
            'our_public_key'       => $keyPair['x509PublicKey'],
            'our_nonce'            => $nonce,
            'data_push_url'        => $dataPushUrl,
            'consumed'             => 0,
            'created_on'           => now('UTC')->toDateTimeString(),
            'user_id'              => $userId ?: null,
        ]);

        try {
            $response = Http::withToken($token)->acceptJson()->timeout(60)->connectTimeout(30)->withHeaders([
                'REQUEST-ID' => $requestId,
                'TIMESTAMP'  => $this->utcStamp(now('UTC')),
                'X-CM-ID'    => config('abdm.cm_id', 'sbx'),
                'X-HIU-ID'   => $this->hiuId(),
            ])->post(config('abdm.health_info_request_url'), $payload);
        } catch (\Throwable $e) {
            Log::error('ABDM HIU health-info request failed', ['error' => $e->getMessage()]);
            $this->discardKeyMaterial($keyRowId, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $body = $response->json();
        if (!$response->successful()) {
            $error = $this->extractError(is_array($body) ? $body : null, $response->status());
            Log::warning('ABDM HIU health-information/request rejected', ['http' => $response->status(), 'error' => $error]);
            $this->discardKeyMaterial($keyRowId, $error);
            return ['success' => false, 'error' => $error];
        }

        // Normally absent (the call is async); the on-request callback or the push itself supplies it.
        $transactionId = $body['hiRequest']['transactionId'] ?? ($body['transactionId'] ?? null);
        if ($transactionId) {
            DB::table('abdm_hiu_key_material')->where('id', $keyRowId)->update(['transaction_id' => substr((string) $transactionId, 0, 64)]);
        }

        return ['success' => true, 'request_id' => $requestId, 'message' => 'Health data request submitted (async)'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhook: health-information/on-request
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Success: { "hiRequest": { "transactionId": "..." } }   Failure: { "error": {...} }
     * Legacy bodies carry no correlating id, so the newest unacknowledged request is used; when ABDM
     * does echo response.requestId the exact request is matched instead.
     */
    public function handleOnRequest(array $body): void
    {
        $error         = $body['error'] ?? null;
        $transactionId = $body['hiRequest']['transactionId'] ?? ($body['transactionId'] ?? null);
        $echoedId      = $body['response']['requestId'] ?? null;

        if ($echoedId) {
            $pending = DB::table('abdm_hiu_key_material')
                ->where('request_id', $echoedId)->where('request_acknowledged', 0)->first();
        } else {
            $pending = DB::table('abdm_hiu_key_material')
                ->whereNull('transaction_id')->where('request_acknowledged', 0)
                ->orderByDesc('id')->first();
        }

        if (!$pending) {
            Log::warning('ABDM HIU on-request: no pending key material to match', ['request_id' => $echoedId, 'transaction_id' => $transactionId]);
            return;
        }

        if ($error) {
            $message = $this->extractError(['error' => $error], 0);
            Log::warning('ABDM HIU on-request: request rejected', ['error' => $message]);
            DB::table('abdm_hiu_key_material')->where('id', $pending->id)
                ->update(['request_error' => substr($message, 0, 65000), 'request_acknowledged' => 1]);
            return;
        }

        DB::table('abdm_hiu_key_material')->where('id', $pending->id)->update([
            'transaction_id'       => $transactionId ? substr((string) $transactionId, 0, 64) : null,
            'request_acknowledged' => 1,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhook: health-information/push (decrypt + store)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Push shape: { pageNumber, pageCount, transactionId, entries:[{content,media,checksum,careContextReference}],
     *               keyMaterial:{ dhPublicKey:{keyValue}, nonce } }
     *
     * transactionId is not a reliable join key (see legacy notes), so every plausible key row is tried per
     * entry - exact transaction match first, then unconsumed rows newest first - and whichever decrypts to
     * valid JSON wins. Unlike legacy, a key that decrypted one entry stays usable for the rest of the same
     * push and for later pages of the same transaction (they share one HIP key + nonce).
     */
    public function handlePush(array $body): void
    {
        $rawTransaction = isset($body['transactionId']) && is_scalar($body['transactionId']) ? trim((string) $body['transactionId']) : '';
        $hasTransaction = $rawTransaction !== '';
        $transactionId  = $hasTransaction ? substr($rawTransaction, 0, 64) : 'unknown_' . time();
        $pageNumber     = isset($body['pageNumber']) ? (int) $body['pageNumber'] : null;
        $pageCount      = isset($body['pageCount']) ? (int) $body['pageCount'] : null;
        $entries        = $body['entries'] ?? [];
        $keyMaterial    = $body['keyMaterial'] ?? [];
        $hipPublicKey   = $keyMaterial['dhPublicKey']['keyValue'] ?? null;
        $hipNonce       = $keyMaterial['nonce'] ?? null;

        if (!is_array($entries) || !$entries || !is_string($hipPublicKey) || $hipPublicKey === '' || !is_string($hipNonce) || $hipNonce === '') {
            Log::warning('ABDM HIU push: missing entries or keyMaterial', ['transaction_id' => $transactionId]);
            return;
        }

        $candidates = $this->keyCandidates($hasTransaction ? $transactionId : null);

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $careRef  = isset($entry['careContextReference']) ? substr((string) $entry['careContextReference'], 0, 50) : null;
            $media    = isset($entry['media']) ? substr((string) $entry['media'], 0, 100) : null;
            $expected = isset($entry['checksum']) ? substr((string) $entry['checksum'], 0, 64) : null;
            $content  = $entry['content'] ?? null;
            $rawEntry = json_encode($entry);

            if (!$content || !is_string($content)) {
                $this->recordEntry($transactionId, null, $careRef, $pageNumber, $pageCount, $media, $expected, null, null, null, 'Entry has no content to decrypt', $rawEntry);
                continue;
            }

            $plaintext = null;
            $matched   = null;
            $lastError = null;
            foreach ($candidates as $idx => $keyRow) {
                try {
                    $shared      = FideliusCrypto::computeSharedSecret($keyRow->our_private_key, $hipPublicKey);
                    [$salt, $iv] = FideliusCrypto::deriveSaltAndIv($keyRow->our_nonce, $hipNonce);
                    $aesKey      = FideliusCrypto::deriveAesKey($shared, $salt);
                    $attempt     = FideliusCrypto::decryptPayload($aesKey, $iv, $content);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    continue;
                }
                // The right key yields a JSON FHIR bundle; anything else means a different key pair.
                $decoded = json_decode($attempt, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $plaintext = $attempt;
                    $matched   = $keyRow;
                    // Same HIP key/nonce encrypts the remaining entries: try this key first next time.
                    unset($candidates[$idx]);
                    array_unshift($candidates, $keyRow);
                    break;
                }
            }

            if ($plaintext === null) {
                $message = 'Decryption failed against every candidate key' . ($lastError ? ': ' . $lastError : '');
                Log::error('ABDM HIU push decryption failed', ['transaction_id' => $transactionId, 'care_context' => $careRef]);
                $this->recordEntry($transactionId, null, $careRef, $pageNumber, $pageCount, $media, $expected, null, null, null, substr($message, 0, 500), $rawEntry);
                continue;
            }

            $consentId = $matched->consent_id;
            $actual    = hash('sha256', $plaintext);
            $checksumOk = $expected ? hash_equals($expected, $actual) : null;
            if ($checksumOk === false) {
                Log::warning('ABDM HIU push: checksum mismatch', ['transaction_id' => $transactionId, 'care_context' => $careRef]);
            }

            $this->markKeyUsed($matched, $hasTransaction ? $transactionId : null);

            // Only accept data for a care context the consent actually covers.
            $allowed = $this->consentCareContexts($consentId);
            if ($allowed !== null && $careRef !== null && !in_array($careRef, $allowed, true)) {
                Log::warning('ABDM HIU push: care context not covered by consent', ['consent_id' => $consentId, 'care_context' => $careRef]);
                $this->recordEntry($transactionId, $consentId, $careRef, $pageNumber, $pageCount, $media, $expected, $actual, $checksumOk, null, 'Care context is not covered by the consent', $rawEntry);
                continue;
            }

            // A replayed push (same transaction/page/content) must not create duplicate records.
            $duplicate = DB::table('abdm_hiu_received_records')
                ->where('transaction_id', $transactionId)->where('checksum_actual', $actual)
                ->where('care_context_reference', $careRef)->whereNotNull('decrypted_bundle')->exists();
            if ($duplicate) {
                continue;
            }

            $this->recordEntry($transactionId, $consentId, $careRef, $pageNumber, $pageCount, $media, $expected, $actual, $checksumOk, $plaintext, null, $rawEntry);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FHIR bundle summary (headline fields for the "Received Records" list)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{title:?string, facility:?string, visit_date:?string, patient_name:?string} */
    public function extractFhirSummary(?string $bundleJson): array
    {
        $out = ['title' => null, 'facility' => null, 'visit_date' => null, 'patient_name' => null];
        if (empty($bundleJson)) {
            return $out;
        }
        $bundle = json_decode($bundleJson, true);
        if (!is_array($bundle) || empty($bundle['entry']) || !is_array($bundle['entry'])) {
            return $out;
        }

        // Resolve references written either as "Type/id" (legacy HIPs) or "urn:uuid:id" (this app's bundles).
        $byRef = [];
        $composition = null;
        foreach ($bundle['entry'] as $e) {
            $r = $e['resource'] ?? null;
            if (!is_array($r) || empty($r['resourceType'])) {
                continue;
            }
            if (isset($r['id'])) {
                $byRef[$r['resourceType'] . '/' . $r['id']] = $r;
                $byRef['urn:uuid:' . $r['id']] = $r;
            }
            if (!empty($e['fullUrl'])) {
                $byRef[$e['fullUrl']] = $r;
            }
            if ($r['resourceType'] === 'Composition' && $composition === null) {
                $composition = $r;
            }
        }

        if ($composition) {
            $out['title']      = $composition['title'] ?? ($composition['type']['text'] ?? null);
            $out['visit_date'] = $composition['date'] ?? null;
            $custodian = $composition['custodian']['reference'] ?? null;
            if ($custodian && isset($byRef[$custodian])) {
                $out['facility'] = $byRef[$custodian]['name'] ?? null;
            }
            $subject = $composition['subject']['reference'] ?? null;
            if ($subject && isset($byRef[$subject])) {
                $out['patient_name'] = $byRef[$subject]['name'][0]['text'] ?? null;
            }
        }

        // Some HIPs' bundles are not a single document: fall back to the first Organization/Patient.
        if (!$out['facility'] || !$out['patient_name']) {
            foreach ($bundle['entry'] as $e) {
                $r = $e['resource'] ?? null;
                if (!is_array($r)) {
                    continue;
                }
                if (!$out['facility'] && ($r['resourceType'] ?? null) === 'Organization') {
                    $out['facility'] = $r['name'] ?? null;
                }
                if (!$out['patient_name'] && ($r['resourceType'] ?? null) === 'Patient') {
                    $out['patient_name'] = $r['name'][0]['text'] ?? null;
                }
            }
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ABDM-1063 rejects ranges outside the consent's own permitted range, so default to (and validate
     * against) the artefact's range instead of "last 6 months until now".
     *
     * @return array{from:string,to:string}|array{error:string}
     */
    private function resolveDateRange(?object $artefact, ?string $from, ?string $to): array
    {
        try {
            $reqFrom = $from !== null && $from !== '' ? CarbonImmutable::parse($from, 'UTC') : null;
            $reqTo   = $to !== null && $to !== '' ? CarbonImmutable::parse($to, 'UTC') : null;
        } catch (\Throwable) {
            return ['error' => 'Invalid date range'];
        }

        if ($artefact && !empty($artefact->date_range_from) && !empty($artefact->date_range_to)) {
            $consentFrom = CarbonImmutable::parse($artefact->date_range_from, 'UTC');
            $consentTo   = CarbonImmutable::parse($artefact->date_range_to, 'UTC');
            if ($reqFrom && $reqFrom->lt($consentFrom)) {
                return ['error' => "Requested date range starts before the consent's permitted range"];
            }
            if ($reqTo && $reqTo->gt($consentTo)) {
                return ['error' => "Requested date range ends after the consent's permitted range"];
            }
            $reqFrom ??= $consentFrom;
            $reqTo   ??= $consentTo;
        } else {
            $reqFrom ??= now('UTC')->subMonths(6)->startOfDay()->toImmutable();
            $reqTo   ??= now('UTC')->subSeconds(10)->toImmutable();
        }

        if ($reqFrom->gt($reqTo)) {
            return ['error' => 'Date range start must not be after its end'];
        }

        return ['from' => $this->utcStamp($reqFrom), 'to' => $this->utcStamp($reqTo)];
    }

    /** Usable key rows: not failed, recent, and either unconsumed or already tied to this very transaction. */
    private function keyCandidates(?string $transactionId): array
    {
        $rows = DB::table('abdm_hiu_key_material')
            ->where(fn ($q) => $q->whereNull('request_error')->orWhere('request_error', ''))
            ->where('created_on', '>=', now('UTC')->subHours(self::KEY_MAX_AGE_HOURS)->toDateTimeString())
            ->where(function ($q) use ($transactionId) {
                $q->where('consumed', 0);
                if ($transactionId !== null) {
                    $q->orWhere('transaction_id', $transactionId);
                }
            })
            ->orderByDesc('id')->get()->all();

        if ($transactionId !== null) {
            usort($rows, fn ($a, $b) => (($b->transaction_id === $transactionId) <=> ($a->transaction_id === $transactionId)) ?: ($b->id <=> $a->id));
        }

        return array_values($rows);
    }

    private function markKeyUsed(object $keyRow, ?string $transactionId): void
    {
        $update = ['consumed' => 1];
        if ($transactionId !== null && empty($keyRow->transaction_id)) {
            $update['transaction_id'] = $transactionId;
        }
        DB::table('abdm_hiu_key_material')->where('id', $keyRow->id)->update($update);
    }

    /** Care-context references the consent covers, or null when no artefact (or no list) is on file. */
    private function consentCareContexts(?string $consentId): ?array
    {
        if (!$consentId) {
            return null;
        }
        $json = DB::table('abdm_hiu_consent_artefacts')->where('consent_id', $consentId)->value('care_contexts');
        $list = $json ? json_decode($json, true) : null;
        if (!is_array($list) || !$list) {
            return null;
        }

        return array_values(array_filter(array_map(fn ($cc) => $cc['careContextReference'] ?? null, $list)));
    }

    private function discardKeyMaterial(int $keyRowId, string $error): void
    {
        DB::table('abdm_hiu_key_material')->where('id', $keyRowId)
            ->update(['consumed' => 1, 'request_acknowledged' => 1, 'request_error' => substr($error, 0, 65000)]);
    }

    private function hiuId(): string
    {
        return (string) (config('abdm.hiu_id') ?: config('abdm.hip_id'));
    }

    private function utcStamp($dt): string
    {
        return CarbonImmutable::instance($dt)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    private function extractError(?array $body, int $status): string
    {
        $error = $body['error'] ?? null;
        if (is_array($error)) {
            $message = $error['message'] ?? json_encode($error);
        } else {
            $message = $error ?? ($body['message'] ?? null);
        }

        return $message ? (string) $message : ($status ? 'ABDM request failed (HTTP ' . $status . ')' : 'ABDM request failed');
    }

    private function recordEntry(
        string $transactionId, ?string $consentId, ?string $careRef, ?int $pageNumber, ?int $pageCount,
        ?string $media, ?string $checksumExpected, ?string $checksumActual, ?bool $checksumOk,
        ?string $decryptedBundle, ?string $decryptionError, ?string $rawEntry
    ): void {
        // A decrypted bundle is stored in decrypted_bundle; the still-encrypted entry is only kept for failures.
        $now = now('UTC')->toDateTimeString();
        DB::table('abdm_hiu_received_records')->insert([
            'transaction_id'         => $transactionId,
            'consent_id'             => $consentId,
            'care_context_reference' => $careRef,
            'page_number'            => $pageNumber,
            'page_count'             => $pageCount,
            'media'                  => $media,
            'checksum_expected'      => $checksumExpected,
            'checksum_actual'        => $checksumActual,
            'checksum_ok'            => $checksumOk === null ? null : (int) $checksumOk,
            'decrypted_bundle'       => $decryptedBundle,
            'decryption_error'       => $decryptionError,
            'raw_entry'              => $rawEntry,
            'received_at'            => $now,
            'created_on'             => $now,
        ]);
    }
}
