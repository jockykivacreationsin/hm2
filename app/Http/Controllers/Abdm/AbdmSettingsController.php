<?php

namespace App\Http\Controllers\Abdm;

use App\Http\Controllers\Controller;
use App\Services\Abdm\AbdmSettingsService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Support\CurrentUser;

/**
 * ABDM Settings (legacy abdm-settings.php): m_roles_and_permissions + the settings.admin_password gate.
 * The legacy page handled every button as a POST to itself and re-rendered; here each action is a POST that
 * redirects back, carrying the gateway result in the flash session (Post/Redirect/Get).
 */
class AbdmSettingsController extends Controller
{
    /** Re-prompt for the admin password after this many minutes of inactivity. */
    private const UNLOCK_TTL_MINUTES = 15;

    public function __construct(private AbdmSettingsService $settings, private PermissionService $permissions) {}

    public function show(Request $request)
    {
        if (!$this->permissions->hasPermission('m_roles_and_permissions')) {
            abort(403, 'Access Denied');
        }

        // Auto-expire the unlock after UNLOCK_TTL_MINUTES minutes of inactivity.
        if ($this->isUnlocked($request)) {
            $request->session()->put(AbdmSettingsService::SESSION_FLAG . '_at', now()->timestamp);
        }

        $authenticated = $this->isUnlocked($request);

        return view('abdm.settings', [
            'is_authenticated'    => $authenticated,
            'settings_row'        => $authenticated ? $this->settings->current() : null,
            'saved'               => (bool) session('abdm_settings_saved'),
            'bridge_result'       => session('abdm_bridge_result'),
            'bridge_check_result' => session('abdm_bridge_check_result'),
        ]);
    }

    /**
     * The old `abdm-settings.php`: one URL that accepted the unlock/save forms by POST and `?logout=1` by GET.
     * Kept so existing bookmarks and the old form targets still work; everything else has its own route.
     * Note: the ?logout GET branch has been removed — locking is now POST-only (abdm.settings.logout).
     */
    public function legacy(Request $request): RedirectResponse
    {
        if ($request->isMethod('post')) {
            return $this->handlePost($request);
        }

        return redirect()->route('abdm.settings', $request->query());
    }

    public function handlePost(Request $request): RedirectResponse
    {
        if ($request->has('admin_password_submit')) {
            return $this->unlock($request);
        }
        if ($request->has('update_bridge_url')) {
            return $this->updateBridgeUrl($request);
        }
        if ($request->has('check_bridge_url')) {
            return $this->checkBridgeUrl($request);
        }
        if ($request->has('save_settings')) {
            return $this->save($request);
        }

        return redirect()->route('abdm.settings');
    }

    public function unlock(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate(['admin_password' => 'required|string|max:255']);

        if (!$this->settings->passwordMatches($data['admin_password'])) {
            Log::warning('ABDM settings unlock failed', ['staff_id' => CurrentUser::id(), 'ip' => $request->ip()]);

            return redirect()->route('abdm.settings')->with('abdm_settings_password_error', 'Invalid password');
        }

        $request->session()->put(AbdmSettingsService::SESSION_FLAG, true);
        $request->session()->put(AbdmSettingsService::SESSION_FLAG . '_at', now()->timestamp);
        Log::info('ABDM settings unlocked', ['staff_id' => CurrentUser::id(), 'ip' => $request->ip()]);

        return redirect()->route('abdm.settings');
    }

    public function lock(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->clearUnlock($request);

        return redirect()->route('abdm.settings');
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeUnlocked($request);

        $data = $request->validate([
            'abdm_client_id'                => 'nullable|string|max:255',
            'abdm_client_secret'            => 'nullable|string|max:255',
            'facility_hfr_id'               => 'nullable|string|max:100',
            'abdm_hiu_id'                   => 'nullable|string|max:100',
            'abdm_bridge_callback_base_url' => 'nullable|string|max:255',
        ]);

        $this->settings->save($data);
        Log::info('ABDM settings saved', ['staff_id' => CurrentUser::id()]);

        return redirect()->route('abdm.settings')->with('abdm_settings_saved', true);
    }

    public function updateBridgeUrl(Request $request): RedirectResponse
    {
        $this->authorizeUnlocked($request);
        $row = $this->settings->current();

        return redirect()->route('abdm.settings')->with('abdm_bridge_result', $this->settings->updateBridgeUrl(
            $row['abdm_client_id'], $row['abdm_client_secret'], $row['abdm_bridge_callback_base_url']
        ));
    }

    public function checkBridgeUrl(Request $request): RedirectResponse
    {
        $this->authorizeUnlocked($request);
        $row = $this->settings->current();

        return redirect()->route('abdm.settings')->with('abdm_bridge_check_result', $this->settings->fetchBridgeServices(
            $row['abdm_client_id'], $row['abdm_client_secret']
        ));
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->permissions->hasPermission('m_roles_and_permissions'), 403, 'Access Denied');
    }

    private function authorizeUnlocked(Request $request): void
    {
        $this->authorizeAdmin();
        abort_unless($this->isUnlocked($request), 403, 'Settings are locked');
    }

    /** Returns true only if the session flag is set AND was refreshed within UNLOCK_TTL_MINUTES. */
    private function isUnlocked(Request $request): bool
    {
        if (!$request->session()->get(AbdmSettingsService::SESSION_FLAG, false)) {
            return false;
        }

        $unlockedAt = (int) $request->session()->get(AbdmSettingsService::SESSION_FLAG . '_at', 0);

        if ($unlockedAt === 0 || now()->timestamp - $unlockedAt > self::UNLOCK_TTL_MINUTES * 60) {
            $this->clearUnlock($request);
            return false;
        }

        return true;
    }

    /** Removes both the flag and its timestamp from the session. */
    private function clearUnlock(Request $request): void
    {
        $request->session()->forget([
            AbdmSettingsService::SESSION_FLAG,
            AbdmSettingsService::SESSION_FLAG . '_at',
        ]);
    }
}
