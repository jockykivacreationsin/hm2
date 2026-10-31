<?php

namespace App\Services\Abdm;

use App\Services\Abdm\Fhir\DcdFhirBuilder;
use App\Services\Abdm\Fhir\HealthDocumentFhirBuilder;
use App\Services\Abdm\Fhir\ImmunizationFhirBuilder;
use App\Services\Abdm\Fhir\InvoiceFhirBuilder;
use App\Services\Abdm\Fhir\IpdFhirBuilder;
use App\Services\Abdm\Fhir\OpdFhirBuilder;
use App\Services\Abdm\Fhir\WellnessFhirBuilder;
use App\Services\AbdmTokenService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * ABDM M4 HIP Health Information push.
 * Ported from bmhabdm/abdm/health_information.php.
 *
 * For each stored health-information/request (abdm_health_info_requests):
 *   1. atomically claim the row (pending → processing) so it is processed once;
 *   2. authorise the request against the stored consent (status, expiry, HIP, patient,
 *      care contexts, HI types, date range);
 *   3. per linked care-context row: build the FHIR bundle, encrypt it with a FRESH HIP key pair +
 *      nonce, and push it as its own transfer (pageNumber 0 / pageCount 1, as legacy does);
 *   4. notify the ABDM gateway once with the real per-care-context outcome, then write one
 *      abdm_health_info_pushes ledger row per attempted entry and set the final status.
 */
