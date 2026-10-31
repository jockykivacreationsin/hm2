<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Generated PDFs (collection reports, ...) are written to the private `reports` disk on demand and are
 * re-created whenever they are requested again, so old ones are only clutter.
 */
class PruneGeneratedReports extends Command
{
    protected $signature = 'reports:prune {--days=14 : Delete generated PDFs older than this many days} {--dry-run : List what would be deleted}';

    protected $description = 'Delete generated report PDFs older than N days from the private reports disk';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->getTimestamp();
        $disk = Storage::disk('reports');
        $deleted = 0;

        foreach ($disk->files() as $file) {
            if (!str_ends_with(strtolower($file), '.pdf') || $disk->lastModified($file) >= $cutoff) {
                continue;
            }
            if (!$this->option('dry-run')) {
                $disk->delete($file);
            }
            $deleted++;
        }

        $this->info(($this->option('dry-run') ? 'Would delete ' : 'Deleted ') . $deleted . " generated PDF(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
