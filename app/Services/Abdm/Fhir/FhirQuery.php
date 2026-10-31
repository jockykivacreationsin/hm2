<?php

namespace App\Services\Abdm\Fhir;

use Illuminate\Support\Facades\DB;

/**
 * Read-only queries for the FHIR builders, on Laravel's database connection.
 *
 * The builders were written against mysqli prepared statements and iterate rows as plain associative arrays, so this
 * returns exactly that (not stdClass objects). `$types` mirrors mysqli's bind-type string ('i' int, 'd' float, 's' string):
 * a non-null value is cast to the declared type before binding, which is what mysqli did, so numeric strings bind as
 * numbers and `LIMIT ?` keeps working. NULL stays NULL.
 */
final class FhirQuery
{
    /** @return array<int, array<string, mixed>> every row, possibly empty */
    public static function rows(string $sql, string $types = '', array $params = []): array
    {
        $bindings = self::cast($types, $params);

        return array_map(static fn ($row) => (array) $row, DB::select($sql, $bindings));
    }

    /** @return array<string, mixed>|null the first row, or null when there is none (like mysqli_fetch_assoc on an empty result) */
    public static function first(string $sql, string $types = '', array $params = []): ?array
    {
        return self::rows($sql, $types, $params)[0] ?? null;
    }

    private static function cast(string $types, array $params): array
    {
        $out = [];
        foreach (array_values($params) as $i => $value) {
            $type = $types[$i] ?? 's';
            $out[] = $value === null ? null : match ($type) {
                'i' => (int) $value,
                'd' => (float) $value,
                default => (string) $value,
            };
        }

        return $out;
    }
}
