<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Who is logged in, for code that stamps `user_id` columns, writes audit logs or decides what the user may see.
 *
 * The Laravel guard is the source of truth. The session keys written at login (`logged_in_user_id`, `user_role_id`,
 * `display_name`) are the fallback, so a request that establishes a user only through the session still resolves - the
 * same rule the `auth` middleware uses.
 */
final class CurrentUser
{
    /** The logged-in staff member's id, or null when nobody is logged in. */
    public static function id(): ?int
    {
        $id = Auth::id() ?? session('logged_in_user_id');

        return $id === null || $id === '' ? null : (int) $id;
    }

    /**
     * The role that applies to this request, 0 when nobody is logged in.
     *
     * A guard user is read at their CURRENT role, so demoting someone takes effect on their next request instead of when
     * they next log in.
     */
    public static function roleId(): int
    {
        $user = Auth::user();

        if ($user !== null && isset($user->user_role_id)) {
            return (int) $user->user_role_id;
        }

        return (int) session('user_role_id', 0);
    }

    /** The name shown in the header; the session copy written at login is the fallback. */
    public static function displayName(): ?string
    {
        $user = Auth::user();

        if ($user !== null && isset($user->staff_name) && $user->staff_name !== '') {
            return (string) $user->staff_name;
        }

        $name = session('display_name');

        return $name === null || $name === '' ? null : (string) $name;
    }
}
