<?php

namespace App\Http\Middleware;

use App\Support\RememberedLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isAuthenticated() || ! RememberedLogin::restore($request)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Your session has expired. Please login again.',
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }

    /**
     * A user logged in through the `web` guard is authenticated. The session key `logged_in_user_id` (written at login) is
     * still honoured so screens and tests that establish a user only through the session keep working while the remaining
     * `session('logged_in_user_id')` reads are moved to Auth::id().
     */
    private function isAuthenticated(): bool
    {
        return Auth::check() || (bool) session('logged_in_user_id');
    }
}
