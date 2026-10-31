<?php

namespace App\Console\Commands;

use App\Support\Uploads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Copies photos and logos that earlier versions kept in public/assets/uploads onto the private `uploads` disk.
 * Safe to run repeatedly: files that already exist on the disk are left alone, and the source is never modified.
 */
class ImportUploads extends Command
{
    protected $signature = 'uploads:import {--from= : Folder holding patients/, staffmembers/, settings/, charges/ (default: public/assets/uploads)}';

    protected $description = 'Copy existing uploaded photos and logos into storage/app/uploads';

    public function handle(): int
    {
        $from = rtrim((string) ($this->option('from') ?: public_path('assets/uploads')), '/\\');

        if (! is_dir($from)) {
            $this->warn("Nothing to import: {$from} does not exist.");

            return self::SUCCESS;
        }

        $disk = Storage::disk(Uploads::DISK);
        $copied = $skipped = $refused = 0;

        foreach (Uploads::FOLDERS as $folder) {
            $dir = $from . DIRECTORY_SEPARATOR . $folder;
            if (! is_dir($dir)) {
                continue;
            }

            foreach (scandir($dir) ?: [] as $name) {
                $path = $dir . DIRECTORY_SEPARATOR . $name;

                if (! is_file($path)) {
                    continue;
                }

                // .htaccess, .gitkeep and anything else that is not a plain image-style name stays behind.
                if (! Uploads::isSafeName($name) || ! preg_match('/\.(jpe?g|png|gif|webp)$/i', $name)) {
                    $this->line("  skipped {$folder}/{$name}");
                    $refused++;

                    continue;
                }

                if ($disk->exists($folder . '/' . $name)) {
                    $skipped++;

                    continue;
                }

                $stream = fopen($path, 'rb');
                $disk->put($folder . '/' . $name, $stream);
                is_resource($stream) && fclose($stream);
                $copied++;
            }
        }

        $this->info("Copied {$copied}, already present {$skipped}, ignored {$refused}.");

        return self::SUCCESS;
    }
}
