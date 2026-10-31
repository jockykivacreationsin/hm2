<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\DbBackupService;
use Illuminate\Support\Facades\Log;
use App\Services\Abdm\AbdmSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DbBackupController extends Controller
{
    public function __construct(
        protected DbBackupService $backupService
    ) {}

    /**
     * Display list of database backups (legacy bmhabdm/db-backups-list.php).
     */
    public function index(Request $request)
    {
        $backups = $this->backupService->getBackups();
        $stats   = $this->backupService->getBackupStats();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'backups' => $backups,
                'stats'   => $stats,
            ]);
        }

        return view('settings.backups', compact('backups', 'stats'));
    }

    /**
     * Verify the admin password before allowing a backup to be created.
     * Returns JSON {success, message} — no session state is set.
     */
    public function verifyPassword(Request $request, AbdmSettingsService $settings): JsonResponse
    {
        $data = $request->validate(['password' => 'required|string|max:255']);

        if (! $settings->passwordMatches($data['password'])) {
            return response()->json(['success' => false, 'message' => 'Incorrect admin password.'], 422);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Trigger on-demand database backup creation.
     */
    public function store(Request $request)
    {
        try {
            $backup = $this->backupService->createBackup();

            app(AuditLogger::class)->createBackup($backup['filename']);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Database backup created successfully: ' . $backup['filename'],
                    'backup'  => $backup,
                ]);
            }

            return redirect()->route('db-backups.index')
                ->with('success', 'Database backup created successfully: ' . $backup['filename']);
        } catch (\Throwable $e) {
            Log::error('DbBackupController@create failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create backup. Please try again.',
                ], 500);
            }

            return redirect()->route('db-backups.index')
                ->with('error', 'Failed to create backup. Please check the server logs.');
        }
    }

    /**
     * Verify admin password then stream the backup file (POST).
     * Used by the Swal password-prompt download flow in backups.blade.php.
     */
    public function verifyAndDownload(Request $request, string $filename, AbdmSettingsService $settings): BinaryFileResponse
    {
        $data = $request->validate(['password' => 'required|string|max:255']);

        if (! $settings->passwordMatches($data['password'])) {
            abort(403, 'Incorrect admin password.');
        }

        $path = $this->backupService->findBackup($filename);

        if (!$path || !file_exists($path)) {
            abort(404, 'Backup file not found or has been deleted.');
        }

        app(AuditLogger::class)->downloadBackup(basename($filename));

        return response()->download($path, basename($filename), [
            'Content-Type'              => 'application/zip',
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'no-store, no-cache, must-revalidate',
            'Pragma'                    => 'public',
        ]);
    }

    /**
     * Download a specific backup archive safely (legacy / internal use).
     */
    public function download(string $filename): BinaryFileResponse
    {
        $path = $this->backupService->findBackup($filename);

        if (!$path || !file_exists($path)) {
            abort(404, 'Backup file not found or has been deleted.');
        }

        app(AuditLogger::class)->downloadBackup(basename($filename));

        return response()->download($path, basename($filename), [
            'Content-Type'              => 'application/zip',
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'no-store, no-cache, must-revalidate',
            'Pragma'                    => 'public',
        ]);
    }

    /**
     * Delete a backup archive.
     */
    public function destroy(string $filename, Request $request)
    {
        $deleted = $this->backupService->deleteBackup($filename);

        app(AuditLogger::class)->deleteBackup(basename($filename), $deleted);


        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'Backup deleted successfully.' : 'Backup file could not be deleted or was not found.',
            ], $deleted ? 200 : 404);
        }

        if ($deleted) {
            return redirect()->route('db-backups.index')->with('success', 'Backup deleted successfully.');
        }

        return redirect()->route('db-backups.index')->with('error', 'Backup file could not be deleted or was not found.');
    }

    /**
     * Legacy handler for db-backup.php (creates backup and immediately initiates download).
     */
    public function legacyBackup(Request $request)
    {
        try {
            $backup = $this->backupService->createBackup();
            app(AuditLogger::class)->createBackup($backup['filename']);
            app(AuditLogger::class)->downloadBackup($backup['filename']);
            return response()->download($backup['path'], $backup['filename'], [
                'Content-Type'              => 'application/zip',
                'X-Content-Type-Options'    => 'nosniff',
                'Cache-Control'             => 'no-store, no-cache, must-revalidate',
                'Pragma'                    => 'public',
            ]);
        } catch (\Throwable $e) {
            Log::error('DbBackupController@verifyAndDownload failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('db-backups.index')
                ->with('error', 'Failed to generate backup. Please check the server logs.');
        }
    }

    /**
     * Legacy direct file download route for db_backups/{filename}.
     */
    public function downloadDirect(string $filename): BinaryFileResponse
    {
        return $this->download($filename);
    }
}
