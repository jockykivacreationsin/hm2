<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared-key guard for the staff QR-scanner kiosk endpoints (api/staff-members, api/staff/attendance).
 *
 * The kiosk has no login, so these routes used to be open to anyone who could reach the site: anyone could
 * list staff names/photos or mark attendance for any numeric staff_id. The kiosk must now send the key from
 * KIOSK_API_KEY (config services.kiosk.key) as either header:
 *
 *     X-Kiosk-Key: <key>
 *     Authorization: Bearer <key>
 *
 * Fails closed: if no key is configured the endpoints are refused (503), never silently left open.
 * The comparison is constant-time and a rejection never logs the key that was supplied.
 */
class VerifyKioskKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.kiosk.key', '');

        if ($expected === '') {
            Log::error('Kiosk endpoint refused: KIOSK_API_KEY is not configured', ['path' => $request->path()]);

            return new JsonResponse(['success' => false, 'message' => 'Kiosk access is not configured'], 503);
        }

        $supplied = (string) ($request->header('X-Kiosk-Key') ?: $request->bearerToken() ?: '');

        if ($supplied === '' || !hash_equals($expected, $supplied)) {
            Log::warning('Kiosk endpoint rejected: missing or wrong key', [
                'path' => $request->path(),
                'ip'   => $request->ip(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
