<?php

namespace App\Services\Abdm\Fhir\Opd;

/** Tiny HTML helpers shared by the FHIR narratives and the PDF renderers. */
final class Xhtml
{
    /** htmlspecialchars() with the flags the legacy code always used; null/int values are stringified. */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** Wraps a narrative fragment in the FHIR XHTML div. */
    public static function div(string $inner): string
    {
        return '<div xmlns="http://www.w3.org/1999/xhtml">' . $inner . '</div>';
    }

    /** True for the radiology services (by category, or by the department name containing "radiology"). */
    public static function isRadiology(?string $category, ?string $departmentName): bool
    {
        return (strtolower(trim((string) $category)) === 'radiology')
            || (stripos((string) $departmentName, 'radiology') !== false);
    }
}