class HipHealthInfoService
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_PROCESSING  = 'processing';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_FAILED      = 'failed';

    private const PUSH_TIMEOUT = 30; // seconds per HTTP attempt (legacy: CURLOPT_TIMEOUT 30)

    public function __construct(
        private AbdmTokenService          $tokenSvc,
        private OpdFhirBuilder            $opdBuilder,
        private IpdFhirBuilder            $ipdBuilder,
        private DcdFhirBuilder            $dcdBuilder,
        private ?InvoiceFhirBuilder       $invoiceBuilder = null,
        private ?ImmunizationFhirBuilder  $immunizationBuilder = null,
        private ?WellnessFhirBuilder      $wellnessBuilder = null,
        private ?HealthDocumentFhirBuilder $healthDocumentBuilder = null,
    ) {
        $this->invoiceBuilder = $invoiceBuilder ?? app(InvoiceFhirBuilder::class);
        $this->immunizationBuilder = $immunizationBuilder ?? app(ImmunizationFhirBuilder::class);
        $this->wellnessBuilder = $wellnessBuilder ?? app(WellnessFhirBuilder::class);
        $this->healthDocumentBuilder = $healthDocumentBuilder ?? app(HealthDocumentFhirBuilder::class);
    }

    /**
     * Process a stored health-information/request row (as an array).
     *
     * @return array{success:bool, skipped?:bool, status?:string, error?:string, delivered?:int, failed?:int, notify_succeeded?:bool, outcomes?:array}
     */
    public function processRequest(array $storedRequest): array
    {
        $transactionId = (string) ($storedRequest['transaction_id'] ?? '');
        $requestId     = (string) ($storedRequest['request_id'] ?? '');
        if ($transactionId === '') {
            return ['success' => false, 'error' => 'Missing transaction_id'];
        }

        // Atomic claim: only one worker can move the row pending → processing.
        $claim = DB::table('abdm_health_info_requests')
            ->where('transaction_id', $transactionId)
            ->where('status', self::STATUS_PENDING);
        if ($requestId !== '') {
            $claim->where('request_id', $requestId);
        }
        if ($claim->update(['status' => self::STATUS_PROCESSING]) === 0) {
            return ['success' => false, 'skipped' => true, 'error' => 'Request is not pending (already claimed or processed)'];
        }

        // Retries + backoff can keep a run busy for much longer than one HTTP timeout; give the whole
        // run room for the worst case of several entries (no-op on CLI queue workers, where it is 0).
        if (function_exists('set_time_limit')) {
            $attempts = max(1, (int) config('abdm.push_retry_max_attempts', 4));
            $backoff  = array_sum(array_map('floatval', (array) config('abdm.push_retry_delays', [])));
            @set_time_limit((int) ceil(10 * ($attempts * self::PUSH_TIMEOUT + $backoff)) + 60);
        }

        try {
            $keyMaterial = $storedRequest['key_material'] ?? null;
            if (is_string($keyMaterial)) {
                $keyMaterial = json_decode($keyMaterial, true);
            }

            return $this->execute(
                $transactionId,
                $requestId,
                (string) ($storedRequest['consent_id'] ?? ''),
                (string) ($storedRequest['data_push_url'] ?? ''),
                is_array($keyMaterial) ? $keyMaterial : [],
            );
        } catch (Throwable $e) {
            Log::error('ABDM health-info processing crashed', ['transaction_id' => $transactionId, 'error' => $e->getMessage()]);
            $this->markFinal($transactionId, $requestId, self::STATUS_FAILED);
            return ['success' => false, 'status' => self::STATUS_FAILED, 'error' => 'Internal error while processing request'];
        }
    }

    // ─── Orchestration ───────────────────────────────────────────────────────

    private function execute(string $transactionId, string $requestId, string $consentId, string $dataPushUrl, array $keyMaterial): array
    {
        $consent = $consentId === '' ? null : DB::table('abdm_consents')->where('consent_id', $consentId)->first();
        if (!$consent) {
            return $this->conclude($transactionId, $requestId, $consentId, (string) config('abdm.hip_id'), [], 'Consent not found');
        }

        $hipId       = (string) $consent->hip_id;
        $consentRefs = $this->consentCareContexts($consent);

        if ($reason = $this->consentDenialReason($consent)) {
            return $this->failAll($transactionId, $requestId, $consentId, $hipId, $consentRefs, $reason);
        }

        $hiuPublicKey = $keyMaterial['dhPublicKey']['keyValue'] ?? null;
        $hiuNonce     = $keyMaterial['nonce'] ?? null;
        if (!is_string($hiuPublicKey) || $hiuPublicKey === '' || !is_string($hiuNonce) || $hiuNonce === '') {
            return $this->failAll($transactionId, $requestId, $consentId, $hipId, $consentRefs, 'Invalid HIU key material');
        }

        [$urlError, $target] = $this->validatePushUrl($dataPushUrl);
        if ($urlError) {
            Log::warning('ABDM health-info push URL rejected', ['transaction_id' => $transactionId, 'reason' => $urlError]);
            return $this->failAll($transactionId, $requestId, $consentId, $hipId, $consentRefs, $urlError);
        }

        $consentHiTypes = array_values(array_filter((array) json_decode((string) $consent->hi_types, true), 'is_string'));
        $dateFrom       = $this->toUtcIso($consent->date_range_from);
        $dateTo         = $this->toUtcIso($consent->date_range_to);

        $outcomes = [];
        $seen     = [];
        foreach ($consentRefs as $ref => $patientReference) {
            $rows = DB::table('abdm_care_contexts')
                ->where('care_context_reference_number', $ref)
                ->where('status', 'linked')
                ->get();

            if ($rows->isEmpty()) {
                $outcomes[] = $this->outcome($ref, null, null, 'Care context is not linked');
                continue;
            }

            $firstRow = $rows->first();
            $source   = $this->sourceOf($firstRow);
            if (!$source) {
                $outcomes[] = $this->outcome($ref, null, null, 'Care context has no unique source record');
                continue;
            }

            if (strcasecmp(trim((string) $firstRow->abha_address), trim((string) $consent->patient_abha_address)) !== 0) {
                $outcomes[] = $this->outcome($ref, null, $source, 'Care context does not belong to the consenting patient');
                continue;
            }

            [$kind, $sourceId] = $source;
            $linkedHiTypes = $rows->pluck('hi_type')->filter()->map('strval')->all();

            // If an IPD/DCD encounter now has a discharge summary record, include DischargeSummary
            // even if the care context was linked prior to discharge summary creation.
            if (in_array($kind, ['IPD', 'DCD'], true) && !in_array('DischargeSummary', $linkedHiTypes, true)) {
                $table = strtolower($kind) . '_discharge';
                $col   = strtolower($kind) . '_id';
                if (\Illuminate\Support\Facades\Schema::hasTable($table) && DB::table($table)->where($col, $sourceId)->exists()) {
                    $linkedHiTypes[] = 'DischargeSummary';
                }
            }

            $deliveryKey     = 'abdm.' . strtolower($kind) . '_delivery_hi_types';
            $fallbackKey     = 'abdm.' . strtolower($kind) . '_hi_types';
            $configuredTypes = (array) (config()->has($deliveryKey) ? config($deliveryKey) : config($fallbackKey, []));
            $candidateTypes  = $configuredTypes ? array_intersect(array_unique($linkedHiTypes), $configuredTypes) : array_unique($linkedHiTypes);
            $typesToDeliver  = array_values(array_intersect($candidateTypes, $consentHiTypes));

            if (empty($typesToDeliver)) {
                $outcomes[] = $this->outcome($ref, null, $source, 'No consented health information type is linked for this care context');
                continue;
            }

            $contextOutcomes = $this->deliverContext(
                $source, $ref, $typesToDeliver, $consentId, $transactionId, $hipId,
                $dateFrom, $dateTo, $hiuPublicKey, $hiuNonce, $dataPushUrl, $target, $seen
            );

            foreach ($contextOutcomes as $co) {
                $outcomes[] = $co;
            }
        }

        return $this->conclude($transactionId, $requestId, $consentId, $hipId, $outcomes, $outcomes ? null : 'No care contexts in consent');
    }

    // ─── Authorisation ───────────────────────────────────────────────────────

    /** @return string|null reason the consent may not be used, null when usable */
    private function consentDenialReason(object $consent): ?string
    {
        if (strtoupper((string) $consent->status) !== 'GRANTED') {
            return 'Consent is not granted';
        }

        if (!empty($consent->data_erase_at)) {
            $erase = strtotime((string) $consent->data_erase_at . ' UTC');
            if ($erase !== false && $erase <= time()) {
                return 'Consent has expired';
            }
        }

        $hip   = (string) $consent->hip_id;
        $known = array_map('strval', array_keys((array) config('abdm.known_facilities', [])));
        if ($hip === '' || ($hip !== (string) config('abdm.hip_id') && !in_array($hip, $known, true))) {
            return 'Consent is not for this facility';
        }

        return null;
    }

    /** @return array<string,string> careContextReference => patientReference (as stated in the consent) */
    private function consentCareContexts(object $consent): array
    {
        $refs = [];
        foreach ((array) json_decode((string) $consent->care_contexts, true) as $cc) {
            $ref = is_array($cc) ? ($cc['careContextReference'] ?? null) : null;
            if (is_string($ref) && $ref !== '') {
                $refs[$ref] = (string) ($cc['patientReference'] ?? '');
            }
        }
        return $refs;
    }

    /** @return array{0:string,1:int}|null [kind, id]; null unless exactly one source id is set */
    private function sourceOf(object $row): ?array
    {
        $set = [];
        foreach (['OPD' => 'opd_id', 'IPD' => 'ipd_id', 'DCD' => 'dcd_id'] as $kind => $col) {
            if (!empty($row->{$col})) {
                $set[] = [$kind, (int) $row->{$col}];
            }
        }
        return count($set) === 1 ? $set[0] : null;
    }

    // ─── Entry delivery: build → encrypt → push ──────────────────────────────

    private function buildBundleForType(string $kind, int $id, string $hiType, ?string $dateFrom, ?string $dateTo): ?array
    {
        return match ($hiType) {
            'Invoice'              => $this->invoiceBuilder->buildBundle($kind, $id, $dateFrom, $dateTo),
            'ImmunizationRecord'   => $this->immunizationBuilder->buildBundle($kind, $id, $dateFrom, $dateTo),
            'WellnessRecord'       => $this->wellnessBuilder->buildBundle($kind, $id, $dateFrom, $dateTo),
            'HealthDocumentRecord' => $this->healthDocumentBuilder->buildBundle($kind, $id, $dateFrom, $dateTo),
            default                => match ($kind) {
                'OPD'   => $this->opdBuilder->buildBundle($id, [$hiType], $dateFrom, $dateTo),
                'IPD'   => $this->ipdBuilder->buildBundle($id, $hiType, $dateFrom, $dateTo),
                default => $this->dcdBuilder->buildBundle($id, $hiType, $dateFrom, $dateTo),
            },
        };
    }

    /**
     * Builds and delivers all consented and linked HI types for a single care context
     * in a SINGLE encrypted transfer payload per ABDM M4 specification.
     *
     * @param array{0: string, 1: int} $source
     * @param list<string> $typesToDeliver
     * @param array<string, bool> $seen
     * @return list<array<string, mixed>>
     */
    private function deliverContext(
        array $source, string $ref, array $typesToDeliver, string $consentId, string $transactionId, string $hipId,
        ?string $dateFrom, ?string $dateTo, string $hiuPublicKey, string $hiuNonce, string $dataPushUrl, array $target,
        array &$seen
    ): array {
        [$kind, $id] = $source;

        $bundlesToPush = [];
        $missingTypes = [];
        $buildErrors = [];

        foreach ($typesToDeliver as $hiType) {
            $key = implode('|', [$ref, $hiType, $kind, $id]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            try {
                $bundle = $this->buildBundleForType($kind, $id, $hiType, $dateFrom, $dateTo);
            } catch (InvalidArgumentException $e) {
                $buildErrors[] = $this->outcome($ref, $hiType, $source, 'Invalid consent date range');
                continue;
            } catch (Throwable $e) {
                Log::error('ABDM FHIR build failed', ['ref' => $ref, 'hi_type' => $hiType, 'error' => $e->getMessage()]);
                $buildErrors[] = $this->outcome($ref, $hiType, $source, 'Could not build FHIR bundle');
                continue;
            }

            if (!$bundle) {
                $missingTypes[] = $hiType;
                continue;
            }

            $bundlesToPush[] = [
                'hiType' => $hiType,
                'bundle' => $bundle,
            ];
        }

        if (empty($bundlesToPush)) {
            $contextOutcomes = $buildErrors;
            foreach ($missingTypes as $hiType) {
                $contextOutcomes[] = $this->outcome($ref, $hiType, $source, 'No health information available in the consented date range');
            }
            return $contextOutcomes;
        }

        // Generate ONE key pair for ALL bundles in this care context (shared encryption context per M4 spec)
        try {
            $hipKeyPair   = FideliusCrypto::generateKeyPair();
            $hipNonce     = FideliusCrypto::generateNonce();
            $sharedSecret = FideliusCrypto::computeSharedSecret($hipKeyPair['privateKey'], $hiuPublicKey);
            [$salt, $iv]  = FideliusCrypto::deriveSaltAndIv($hipNonce, $hiuNonce);
            $aesKey       = FideliusCrypto::deriveAesKey($sharedSecret, $salt);
        } catch (Throwable $e) {
            Log::error('ABDM encryption setup failed', ['ref' => $ref, 'error' => $e->getMessage()]);
            $contextOutcomes = $buildErrors;
            foreach ($bundlesToPush as $b) {
                $contextOutcomes[] = $this->outcome($ref, $b['hiType'], $source, 'Encryption setup failed');
            }
            return $contextOutcomes;
        }

        // Encrypt all bundles using the same shared secret and IV
        $entries = [];
        $pushedHiTypes = [];
        $contextOutcomes = $buildErrors;

        foreach ($bundlesToPush as $b) {
            $hiType = $b['hiType'];
            $json = json_encode($b['bundle'], JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $contextOutcomes[] = $this->outcome($ref, $hiType, $source, 'Could not serialise FHIR bundle');
                continue;
            }

            try {
                $encrypted = FideliusCrypto::encryptPayload($aesKey, $iv, $json);
            } catch (Throwable $e) {
                Log::error('ABDM encryption failed', ['ref' => $ref, 'hi_type' => $hiType, 'error' => $e->getMessage()]);
                $contextOutcomes[] = $this->outcome($ref, $hiType, $source, 'Encryption failed');
                continue;
            }

            $entries[] = [
                'content'              => $encrypted,
                'media'                => 'application/fhir+json',
                'checksum'             => hash('sha256', $json),
                'careContextReference' => $ref,
            ];
            $pushedHiTypes[] = $hiType;
        }

        if (empty($entries)) {
            return $contextOutcomes;
        }

        $payload = [
            'pageNumber'    => 0,
            'pageCount'     => 1,
            'transactionId' => $transactionId,
            'entries'       => $entries,
            'keyMaterial'   => [
                'cryptoAlg'   => 'ECDH',
                'curve'       => 'Curve25519',
                'dhPublicKey' => [
                    'expiry'     => gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+7 days')),
                    'parameters' => 'Curve25519/32byte random key',
                    'keyValue'   => $hipKeyPair['x509PublicKey'],
                ],
                'nonce'         => $hipNonce,
            ],
        ];

        $hiTypeLabel = implode(',', $pushedHiTypes);
        $pushResult = $this->push($source, $ref, $hiTypeLabel, $hipId, $dataPushUrl, $target, $payload);

        foreach ($pushedHiTypes as $hiType) {
            $contextOutcomes[] = $this->outcome(
                $ref,
                $hiType,
                $source,
                $pushResult['ok'] ? null : $pushResult['error'],
                $pushResult['http_code'],
                $pushResult['attempts']
            );
        }

        // If at least one entry succeeded, filter out missing-data bundles so an encounter without labs or
        // discharge summary isn't treated as an error when other clinical data was delivered.
        $hasDelivered = collect($contextOutcomes)->contains(fn ($o) => $o['ok']);
        if ($hasDelivered) {
            $contextOutcomes = array_values(array_filter($contextOutcomes, function ($o) {
                return $o['ok'] || $o['error'] !== 'No health information available in the consented date range';
            }));
        }

        return $contextOutcomes;
    }

    /**
     * Push one encrypted entry, retrying transient failures exactly like legacy
     * abdm_execute_data_push_with_retry(). The SAME $payload (same ciphertext, keys, nonce) is re-sent on
     * every attempt; the URL was validated once by the caller and is never re-resolved or relaxed.
     *
     * Retried: HTTP >= 500; connection/timeout errors; HTTP 400 whose body contains ABDM-1024 or ABDM-9999.
     * Final (not retried): any other non-2xx (incl. 401/429/other 4xx) and non-connection exceptions.
     * As in legacy the access token is fetched once and NOT refreshed on 401.
     */
    private function push(array $source, string $ref, string $hiType, string $hipId, string $dataPushUrl, array $target, array $payload): array
    {
        $options = ['allow_redirects' => false];
        if ($target['pin'] !== null && defined('CURLOPT_RESOLVE')) {
            // Connect to the exact address we validated (defeats DNS rebinding between check and use).
            $options['curl'] = [CURLOPT_RESOLVE => [$target['host'] . ':443:' . $target['pin']]];
        }

        // ABDM credentials only ever go to ABDM's own gateway (the sandbox pushes back through it).
        $token = null;
        if ($target['abdm']) {
            try {
                $token = $this->tokenSvc->getAccessToken();
            } catch (Throwable $e) {
                Log::error('ABDM HIU push failed', ['ref' => $ref, 'hi_type' => $hiType, 'error' => $e->getMessage()]);
                return $this->outcome($ref, $hiType, $source, 'Data push failed: ' . substr($e->getMessage(), 0, 300));
            }
            if (!$token) {
                return $this->outcome($ref, $hiType, $source, 'Could not get ABDM access token');
            }
        }

        $maxAttempts = max(1, (int) config('abdm.push_retry_max_attempts', 4));
        $attempt     = 0;

        while (true) {
            $attempt++;
            $code        = null;
            $error       = null;
            $retryReason = null;

            try {
                $request = Http::withoutRedirecting()->timeout(self::PUSH_TIMEOUT)->withOptions($options);
                if ($token !== null) {
                    // New REQUEST-ID/TIMESTAMP per attempt (as legacy); the body never changes.
                    $request = $request->withToken($token)->withHeaders($this->gatewayHeaders($hipId));
                }

                $response = $request->post($dataPushUrl, $payload);
                $code     = $response->status();

                if ($response->successful()) {
                    if ($attempt > 1) {
                        Log::info('ABDM HIU push succeeded after retry', ['ref' => $ref, 'hi_type' => $hiType, 'attempts' => $attempt, 'http_code' => $code]);
                    }
                    return $this->outcome($ref, $hiType, $source, null, $code, $attempt);
                }

                $snippet = trim(substr((string) $response->body(), 0, 300));
                $error   = substr('HTTP ' . $code . ($snippet !== '' ? ' - ' . $snippet : ''), 0, 500);

                if ($code >= 500) {
                    $retryReason = 'HTTP ' . $code . ' server error';
                } elseif ($code === 400 && str_contains($snippet, 'ABDM-1024')) {
                    $retryReason = 'ABDM-1024 Dependent service unavailable';
                } elseif ($code === 400 && str_contains($snippet, 'ABDM-9999') && !stripos($snippet, 'Invalid')) {
                    $retryReason = 'ABDM-9999 Internal error';
                }
            } catch (ConnectionException $e) {
                // Network failure / timeout: transient.
                $error       = 'Data push failed: ' . substr($e->getMessage(), 0, 300);
                $retryReason = 'Connection error';
            } catch (Throwable $e) {
                Log::error('ABDM HIU push failed', ['ref' => $ref, 'hi_type' => $hiType, 'attempt' => $attempt, 'error' => $e->getMessage()]);
                return $this->outcome($ref, $hiType, $source, 'Data push failed: ' . substr($e->getMessage(), 0, 300), null, $attempt);
            }

            if ($retryReason === null || $attempt >= $maxAttempts) {
                Log::warning('ABDM HIU push failed', [
                    'ref' => $ref, 'hi_type' => $hiType, 'attempts' => $attempt, 'http_code' => $code, 'error' => $error,
                ]);
                return $this->outcome($ref, $hiType, $source, $error, $code, $attempt);
            }

            $delayMs = $this->retryDelayMs($attempt);
            Log::warning('ABDM HIU push failed, retrying', [
                'ref' => $ref, 'hi_type' => $hiType, 'reason' => $retryReason, 'attempt' => $attempt,
                'max_attempts' => $maxAttempts, 'retry_in_ms' => $delayMs,
            ]);
            $this->sleepMs($delayMs);
        }
    }

    /** Backoff (ms) to wait after failed attempt number $attempt (1-based); legacy: 2s, 4s, 8s. */
    private function retryDelayMs(int $attempt): int
    {
        $delays = array_values((array) config('abdm.push_retry_delays', []));
        if ($delays === []) {
            return 0;
        }
        $seconds = $delays[$attempt - 1] ?? end($delays);

        return max(0, (int) round(((float) $seconds) * 1000));
    }

    /** Seam so tests never really sleep. */
    protected function sleepMs(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    // ─── Push-URL validation (SSRF guard) ────────────────────────────────────

    /**
     * @return array{0:?string,1:array} [error|null, target{host,pin,abdm}]
     */
    private function validatePushUrl(string $url): array
    {
        $none = ['host' => '', 'pin' => null, 'abdm' => false];
        $p    = parse_url($url);

        if (!is_array($p) || strtolower((string) ($p['scheme'] ?? '')) !== 'https' || empty($p['host'])
            || isset($p['user']) || isset($p['pass'])) {
            return ['Data push URL must be an https URL', $none];
        }
        if (isset($p['port']) && (int) $p['port'] !== 443) {
            return ['Data push URL must use port 443', $none];
        }

        $host = rtrim(strtolower(trim((string) $p['host'], '[]')), '.');

        $trusted = array_map('strtolower', (array) config('abdm.trusted_push_hosts', []));
        if ($trusted !== [] && !in_array($host, $trusted, true)) {
            return ['Data push host is not trusted', $none];
        }

        $isLiteral = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $ips       = $isLiteral ? [$host] : $this->resolveHost($host);
        if ($ips === []) {
            return ['Data push host could not be resolved', $none];
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return ['Data push host resolves to a non-public address', $none];
            }
        }

        $pin = null;
        if (!$isLiteral) {
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    $pin = $ip;
                    break;
                }
            }
        }

        return [null, ['host' => $host, 'pin' => $pin, 'abdm' => $this->isAbdmGatewayHost($host)]];
    }

    /** @return string[] */
    private function resolveHost(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        if (function_exists('dns_get_record')) {
            foreach ((@dns_get_record($host, DNS_AAAA) ?: []) as $rec) {
                if (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }
        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        if (strlen($bin) === 4) {
            // 100.64.0.0/10 (carrier-grade NAT) is not covered by the PHP filter flags.
            return !(ord($bin[0]) === 100 && (ord($bin[1]) & 0xC0) === 64);
        }
        // IPv6: unspecified/loopback, link-local fe80::/10, IPv4-mapped (re-check the embedded v4).
        if (str_starts_with($bin, str_repeat("\0", 15))) {
            return false;
        }
        if (ord($bin[0]) === 0xFE && (ord($bin[1]) & 0xC0) === 0x80) {
            return false;
        }
        if (str_starts_with($bin, str_repeat("\0", 10) . "\xFF\xFF")) {
            return $this->isPublicIp(inet_ntop(substr($bin, 12)));
        }
        return true;
    }

    private function isAbdmGatewayHost(string $host): bool
    {
        if (str_ends_with($host, '.abdm.gov.in') || str_ends_with($host, '.sandbox.abdm.gov.in')) {
            return true;
        }
        foreach (['health_info_notify_url', 'gateway_url', 'callback_base_url'] as $key) {
            $h = parse_url((string) config('abdm.' . $key), PHP_URL_HOST);
            if ($h && strtolower($h) === strtolower($host)) {
                return true;
            }
        }
        return false;
    }

    // ─── Conclusion: notify → ledger → final status ──────────────────────────

    private function failAll(string $transactionId, string $requestId, string $consentId, string $hipId, array $consentRefs, string $reason): array
    {
        $outcomes = [];
        foreach (array_keys($consentRefs) as $ref) {
            $outcomes[] = $this->outcome((string) $ref, null, null, $reason);
        }
        return $this->conclude($transactionId, $requestId, $consentId, $hipId, $outcomes, $reason);
    }

    private function conclude(string $transactionId, string $requestId, string $consentId, string $hipId, array $outcomes, ?string $error): array
    {
        $delivered = count(array_filter($outcomes, fn ($o) => $o['ok']));
        $failed    = count($outcomes) - $delivered;
        $ok        = $delivered > 0;

        $notify = $this->notifyAbdm($transactionId, $consentId, $hipId, $ok, $outcomes);
        $this->recordLedger($transactionId, $consentId, $outcomes, $notify);

        $status = $ok ? self::STATUS_TRANSFERRED : self::STATUS_FAILED;
        $this->markFinal($transactionId, $requestId, $status);

        $result = [
            'success'          => $ok,
            'status'           => $status,
            'delivered'        => $delivered,
            'failed'           => $failed,
            'notify_succeeded' => $notify['success'],
            'outcomes'         => $outcomes,
        ];
        if (!$ok) {
            $result['error'] = $error ?? ($outcomes[0]['error'] ?? 'No entries delivered');
        }
        return $result;
    }

    private function markFinal(string $transactionId, string $requestId, string $status): void
    {
        $q = DB::table('abdm_health_info_requests')->where('transaction_id', $transactionId);
        if ($requestId !== '') {
            $q->where('request_id', $requestId);
        }
        $q->update(['status' => $status, 'processed_at' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * One notification per transfer with a statusResponse per care context
     * (DELIVERED only when every attempted entry of that context was pushed).
     *
     * @return array{success:bool, http_code:?int, error:?string}
     */
    private function notifyAbdm(string $transactionId, string $consentId, string $hipId, bool $sessionOk, array $outcomes): array
    {
        $byRef = [];
        foreach ($outcomes as $o) {
            $byRef[$o['ref']]['delivered'] = ($byRef[$o['ref']]['delivered'] ?? 0) + ($o['ok'] ? 1 : 0);
            $byRef[$o['ref']]['errors']    = $byRef[$o['ref']]['errors'] ?? [];
            if (!$o['ok']) {
                $byRef[$o['ref']]['errors'][] = ($o['hi_type'] ? $o['hi_type'] . ': ' : '') . $o['error'];
            }
        }

        $statusResponses = [];
        foreach ($byRef as $ref => $r) {
            $isDelivered = ($r['delivered'] ?? 0) > 0;
            $statusResponses[] = [
                'careContextReference' => (string) $ref,
                'hiStatus'             => $isDelivered ? 'OK' : 'ERRORED',
                'description'          => $isDelivered ? 'Data transferred successfully' : 'Data transfer failed',
            ];
        }

        if ($statusResponses === []) {
            // Nothing to report on (e.g. unknown consent): the gateway requires at least one status response.
            return ['success' => false, 'http_code' => null, 'error' => 'No care contexts to report'];
        }

        $token = $this->tokenSvc->getAccessToken();
        if (!$token) {
            return ['success' => false, 'http_code' => null, 'error' => 'Could not get ABDM access token'];
        }

        $hipId   = $hipId !== '' ? $hipId : (string) config('abdm.hip_id');
        $payload = [
            'notification' => [
                'consentId'          => $consentId,
                'transactionId'      => $transactionId,
                'doneAt'             => gmdate('Y-m-d\TH:i:s.000\Z', time() - 10),
                'notifier'           => ['type' => 'HIP', 'id' => $hipId],
                'statusNotification' => [
                    'sessionStatus'   => $sessionOk ? 'TRANSFERRED' : 'FAILED',
                    'hipId'           => $hipId,
                    'statusResponses' => $statusResponses,
                ],
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->withHeaders($this->gatewayHeaders($hipId))
                ->timeout(30)
                ->post((string) config('abdm.health_info_notify_url'), $payload);

            if ($response->successful()) {
                return ['success' => true, 'http_code' => $response->status(), 'error' => null];
            }

            $error = $response->json('error.message');
            $body  = $response->body();
            Log::error('ABDM health-info notify rejected by gateway', [
                'http_code'      => $response->status(),
                'body'           => $body,
                'consent_id'     => $consentId,
                'transaction_id' => $transactionId,
            ]);
            return [
                'success'   => false,
                'http_code' => $response->status(),
                'error'     => substr('HTTP ' . $response->status() . ' - ' . (is_string($error) ? $error : $body), 0, 500),
            ];
        } catch (Throwable $e) {
            Log::error('ABDM health-info notify failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'http_code' => null, 'error' => substr($e->getMessage(), 0, 500)];
        }
    }

    private function recordLedger(string $transactionId, string $consentId, array $outcomes, array $notify): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($outcomes as $o) {
            try {
                DB::table('abdm_health_info_pushes')->insert([
                    'opd_id'                        => $o['source'] && $o['source'][0] === 'OPD' ? $o['source'][1] : null,
                    'ipd_id'                        => $o['source'] && $o['source'][0] === 'IPD' ? $o['source'][1] : null,
                    'dcd_id'                        => $o['source'] && $o['source'][0] === 'DCD' ? $o['source'][1] : null,
                    'source_type'                   => $o['source'] ? strtolower($o['source'][0]) : null,
                    'source_id'                     => $o['source'] ? $o['source'][1] : null,
                    'consent_id'                    => $consentId,
                    'transaction_id'                => $transactionId,
                    'care_context_reference_number' => $o['ref'],
                    'hi_type'                       => $o['hi_type'],
                    'push_http_code'                => $o['http_code'],
                    'push_succeeded'                => $o['ok'] ? 1 : 0,
                    'notify_http_code'              => $notify['http_code'],
                    'notify_succeeded'              => $notify['success'] ? 1 : 0,
                    'error_message'                 => $this->ledgerError($o),
                    'notify_error_message'          => $notify['success'] ? null : substr((string) $notify['error'], 0, 500),
                    'created_on'                    => $now,
                ]);
            } catch (Throwable $e) {
                Log::error('ABDM push ledger insert failed', ['ref' => $o['ref'], 'error' => $e->getMessage()]);
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function outcome(string $ref, ?string $hiType, ?array $source, ?string $error, ?int $httpCode = null, int $attempts = 1): array
    {
        return [
            'ref'       => $ref,
            'hi_type'   => $hiType,
            'source'    => $source,
            'ok'        => $error === null,
            'http_code' => $httpCode,
            'error'     => $error,
            'attempts'  => $attempts,
        ];
    }

    /** Ledger text for the final error; the table has no attempts column, so retries are noted in the text. */
    private function ledgerError(array $o): ?string
    {
        if ($o['error'] === null) {
            return null;
        }
        $suffix = ($o['attempts'] ?? 1) > 1 ? ' [after ' . $o['attempts'] . ' attempts]' : '';

        return substr($o['error'], 0, 500 - strlen($suffix)) . $suffix;
    }

    private function gatewayHeaders(string $hipId): array
    {
        return [
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP'  => now()->utc()->subSeconds(15)->format('Y-m-d\TH:i:s.v\Z'),
            'X-CM-ID'    => 'sbx',
            'X-HIP-ID'   => $hipId !== '' ? $hipId : (string) config('abdm.hip_id'),
        ];
    }

    /** Consent bounds are stored as naive UTC datetimes; hand them to the builders as explicit UTC ISO-8601. */
    private function toUtcIso($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable) {
            return (string) $value; // the builder rejects it with InvalidArgumentException
        }
    }
}
