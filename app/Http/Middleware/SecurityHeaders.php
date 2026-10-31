<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security-related HTTP response headers to every response.
 *
 * Headers set:
 *   Content-Security-Policy   — restricts sources for scripts, styles, fonts, images, frames
 *   Strict-Transport-Security — forces HTTPS for 1 year (only on HTTPS responses)
 *   X-Frame-Options           — prevents clickjacking
 *   X-Content-Type-Options    — prevents MIME-type sniffing
 *   X-XSS-Protection          — enables legacy browser XSS filter
 *   Referrer-Policy           — limits referrer leakage
 *   Permissions-Policy        — disables unused browser features
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // ── Content-Security-Policy ────────────────────────────────────────────
        // All JS/CSS/images are served locally.
        // Google Fonts (fonts.googleapis.com, fonts.gstatic.com) are the only
        // external style/font sources used in layouts and the login page.
        // 'unsafe-inline' for style is required by the existing Blade templates
        // (inline <style> blocks and style="" attributes throughout the views).
        // 'unsafe-inline' for script is required by existing Blade inline <script>
        // blocks; remove once all inline scripts are moved to external .js files.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        // ── HSTS — only send over HTTPS to avoid breaking HTTP dev environments ─
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // ── Clickjacking protection ────────────────────────────────────────────
        $response->headers->set('X-Frame-Options', 'DENY');

        // ── MIME sniffing protection ───────────────────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ── Legacy browser XSS filter ─────────────────────────────────────────
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // ── Referrer leakage ──────────────────────────────────────────────────
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ── Permissions Policy — disable unused browser APIs ──────────────────
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        return $response;
    }
}
