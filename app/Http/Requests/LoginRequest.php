<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The staff login form: an e-mail address OR a mobile number, and a password.
 *
 * Failed attempts are throttled per user name + IP address (Laravel's usual login throttle), so a password cannot be
 * guessed by hammering the endpoint. A refused attempt always answers {result: '0'} - the form never learns whether the
 * user name or the password was wrong.
 */
class LoginRequest extends FormRequest
{
    /** Failed attempts allowed per user name + IP before the login is locked for a while. */
    public const MAX_ATTEMPTS = 5;

    /** Seconds the lock lasts. */
    public const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'patient_category_id' => ['nullable', 'integer'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** The "Remember Me" box: keep this device signed in after the browser session ends. */
    public function wantsRemember(): bool
    {
        return $this->boolean('remember');
    }

    public function userName(): string
    {
        return trim((string) $this->input('user_name', ''));
    }

    public function plainPassword(): string
    {
        return (string) $this->input('password', '');
    }

    /** One bucket per user name and address, so one person's typos never lock out another. */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->userName()) . '|' . $this->ip());
    }

    public function isThrottled(): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS);
    }

    public function secondsUntilUnlocked(): int
    {
        return RateLimiter::availableIn($this->throttleKey());
    }

    public function recordFailure(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    public function clearFailures(): void
    {
        RateLimiter::clear($this->throttleKey());
    }
}
