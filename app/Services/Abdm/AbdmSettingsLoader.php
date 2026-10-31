<?php

namespace App\Services\Abdm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Overlays the hospital-specific ABDM values stored in the `settings` table onto config('abdm.*').
 *
 * The legacy app (bmhabdm/abdm/config.php) read these from the settings row, never from a config
 * file, so the table is the source of truth. The .env values (ABDM_HIP_ID, ABDM_CLIENT_ID, ...)
 * are only a fallback, used when the matching settings column is empty.
 *
 *   settings.abdm_client_id                -> abdm.client_id
 *   settings.abdm_client_secret            -> abdm.client_secret
 *   settings.facility_hfr_id               -> abdm.hip_id   (legacy $abdm_facility_hfr_id = X-HIP-ID)
 *   settings.abdm_hiu_id                   -> abdm.hiu_id
 *   settings.abdm_bridge_callback_base_url -> abdm.callback_base_url
 *
 * The row is read straight from the DB (not via the cached SettingsService) because the model hides
 * the client secret, and so an edit on the Settings page applies on the very next request.
 */
class AbdmSettingsLoader
{
    private const MAP = [
        'abdm_client_id'                => 'abdm.client_id',
        'abdm_client_secret'            => 'abdm.client_secret',
        'facility_hfr_id'               => 'abdm.hip_id',
        'abdm_hiu_id'                   => 'abdm.hiu_id',
        'abdm_bridge_callback_base_url' => 'abdm.callback_base_url',
    ];

    public static function apply(): void
    {
        try {
            $row = DB::table('settings')->where('id', 1)->first(array_keys(self::MAP));
        } catch (\Throwable $e) {
            // Table/DB not available yet (fresh install, migrations running) - keep the .env fallback.
            Log::debug('ABDM settings overlay skipped: ' . $e->getMessage());
            return;
        }

        if (!$row) {
            return;
        }

        foreach (self::MAP as $column => $configKey) {
            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '') {
                config([$configKey => $value]);
            }
        }
    }
}
