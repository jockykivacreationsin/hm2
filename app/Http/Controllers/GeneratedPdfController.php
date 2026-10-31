<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a PDF that an earlier request generated onto the private `reports` disk (collection reports ...).
 *
 * Only a bare file name directly inside the disk root is ever served: anything with a directory part, a traversal
 * sequence or a different extension is a 404, however the name arrives.
 */
class GeneratedPdfController extends Controller
{
    public const DISK = 'reports';

    public function __invoke(string $filename): StreamedResponse
    {
        abort_unless($this->isPlainPdfName($filename), 404);

        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($filename), 404);

        app(AuditLogger::class)->downloadPdf($filename);

        return $disk->response($filename, $filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function isPlainPdfName(string $filename): bool
    {
        return $filename === basename($filename)
            && preg_match('/^[A-Za-z0-9._\- ]+\.pdf$/', $filename) === 1
            && ! str_contains($filename, '..');
    }
}
