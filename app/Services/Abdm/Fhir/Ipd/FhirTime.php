<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** UTC ISO-8601 timestamps the way the IPD bundles have always formatted them. */
final class FhirTime
{
    private const FORMAT = 'Y-m-d\TH:i:sP';

    /** Converts a stored database date-time to an offset timestamp. */
    public static function utc(?string $dateTime): string
    {
        return gmdate(self::FORMAT, strtotime($dateTime));
    }

    public static function now(): string
    {
        return gmdate(self::FORMAT);
    }
}
