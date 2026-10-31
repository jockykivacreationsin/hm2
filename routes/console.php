<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
| Re-dispatch ABDM health-information requests that were stored but never processed
| (e.g. PHP was killed after the callback was acknowledged). Only rows still 'pending'
| are picked up, so a request already claimed by another worker is never pushed twice.
*/
Artisan::command('abdm:process-pending-health-info {--older-than=2 : Only requests at least this many minutes old}', function () {
    $cutoff = now()->subMinutes((int) $this->option('older-than'))->toDateTimeString();
    $ids = DB::table('abdm_health_info_requests')->where('status', 'pending')
        ->where('created_on', '<=', $cutoff)->orderBy('id')->limit(50)->pluck('transaction_id');
    foreach ($ids as $transactionId) {
        \App\Jobs\ProcessAbdmHealthInfo::dispatchSync($transactionId);
    }
    $this->info('Processed ' . $ids->count() . ' pending request(s).');
})->purpose('Process stored but unprocessed ABDM health-information requests');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the private reports disk from growing forever (run by `php artisan schedule:run` from cron).
\Illuminate\Support\Facades\Schedule::command('reports:prune --days=14')->daily();
