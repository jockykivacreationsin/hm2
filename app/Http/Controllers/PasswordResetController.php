<?php

namespace App\Http\Controllers;

use App\Mail\ForgotPasswordMail;
use App\Models\StaffMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    /** Token expires after this many minutes. */
    private const TTL_MINUTES = 60;

    // ── Step 1: show the email form ───────────────────────────

    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    // ── Step 2: validate email, store token, send link ────────

    public function sendLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|exists:staff_members,email',
        ], [
            'email.exists' => 'No active staff account found with this email address.',
        ]);

        $email = strtolower(trim($request->input('email')));

        $staff = StaffMember::where('email', $email)->where('active', 'Yes')->first();

        if ($staff) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->upsert(
                [
                    'email'      => $email,
                    'token'      => Hash::make($token),
                    'created_at' => now(),
                ],
                ['email'],
                ['token', 'created_at']
            );

            Mail::to($email)->send(new ForgotPasswordMail($staff->staff_name, $token));
        }

        return back()->with('status', 'If that email belongs to an active staff account, a password reset link has been sent.');
    }

    // ── Step 3: show the new-password form ────────────────────

    public function showResetForm(string $token)
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    // ── Step 4: validate token, update password ───────────────

    public function reset(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email|max:255',
            'token'                 => 'required|string',
            'password'              => ['required', 'confirmed', Password::defaults()],
            'password_confirmation' => 'required|string',
        ]);

        $email = strtolower(trim($request->input('email')));

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record) {
            return back()->withErrors(['email' => 'This password reset link is invalid.']);
        }

        // Check token age.
        if (now()->diffInMinutes($record->created_at) > self::TTL_MINUTES) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return back()->withErrors(['email' => 'This password reset link has expired. Please request a new one.']);
        }

        // Check token value.
        if (! Hash::check($request->input('token'), $record->token)) {
            return back()->withErrors(['email' => 'This password reset link is invalid.']);
        }

        $staff = StaffMember::where('email', $email)->where('active', 'Yes')->first();

        if (! $staff) {
            return back()->withErrors(['email' => 'No active staff account found for this email.']);
        }

        $staff->password = Hash::make($request->input('password'));
        $staff->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return redirect()->route('login')->with('status', 'Password reset successfully. You can now log in with your new password.');
    }
}
