<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * What has to happen when a device is signed in again from the "Remember Me" cookie rather than from a live session.
 *
 * Laravel restores the staff member itself, but this application also keeps the chosen patient category (a login-form
 * choice that most list screens filter by) in the session, and a restored device starts with an empty one. The category is
 * therefore also kept in an encrypted cookie that lives exactly as long as the remember cookie.
 *
 * A device that was remembered before its staff member was deactivated must not get back in, so a restored user is
 * checked for `active` here. (A normal login already does this in AuthController.)
 */
final class RememberedLogin
{
    public const CATEGORY_COOKIE = 'bmh_pcat';

    /**
     * Call after the guard has had the chance to restore a user. Returns false when the restored user must NOT stay signed
     * in (they have been signed out again); true otherwise, including when nobody is signed in at all.
     */
    public static function restore(Request $request): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return true;
        }

        if (Auth::viaRemember() && (string) ($user->active ?? 'Yes') !== 'Yes') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return false;
        }

        if (! $request->session()->has('patient_category_id')) {
            $category = $request->cookie(self::CATEGORY_COOKIE);

            if (is_string($category) && ctype_digit($category)) {
                $request->session()->put([
                    'patient_category_id' => (int) $category,
                    'logged_in_patient_category_id' => (int) $category,
                ]);
            }
        }

        // The keys the rest of the application still reads from the session (see AuthController::rememberSessionContext).
        foreach (['logged_in_user_id' => $user->getAuthIdentifier(), 'user_role_id' => (int) ($user->user_role_id ?? 0), 'display_name' => $user->staff_name ?? null] as $key => $value) {
            if (! $request->session()->has($key) && $value !== null) {
                $request->session()->put($key, $value);
            }
        }

        return true;
    }
}
