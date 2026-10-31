<?php

namespace App\Support;

use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The UHID shown for a patient: settings.uhid_prefix + patients.id zero-padded to settings.uhid_padding digits +
 * settings.uhid_suffix  (defaults: "BMH" + 7 digits, e.g. BMH0000014). It is derived, never stored, so changing the
 * settings changes how every patient's UHID is printed; patients.id itself never changes.
 *
 * The prefix and the suffix may contain the year tokens {YY} (26) and {YYYY} (2026). The year is the year the patient
 * was REGISTERED (patients.created_on), not today's: a UHID never changes once issued. e.g. prefix "BMH/{YY}/" gives
 * BMH/26/0000014 for a patient registered in 2026 and BMH/27/0000099 for one registered in 2027.
 *
 * ABDM patient references (CareContextService::patientRef, e.g. in care contexts and consents) are a different
 * identifier that was already issued to ABDM and deliberately do NOT follow this setting.
 */
final class Uhid
{
    public const DEFAULT_PREFIX = 'BMH';
    public const DEFAULT_SUFFIX = '';
    public const DEFAULT_PADDING = 7;
    public const MAX_PADDING = 12;

    /** Characters allowed in the prefix/suffix, plus the two year tokens (printed on documents, typed into search). */
    public const PART_PATTERN = '/^(?:[A-Za-z0-9\-_\/.]|\{YYYY\}|\{YY\})*$/';

    /**
     * @param  string|\DateTimeInterface|null  $registeredAt  patients.created_on; only needed when the prefix/suffix has a
     *                                                       year token, and looked up from the patient when not given
     */
    public static function format(int|string|null $patientId, string|\DateTimeInterface|null $registeredAt = null): string
    {
        [$prefix, $suffix, $padding] = self::parts();
        $id = (int) $patientId;

        if (self::hasYearToken($prefix . $suffix)) {
            $year = self::registrationYear($id, $registeredAt);
            $prefix = self::withYear($prefix, $year);
            $suffix = self::withYear($suffix, $year);
        }

        return $prefix . str_pad((string) $id, $padding, '0', STR_PAD_LEFT) . $suffix;
    }

    /**
     * The id inside a typed UHID ("BMH0000014", "uhid-14/26" ...) or null when the text does not have the configured
     * prefix/suffix around digits. Case-insensitive, so desk staff can type it any way. A year token matches any year
     * (the id decides the patient).
     */
    public static function parse(string $uhid): ?int
    {
        [$prefix, $suffix] = self::parts();

        $pattern = '/^' . self::quotePart($prefix) . '(\d+)' . self::quotePart($suffix) . '$/i';

        return preg_match($pattern, trim($uhid), $m) ? (int) $m[1] : null;
    }

    /** @return array{0: string, 1: string, 2: int} prefix, suffix, padding (year tokens still in place) */
    public static function parts(): array
    {
        $settings = app(SettingsService::class)->getSettings();

        $prefix = self::clean($settings['uhid_prefix'] ?? null, self::DEFAULT_PREFIX);
        $suffix = self::clean($settings['uhid_suffix'] ?? null, self::DEFAULT_SUFFIX);
        $padding = $settings['uhid_padding'] ?? null;
        $padding = is_numeric($padding) ? max(0, min(self::MAX_PADDING, (int) $padding)) : self::DEFAULT_PADDING;

        return [$prefix, $suffix, $padding];
    }

    private static function hasYearToken(string $text): bool
    {
        return str_contains($text, '{YY');
    }

    private static function withYear(string $part, int $year): string
    {
        return strtr($part, ['{YYYY}' => (string) $year, '{YY}' => substr((string) $year, -2)]);
    }

    /** Year of patients.created_on (given, or read from the patient); this year when it cannot be determined. */
    private static function registrationYear(int $patientId, string|\DateTimeInterface|null $registeredAt): int
    {
        $registeredAt ??= $patientId > 0 ? DB::table('patients')->where('id', $patientId)->value('created_on') : null;

        try {
            return $registeredAt ? Carbon::parse($registeredAt)->year : now()->year;
        } catch (\Throwable) {
            return now()->year;
        }
    }

    /** preg_quote the literal text, but let a year token match digits. */
    private static function quotePart(string $part): string
    {
        $pieces = preg_split('/(\{YYYY\}|\{YY\})/', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(fn (string $piece) => match ($piece) {
            '{YYYY}' => '\d{4}',
            '{YY}' => '\d{2}',
            default => preg_quote($piece, '/'),
        }, $pieces));
    }

    /** A stored part that is missing falls back to the default; one with unexpected characters is not printed as is. */
    private static function clean(mixed $value, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        $value = (string) $value;

        return preg_match(self::PART_PATTERN, $value) === 1 ? $value : $default;
    }
}
