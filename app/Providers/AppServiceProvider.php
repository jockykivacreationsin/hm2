<?php
namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Services\PermissionService;
use App\Services\SettingsService;
use App\Services\Abdm\AbdmSettingsLoader;
use App\Support\CurrentUser;
use App\Support\Uploads;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, function ($app) {
            return new PermissionService();
        });

        $this->app->singleton(SettingsService::class, function ($app) {
            return new SettingsService();
        });
    }

    public function boot(): void
    {
        // ── Password complexity policy (VAPT requirement) ─────────────────────
        // Minimum 8 characters, at least one uppercase letter, one lowercase
        // letter, one digit, and one symbol.  Applied everywhere a staff password
        // is set: new-staff form, edit-staff form, and the password-reset flow.
        Password::defaults(fn () =>
            Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
        );

        $this->registerRateLimiters();

        // ABDM client id/secret, HFR (HIP) id, HIU id and callback URL live in the settings table.
        AbdmSettingsLoader::apply();

        // Header data (legacy header.php): logged-in user photo + "Please Add Doctors" alert
        view()->composer('layouts.partials.header', function ($view) {
            $userId = CurrentUser::id();
            $user = $userId ? \App\Models\StaffMember::find($userId) : null;
            $doctorDepartmentId = app(SettingsService::class)->getIdField('doctor_department_id');
            $view->with([
                'userPhoto' => Uploads::url(Uploads::STAFF, $user?->photo),
                'noDoctors' => !\App\Models\StaffMember::where('department_id', $doctorDepartmentId)
                    ->where('active', 'Yes')->exists(),
            ]);
        });

        // Share hospital settings with all views
        view()->composer('*', function ($view) {
            $settingsService = app(SettingsService::class);
            // hospitalName is shared with every view (including public login/forgot-password pages).
            $view->with('hospitalName', $settingsService->getSettings()['hospital_name'] ?? '');

            if (CurrentUser::id() !== null) {
                $permissionService = app(PermissionService::class);
                $settings = $settingsService->getSettings();
                $view->with([
                    'hospitalSettings' => $settings,
                    'rolePermissions' => $permissionService->getPermissions(),
                    'loggedInUserId' => CurrentUser::id(),
                    'userRoleId' => CurrentUser::roleId(),
                    'displayName' => CurrentUser::displayName(),
                    'patientCategoryId' => session('patient_category_id'),
                ]);
            }
        });
    }

    /**
     * Limits for the ABHA / Aadhaar OTP endpoints. Each "send OTP" makes ABDM text a real person, and ABDM blocks a
     * facility that sends too many requests (ABDM-1027, 24 hours) - so a stolen session or a script must not be able
     * to burn that allowance, and OTP guesses are capped. Limited per logged-in user (per address when there is none).
     * Normal use is a handful of calls per patient, far below these numbers.
     */
    private function registerRateLimiters(): void
    {
        $who = fn (Request $request): string => (string) (CurrentUser::id() ?: $request->ip());

        $tooMany = fn (string $what) => function (Request $request, array $headers) use ($what) {
            return response()->json([
                'success' => false,
                'error'   => "Too many {$what} in a short time - please wait a minute and try again.",
            ], 429, $headers);
        };

        RateLimiter::for('abdm-otp-send', fn (Request $request) => [
            Limit::perMinute(6)->by('send:' . $who($request))->response($tooMany('OTP requests')),
            Limit::perHour(40)->by('send-hour:' . $who($request))->response($tooMany('OTP requests')),
        ]);

        RateLimiter::for('abdm-otp-verify', fn (Request $request) => [
            Limit::perMinute(12)->by('verify:' . $who($request))->response($tooMany('OTP attempts')),
            Limit::perHour(80)->by('verify-hour:' . $who($request))->response($tooMany('OTP attempts')),
        ]);
    }
}
