<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every ABDM gateway callback before its handler reads the body.
 *
 * A callback URL has to be reachable by ABDM, which makes it reachable by everyone, so a POST arriving
 * there proves nothing. This implements the three checks ABDM itself prescribes ("Proving a callback came
 * from ABDM", docs.abdm.gov.in/docs/hiecm/v3/concepts/callback-authenticity) and fails closed:
 *
 *   1. A bearer token is present (and shaped like a JWT that has not expired).
 *   2. A callback that ANSWERS one of our calls carries a response/requestId that matches a request this
 *      system actually sent - and, where the data allows, one that has not already been settled.
 *   3. X-HIP-ID / X-HIU-ID names a facility this bridge serves.
 *
 * What this deliberately does NOT do: verify the token's signature. ABDM publishes no key source for it and
 * explicitly says not to invent a check against one. Presence + correlation + facility are the controls
 * ABDM documents; (2) is the strong one, because a forger cannot know a REQUEST-ID we generated.
 *
 * Route usage:  ->middleware('abdm.callback:answers=link_token')   a callback answering a call of ours
 *               ->middleware('abdm.callback:facility=hip')          an unsolicited callback (facility only)
 * Mode is config('abdm.callback_auth'): 'enforce' (default) rejects, 'log' only records what it would have
 * rejected (for diagnosing a sandbox integration), 'off' disables the guard.
 */
class VerifyAbdmCallback
{
    public function handle(Request $request, Closure $next, string ...$rules): Response
    {
        $mode = (string) config('abdm.callback_auth', 'enforce');
        if ($mode === 'off') {
            return $next($request);
        }

        $rejection = $this->check($request, $this->parseRules($rules));
        if ($rejection === null) {
            return $next($request);
        }

        // Never log the body (patient data) or the token - only what was wrong and where it came from.
        Log::warning('ABDM callback rejected', [
            'reason' => $rejection['reason'],
            'path'   => $request->path(),
            'ip'     => $request->ip(),
            'mode'   => $mode,
        ]);

        if ($mode === 'log') {
            return $next($request);
        }

        // A callback that was never ours (foreign facility, or an answer to a request we never sent) is
        // dropped with the same 200 {"status":"ignored"} the legacy handlers return: it is not an error on
        // our side, and ABDM should not keep retrying something that was never ours.
        if ($rejection['status'] === 200) {
            return new JsonResponse(['status' => 'ignored'], 200);
        }

        return new JsonResponse(['error' => ['code' => $rejection['code'], 'message' => $rejection['message']]], $rejection['status']);
    }

    /** @return array{answers?:string, facility?:string} */
    private function parseRules(array $rules): array
    {
        $parsed = [];
        foreach ($rules as $rule) {
            [$key, $value] = array_pad(explode('=', $rule, 2), 2, '');
            $parsed[$key] = $value;
        }

        return $parsed;
    }

    /** @return array{code:string,message:string,status:int,reason:string}|null */
    private function check(Request $request, array $rules): ?array
    {
        // 1. Bearer token present and plausible.
        $token = $this->bearerToken($request);
        if ($token === null) {
            return $this->reject('MISSING_TOKEN', 'Bearer token required', 401, 'missing or malformed Authorization header');
        }
        if (!$this->looksLikeUsableJwt($token)) {
            return $this->reject('INVALID_TOKEN', 'Bearer token is not valid', 401, 'token is not a well-formed, unexpired JWT');
        }

        // 3. Facility header (checked before correlation so a callback for someone else's service is dropped
        //    without touching our tables).
        //    A header that IS present must name one of our facilities - a callback meant for someone else's
        //    service is dropped. A header that is ABSENT is allowed, exactly as the working legacy bridge
        //    allows it (abdm_request_is_for_this_facility() returns true for a missing header): older sandbox
        //    flows do not always send it, and for HIU callbacks the legacy handlers never look at it at all.
        //    Requiring it would be stricter than the system we are matching and could reject genuine callbacks.
        //    Set ABDM_CALLBACK_REQUIRE_FACILITY=true to demand it on unsolicited HIP callbacks once you have
        //    confirmed in the sandbox that ABDM always sends it.
        if (isset($rules['facility']) || isset($rules['answers'])) {
            $required = ($rules['facility'] ?? '') === 'hip' && (bool) config('abdm.callback_require_facility', false);
            $facility = $this->facilityHeader($request, $rules['facility'] ?? 'hip');
            if ($facility === null && $required) {
                return $this->reject('MISSING_FACILITY', 'Facility header required', 400, 'no X-HIP-ID / X-HIU-ID header');
            }
            if ($facility !== null && !$this->isOurFacility($facility)) {
                // 200 + ignored, like the legacy misroute handling: it is not an error on our side and ABDM
                // should not keep retrying a callback that was never ours.
                return $this->reject('FOREIGN_FACILITY', 'Not served here', 200, 'facility header names a facility this bridge does not serve');
            }
        }

        // 2. An answer must answer something we sent.
        if (isset($rules['answers']) && $rules['answers'] !== '') {
            $requestId = $this->answeredRequestId($request);
            if ($requestId === null) {
                return $this->reject('MISSING_REQUEST_ID', 'response.requestId required', 400, 'answer callback carries no request id');
            }
            if (!$this->wasSentByUs($rules['answers'], $requestId)) {
                return $this->reject('UNKNOWN_REQUEST', 'Request not recognised', 200, 'request id does not match any request this system sent');
            }
        }

        return null;
    }

    private function reject(string $code, string $message, int $status, string $reason): array
    {
        return compact('code', 'message', 'status', 'reason');
    }

    private function bearerToken(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization', ''));
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * Three base64url segments with a decodable JSON header and payload, and an `exp` that is not in the
     * past when one is given. This is shape and freshness only - NOT signature verification (see class doc).
     */
    private function looksLikeUsableJwt(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || strlen($token) > 8192) {
            return false;
        }
        foreach ($parts as $part) {
            if ($part === '' || !preg_match('/^[A-Za-z0-9_\-]+={0,2}$/', $part)) {
                return false;
            }
        }
        $header  = $this->decodeSegment($parts[0]);
        $payload = $this->decodeSegment($parts[1]);
        if ($header === null || $payload === null) {
            return false;
        }
        if (isset($payload['exp'])) {
            if (!is_numeric($payload['exp']) || (int) $payload['exp'] < time() - (int) config('abdm.callback_clock_skew', 120)) {
                return false;
            }
        }

        return true;
    }

    private function decodeSegment(string $segment): ?array
    {
        $json = base64_decode(strtr(rtrim($segment, '='), '-_', '+/') . str_repeat('=', (4 - strlen(rtrim($segment, '=')) % 4) % 4), true);
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    private function facilityHeader(Request $request, string $kind): ?string
    {
        $names = $kind === 'hiu' ? ['X-HIU-ID', 'X-HIP-ID'] : ['X-HIP-ID', 'X-HIU-ID'];
        foreach ($names as $name) {
            $value = trim((string) $request->header($name, ''));
            if ($value !== '') {
                return mb_substr($value, 0, 64);
            }
        }

        return null;
    }

    private function isOurFacility(string $id): bool
    {
        $ours = array_filter([
            (string) config('abdm.hip_id', ''),
            (string) config('abdm.hiu_id', ''),
            ...array_map('strval', array_keys((array) config('abdm.known_facilities', []))),
        ]);
        foreach ($ours as $own) {
            if (strcasecmp($id, $own) === 0) {
                return true;
            }
        }

        return false;
    }

    /** The id ABDM echoes back: top-level requestId, or response.requestId / resp.requestId. */
    private function answeredRequestId(Request $request): ?string
    {
        foreach (['requestId', 'response.requestId', 'resp.requestId'] as $key) {
            $value = $request->input($key);
            if (is_string($value) && preg_match('/^[A-Za-z0-9._:\-]{1,100}$/', trim($value))) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Does a request we sent carry this id? Each kind maps to the table that records the REQUEST-ID we
     * generated. Where the row has a settled state, a repeat of an already-handled answer is not accepted.
     */
    private function wasSentByUs(string $kind, string $requestId): bool
    {
        return match ($kind) {
            // pending until the token arrives; the legacy flow deletes/settles the row afterwards
            'link_token'      => DB::table('abdm_link_tokens')->where('request_id', $requestId)->where('status', 'pending')->exists(),
            'care_context'    => DB::table('abdm_care_contexts')->where('request_id', $requestId)->where('status', 'pending')->exists(),
            'hiu_consent'     => DB::table('abdm_hiu_consent_requests')->where('request_id', $requestId)->exists(),
            'hiu_health_info' => DB::table('abdm_hiu_key_material')->where('request_id', $requestId)->exists(),
            default           => false, // an unknown rule fails closed
        };
    }
}
