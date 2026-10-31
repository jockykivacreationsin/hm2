<?php

namespace App\Http\Controllers;

use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photo uploads (the Slim cropper on the patient and staff forms) and the route that serves stored images.
 *
 * Only `<known folder>/<bare file name>` is ever read; anything else is a 404. Images are sent with `nosniff` and an
 * image content type, never as HTML, so an unexpected upload cannot execute in the browser.
 */
class UploadController extends Controller
{
    private const IMAGE_TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    ];

    /** Detected image type => file extension. Anything else (svg, php, html ...) is refused. */
    private const RASTER = [
        IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp',
    ];

    private const MAX_BYTES = 4 * 1024 * 1024;

    /** Upload target per `{kind}` of the route: folder and file-name prefix. */
    private const KINDS = [
        'patient' => [Uploads::PATIENTS, 'patient_'],
        'staff' => [Uploads::STAFF, 'staff_'],
    ];

    public function show(string $folder, string $file): StreamedResponse
    {
        abort_unless(Uploads::exists($folder, $file), 404);

        $type = self::IMAGE_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
        abort_if($type === null, 404);

        return Storage::disk(Uploads::DISK)->response($folder . '/' . $file, $file, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], 'inline');
    }

    /**
     * Receives what the Slim cropper posts (`slim[]` = a JSON string whose `output.image` is a base64 data URI),
     * checks it is a real raster image, stores it under a server-chosen name and answers in the shape Slim expects:
     * `{status: 'success', file: <name>}`. The form then submits that bare name in its `photo` field.
     */
    public function storePhoto(Request $request, string $kind): JsonResponse
    {
        [$folder, $prefix] = self::KINDS[$kind] ?? abort(404);

        $slim = $request->input('slim');
        $payload = is_array($slim) ? ($slim[0] ?? null) : $slim;
        $decoded = is_string($payload) ? json_decode($payload, true) : null;
        $image = is_array($decoded) ? ($decoded['output']['image'] ?? $decoded['input']['image'] ?? null) : null;

        if (! is_string($image) || $image === '') {
            return $this->failure('No image data');
        }

        $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image), true);
        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_BYTES) {
            return $this->failure('Invalid or too large image');
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false || ! isset(self::RASTER[$info[2]])) {
            return $this->failure('The file is not a JPEG, PNG, GIF or WebP image');
        }

        $name = $prefix . bin2hex(random_bytes(8)) . '.' . self::RASTER[$info[2]];
        if (! Uploads::put($folder, $name, $binary)) {
            return $this->failure('Failed to save the image');
        }

        return response()->json(['status' => 'success', 'file' => $name, 'path' => Uploads::url($folder, $name)]);
    }

    private function failure(string $message): JsonResponse
    {
        return response()->json(['status' => 'failure', 'message' => $message]);
    }
}
