<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use ZipArchive;

class DbBackupService
{
    /**
     * Get list of directories where backups can reside.
     * Primary is storage_path('app/db_backups').
     * Falls back to public_path('db_backups').
     */
    public function getBackupDirectories(): array
    {
        $primary = storage_path('app/db_backups');
        if (!is_dir($primary)) {
            @mkdir($primary, 0755, true);
        }

        return array_values(array_filter([
            $primary,
            public_path('db_backups'),
        ], 'is_dir'));
    }

    /**
     * Get the primary directory where new backups are created.
     */
    public function getPrimaryDirectory(): string
    {
        $dir = storage_path('app/db_backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Extract legacy date (DD-MM-YYYY) from filename.
     * Matches legacy getDateFromFilename() in bmhabdm/db-backups-list.php.
     */
    public function getDateFromFilename(string $file): string
    {
        $name = basename($file);
        if (preg_match('/hospital_backup_(\d{2})_(\d{2})_(\d{4})_(\d{2})_(\d{2})_(\d{2})_(am|pm)/i', $name, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        return $name;
    }

    /**
     * Extract full formatted timestamp (DD-MM-YYYY hh:mm:ss AM/PM) from filename.
     */
    public function getFormattedDateTime(string $file): string
    {
        $name = basename($file);
        if (preg_match('/hospital_backup_(\d{2})_(\d{2})_(\d{4})_(\d{2})_(\d{2})_(\d{2})_(am|pm)/i', $name, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]} " . strtoupper($m[7]);
        }
        $mtime = @filemtime($file);
        return $mtime ? date('d-m-Y h:i:s A', $mtime) : date('d-m-Y');
    }

    /**
     * Parse timestamp integer from filename.
     * Matches legacy getTimestampFromFilename() in bmhabdm/db-backups-list.php.
     */
    public function getTimestampFromFilename(string $file): int
    {
        $name = basename($file);
        if (preg_match('/hospital_backup_(\d{2})_(\d{2})_(\d{4})_(\d{2})_(\d{2})_(\d{2})_(am|pm)/i', $name, $m)) {
            [$full, $d, $mth, $y, $h, $i, $s, $ampm] = $m;
            $h = (int) $h;
            $ampm = strtolower($ampm);
            if ($ampm === 'pm' && $h < 12) {
                $h += 12;
            }
            if ($ampm === 'am' && $h == 12) {
                $h = 0;
            }
            return (int) mktime($h, (int) $i, (int) $s, (int) $mth, (int) $d, (int) $y);
        }

        $mtime = @filemtime($file);
        return $mtime ?: 0;
    }

    /**
     * Get sorted list of backup archives descending by timestamp.
     */
    public function getBackups(): array
    {
        $files = [];
        $seen = [];

        foreach ($this->getBackupDirectories() as $dir) {
            $globFiles = glob($dir . '/hospital_backup_*.zip') ?: [];
            foreach ($globFiles as $path) {
                $filename = basename($path);
                if (isset($seen[$filename])) {
                    continue;
                }
                $seen[$filename] = true;
                $files[] = $path;
            }
        }

        usort($files, function ($a, $b) {
            return $this->getTimestampFromFilename($b) <=> $this->getTimestampFromFilename($a);
        });

        $result = [];
        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $bytes = @filesize($filePath) ?: 0;
            $result[] = [
                'filename'        => $filename,
                'path'            => $filePath,
                'display_date'    => $this->getDateFromFilename($filePath),
                'formatted_time'  => $this->getFormattedDateTime($filePath),
                'timestamp'       => $this->getTimestampFromFilename($filePath),
                'bytes'           => $bytes,
                'file_size'       => $this->formatBytes($bytes),
            ];
        }

        return $result;
    }

    /**
     * Find a backup archive safely, preventing path traversal.
     */
    public function findBackup(string $filename): ?string
    {
        $safeName = basename($filename);
        if (!preg_match('/^hospital_backup_[a-zA-Z0-9_\-]+\.zip$/i', $safeName)) {
            return null;
        }

        foreach ($this->getBackupDirectories() as $dir) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $safeName;
            if (file_exists($candidate)) {
                $realCandidate = realpath($candidate);
                $realDir = realpath($dir);
                if ($realCandidate && $realDir && str_starts_with($realCandidate, $realDir)) {
                    return $realCandidate;
                }
            }
        }

        return null;
    }

    /**
     * Delete a backup file safely.
     */
    public function deleteBackup(string $filename): bool
    {
        $path = $this->findBackup($filename);
        if ($path && file_exists($path)) {
            return @unlink($path);
        }
        return false;
    }

    /**
     * Return true if a backup already exists for today (DD-MM-YYYY).
     */
    public function hasBackupForToday(): bool
    {
        $today = date('d-m-Y');
        foreach ($this->getBackups() as $backup) {
            if ($backup['display_date'] === $today) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate an on-demand SQL dump and compress it to ZIP archive.
     * Matches legacy hospital_backup_{d_m_Y_h_i_s_a}.zip.
     * Throws \RuntimeException if a backup for today already exists.
     */
    public function createBackup(): array
    {
        if ($this->hasBackupForToday()) {
            throw new \RuntimeException('A backup for today already exists. Only one backup per day is allowed.');
        }

        $backupDir = $this->getPrimaryDirectory();
        $timestamp = date('d_m_Y_h_i_s_a');
        $sqlFile = "hospital_backup_{$timestamp}.sql";
        $zipFile = "hospital_backup_{$timestamp}.zip";

        $sqlPath = $backupDir . DIRECTORY_SEPARATOR . $sqlFile;
        $zipPath = $backupDir . DIRECTORY_SEPARATOR . $zipFile;

        $handle = fopen($sqlPath, 'w');
        if (!$handle) {
            throw new \RuntimeException("Unable to open backup file for writing: {$sqlPath}");
        }

        try {
            $driver = DB::connection()->getDriverName();

            fwrite($handle, "-- BMH Hospital Database Backup\n");
            fwrite($handle, "-- Created: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "-- Driver: " . $driver . "\n\n");

            if ($driver === 'mysql') {
                $this->dumpMysqlDatabase($handle);
            } elseif ($driver === 'sqlite') {
                $this->dumpSqliteDatabase($handle);
            } else {
                $this->dumpGenericDatabase($handle);
            }
        } finally {
            fclose($handle);
        }

        // Compress SQL to ZIP using ZipArchive
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($opened !== true) {
                @unlink($sqlPath);
                throw new \RuntimeException("Could not create ZIP file (ZipArchive error: {$opened})");
            }
            $zip->addFile($sqlPath, $sqlFile);
            $zip->close();

            // Legacy removes SQL and retains ZIP
            @unlink($sqlPath);
        } else {
            // Fallback: rename sql to zip or keep sql if zip extension absent
            rename($sqlPath, $zipPath);
        }

        $bytes = file_exists($zipPath) ? (@filesize($zipPath) ?: 0) : 0;

        return [
            'filename'       => $zipFile,
            'path'           => $zipPath,
            'display_date'   => $this->getDateFromFilename($zipFile),
            'formatted_time' => $this->getFormattedDateTime($zipFile),
            'bytes'          => $bytes,
            'file_size'      => $this->formatBytes($bytes),
        ];
    }

    /**
     * Backup statistics for settings dashboard widget.
     */
    public function getBackupStats(): array
    {
        $backups = $this->getBackups();
        $count = count($backups);
        $latest = $count > 0 ? $backups[0] : null;

        return [
            'total_backups' => $count,
            'latest_backup' => $latest,
            'directory'     => $this->getPrimaryDirectory(),
        ];
    }

    /**
     * Dump MySQL database schema and data.
     */
    protected function dumpMysqlDatabase($handle): void
    {
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = [];
        $rows = DB::select('SHOW TABLES');
        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $tables[] = reset($rowArray);
        }

        foreach ($tables as $table) {
            // Table structure
            $createRows = DB::select("SHOW CREATE TABLE `{$table}`");
            if (!empty($createRows)) {
                $createRow = (array) $createRows[0];
                $createSql = $createRow['Create Table'] ?? (array_values($createRow)[1] ?? '');
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($handle, $createSql . ";\n\n");
            }

            // Table data
            $cursor = DB::table($table)->cursor();
            foreach ($cursor as $item) {
                $data = (array) $item;
                $values = [];
                foreach ($data as $val) {
                    if (is_null($val)) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . addslashes((string) $val) . "'";
                    }
                }
                fwrite($handle, "INSERT INTO `{$table}` VALUES (" . implode(',', $values) . ");\n");
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * Dump SQLite database schema and data (used in tests and SQLite installs).
     */
    protected function dumpSqliteDatabase($handle): void
    {
        $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($tables as $tbl) {
            $tableName = $tbl->name;
            $createSql = $tbl->sql;

            fwrite($handle, "DROP TABLE IF EXISTS \"{$tableName}\";\n");
            fwrite($handle, $createSql . ";\n\n");

            $cursor = DB::table($tableName)->cursor();
            foreach ($cursor as $item) {
                $data = (array) $item;
                $values = [];
                foreach ($data as $val) {
                    if (is_null($val)) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . addslashes((string) $val) . "'";
                    }
                }
                fwrite($handle, "INSERT INTO \"{$tableName}\" VALUES (" . implode(',', $values) . ");\n");
            }
            fwrite($handle, "\n");
        }
    }

    /**
     * Generic fallback database dumper.
     */
    protected function dumpGenericDatabase($handle): void
    {
        $tables = DB::connection()->getSchemaBuilder()->getTableListing();
        foreach ($tables as $tableName) {
            $cursor = DB::table($tableName)->cursor();
            foreach ($cursor as $item) {
                $data = (array) $item;
                $values = [];
                foreach ($data as $val) {
                    if (is_null($val)) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . addslashes((string) $val) . "'";
                    }
                }
                fwrite($handle, "INSERT INTO `{$tableName}` VALUES (" . implode(',', $values) . ");\n");
            }
            fwrite($handle, "\n");
        }
    }

    /**
     * Format bytes to human readable string.
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
