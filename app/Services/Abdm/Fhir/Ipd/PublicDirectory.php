<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** mPDF resolves the relative `assets/...` image paths of the reports against the working directory. */
final class PublicDirectory
{
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        $previous = getcwd();
        chdir(public_path());
        try {
            return $callback();
        } finally {
            if ($previous !== false) {
                chdir($previous);
            }
        }
    }
}
