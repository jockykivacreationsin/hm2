<?php

namespace App\Jobs;

use App\Services\Abdm\HipHealthInfoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Builds, encrypts and pushes the FHIR data for one stored ABDM health-information/request.
 * Single attempt: HipHealthInfoService claims the row atomically and records the final outcome,
 * and a blind retry could push the same data twice.
 */
class ProcessAbdmHealthInfo implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public function __construct(public string $transactionId)
    {
    }

    public function handle(HipHealthInfoService $svc): void
    {
        $row = DB::table('abdm_health_info_requests')->where('transaction_id', $this->transactionId)->first();
        if (!$row) {
            Log::warning('ABDM health-info job: request row not found', ['transaction_id' => $this->transactionId]);
            return;
        }

        $svc->processRequest((array) $row);
    }
}
