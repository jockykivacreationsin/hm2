<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** Escaping and template helpers of the PDF report renderers. */
final class Html
{
    /** Escapes a database/user value for HTML text or attribute content (null becomes ''). */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Fills a `{{name}}` template. Replacement is single-pass, so a value that itself contains `{{...}}` is not expanded.
     *
     * @param array<string, string> $values name => already escaped HTML
     */
    public static function fill(string $template, array $values): string
    {
        $replacements = [];
        foreach ($values as $name => $value) {
            $replacements['{{' . $name . '}}'] = $value;
        }

        return strtr($template, $replacements);
    }
}
