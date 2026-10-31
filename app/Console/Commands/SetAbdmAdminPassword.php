<?php

namespace App\Console\Commands;

use App\Services\Abdm\AbdmSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The ABDM settings page is unlocked with a password kept (hashed) in the `settings` row. Nothing in the web UI sets it,
 * so this is how an administrator creates or changes it.
 */
class SetAbdmAdminPassword extends Command
{
    protected $signature = 'abdm:set-admin-password {password? : The new password (prompted for when omitted)}';

    protected $description = 'Set the password that unlocks the ABDM settings page';

    public function handle(AbdmSettingsService $settings): int
    {
        if (! DB::table('settings')->where('id', 1)->exists()) {
            $this->error('There is no settings row. Run "php artisan migrate --seed" first.');

            return self::FAILURE;
        }

        $password = (string) ($this->argument('password') ?? $this->secret('New password'));

        if (strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $settings->setPassword($password);
        $this->info('ABDM settings password updated.');

        return self::SUCCESS;
    }
}
