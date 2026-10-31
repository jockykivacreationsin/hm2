<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Everything the application stores for users: patient and staff photos, hospital logos, charge images.
 *
 * Files live on the private `uploads` disk (storage/app/uploads/<folder>/<file>) and are served by the authenticated
 * `uploads.show` route. The database keeps only the bare file name, exactly as it always has, so existing rows keep working.
 */
final class Uploads
{
    public const DISK = 'uploads';

    public const PATIENTS = 'patients';
    public const STAFF = 'staffmembers';
    public const SETTINGS = 'settings';
    public const CHARGES = 'charges';

    public const FOLDERS = [self::PATIENTS, self::STAFF, self::SETTINGS, self::CHARGES];

    public const PLACEHOLDER = 'assets/images/no_image.png';

    /** A bare file name: no directory part, no traversal, no leading dot. */
    public static function isSafeName(?string $name): bool
    {
        return is_string($name)
            && $name !== ''
            && $name === basename(str_replace('\\', '/', $name))
            && ! str_starts_with($name, '.')
            && ! str_contains($name, '..')
            && ! str_contains($name, "\0");
    }

    public static function exists(string $folder, ?string $name): bool
    {
        return in_array($folder, self::FOLDERS, true)
            && self::isSafeName($name)
            && Storage::disk(self::DISK)->exists($folder . '/' . $name);
    }

    /** Absolute path of an existing upload, or null (for code that needs a real file, e.g. the PDF letterhead). */
    public static function path(string $folder, ?string $name): ?string
    {
        return self::exists($folder, $name) ? Storage::disk(self::DISK)->path($folder . '/' . $name) : null;
    }

    /** URL for an <img>. Falls back to the placeholder picture when there is no (safe) file name. */
    public static function url(string $folder, ?string $name, string $fallback = self::PLACEHOLDER): string
    {
        if (! in_array($folder, self::FOLDERS, true) || ! self::isSafeName($name)) {
            return asset($fallback);
        }

        return route('uploads.show', ['folder' => $folder, 'file' => $name]);
    }

    /** Same as url() but empty-file aware and checking the file is really there (the dashboard hides missing photos). */
    public static function urlIfExists(string $folder, ?string $name): ?string
    {
        return self::exists($folder, $name) ? self::url($folder, $name) : null;
    }

    /** Base URL for scripts that append the file name themselves (`BASE + data.photo`). Ends with a slash. */
    public static function baseUrl(string $folder): string
    {
        $sample = route('uploads.show', ['folder' => $folder, 'file' => 'x']);

        return substr($sample, 0, -1);
    }

    public static function put(string $folder, string $name, string $contents): bool
    {
        abort_unless(in_array($folder, self::FOLDERS, true) && self::isSafeName($name), 500, 'Unsafe upload target.');

        return Storage::disk(self::DISK)->put($folder . '/' . $name, $contents);
    }

    /** Stores an uploaded file under a server-chosen name and returns that name. */
    public static function putUploaded(string $folder, UploadedFile $file, string $prefix, ?string $extension = null): string
    {
        $extension = strtolower($extension ?: ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'));
        $name = $prefix . bin2hex(random_bytes(8)) . '.' . preg_replace('/[^a-z0-9]/', '', $extension);

        self::put($folder, $name, (string) file_get_contents($file->getRealPath()));

        return $name;
    }

    /**
     * Decodes a base64 data URL (data:image/jpeg;base64,...) and saves it as a new file.
     * Returns the bare filename, or null if the data URL is invalid or the MIME type is not an image.
     */
    public static function putBase64(string $folder, string $dataUrl, string $prefix): ?string
    {
        // data:[<mediatype>][;base64],<data>
        if (!preg_match('/^data:(image\/(jpeg|jpg|png|gif|webp));base64,(.+)$/i', $dataUrl, $m)) {
            return null;
        }

        $extension = str_replace('jpeg', 'jpg', strtolower($m[2]));
        $raw       = base64_decode($m[3], strict: true);

        if ($raw === false || strlen($raw) < 8) {
            return null;
        }

        $name = $prefix . bin2hex(random_bytes(8)) . '.' . $extension;
        self::put($folder, $name, $raw);

        return $name;
    }

    /** Stores an uploaded file keeping a sanitised version of its own name (logos), made unique with a timestamp. */
    public static function putUploadedKeepingName(string $folder, UploadedFile $file): string
    {
        $base = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), '_') ?: 'file';
        $extension = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $file->guessExtension() ?: $file->getClientOriginalExtension()) ?: 'bin');
        $name = time() . '_' . $base . '.' . $extension;

        self::put($folder, $name, (string) file_get_contents($file->getRealPath()));

        return $name;
    }
}
