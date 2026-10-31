<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Models\IdFieldSetting;
use App\Services\SettingsService;
use App\Services\Abdm\AbdmSettingsService;
use App\Support\AbhaTerms;
use App\Support\CurrentUser;
use App\Support\Uhid;
use App\Support\Uploads;

use App\Models\HrsDepartment;
use App\Models\Designation;
use App\Services\DbBackupService;

class SettingController extends Controller
{
    /** Session flag for the settings page password gate. */
    private const SESSION_FLAG    = 'settings_page_authenticated';
    private const SESSION_FLAG_AT = 'settings_page_authenticated_at';

    /** Re-prompt for the admin password after this many minutes of inactivity. */
    private const UNLOCK_TTL_MINUTES = 30;

    // ── Page display ──────────────────────────────────────────────────────────

    public function index(Request $request, DbBackupService $backupService)
    {
        $isUnlocked = $this->isUnlocked($request);

        if ($isUnlocked) {
            // Refresh the activity timestamp on every page load while unlocked.
            $request->session()->put(self::SESSION_FLAG_AT, now()->timestamp);
        }

        $settings     = Setting::find(1) ?? new Setting();
        $idSettings   = IdFieldSetting::find(1) ?? new IdFieldSetting();
        $departments  = HrsDepartment::orderBy('department')->get();
        $designations = Designation::orderBy('designation')->get();
        $backupStats  = $backupService->getBackupStats();

        return view('settings.index', compact(
            'settings', 'idSettings', 'departments', 'designations', 'backupStats', 'isUnlocked'
        ));
    }

    // ── Password gate ─────────────────────────────────────────────────────────

    public function unlock(Request $request, AbdmSettingsService $abdmSettings): RedirectResponse
    {
        $data = $request->validate(['admin_password' => 'required|string|max:255']);

        if (! $abdmSettings->passwordMatches($data['admin_password'])) {
            Log::warning('Settings page unlock failed', [
                'staff_id' => CurrentUser::id(),
                'ip'       => $request->ip(),
            ]);

            return redirect()->route('settings.index')
                ->with('settings_password_error', 'Incorrect admin password. Please try again.');
        }

        $request->session()->put(self::SESSION_FLAG,    true);
        $request->session()->put(self::SESSION_FLAG_AT, now()->timestamp);

        Log::info('Settings page unlocked', [
            'staff_id' => CurrentUser::id(),
            'ip'       => $request->ip(),
        ]);

        return redirect()->route('settings.index');
    }

    public function lock(Request $request): RedirectResponse
    {
        $this->clearUnlock($request);

        return redirect()->route('settings.index');
    }

    // ── Settings update (POST /) ──────────────────────────────────────────────

    public function update(Request $request): JsonResponse
    {
        if (! $this->isUnlocked($request)) {
            return response()->json(['success' => false, 'message' => 'Page is locked. Please enter the admin password first.'], 403);
        }

        $v = $request->validate([
            'hospital_name'            => 'required|string|max:255',
            'address'                  => 'nullable|string',
            'phone_number'             => 'nullable|string|max:20',
            'mobile_number'            => 'nullable|string|max:20',
            'contact_number'           => 'nullable|string|max:20',
            'email'                    => 'nullable|email|max:255',
            'slogan_general'           => 'nullable|string|max:255',
            'slogan_hospital'          => 'nullable|string|max:255',
            'signer'                   => 'nullable|string|max:255',
            'bmh_billing_start_number' => 'nullable|integer|min:0',
            'ivy_billing_start_number' => 'nullable|integer|min:0',
            // UHID = prefix + patients.id padded to `uhid_padding` digits + suffix (App\Support\Uhid)
            'uhid_prefix'              => ['nullable', 'string', 'max:20', 'regex:' . Uhid::PART_PATTERN],
            'uhid_suffix'              => ['nullable', 'string', 'max:20', 'regex:' . Uhid::PART_PATTERN],
            'uhid_padding'             => 'nullable|integer|min:0|max:' . Uhid::MAX_PADDING,
            'facility_name'            => 'nullable|string|max:255',
            'facility_hfr_id'          => 'nullable|string|max:100',
            'abdm_hiu_id'              => 'nullable|string|max:100',
            'abdm_bridge_callback_base_url' => 'nullable|url|max:500',
            // The form posts the title and the numbered terms; they are stored as the JSON the legacy pages read.
            'abha_terms_title'         => 'nullable|string|max:255',
            'abha_terms_items'         => 'nullable|array|max:50',
            'abha_terms_items.*'       => 'nullable|string|max:2000',
            'abha_creation_terms'      => 'nullable|string',   // raw text/JSON, still accepted (API clients, older forms)
            'logo'                     => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
            'square_logo'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
            // Admin password change (all three fields must be present to trigger a change)
            'current_admin_password'          => 'nullable|string|max:255',
            'new_admin_password'              => 'nullable|string|min:6|max:255',
            'new_admin_password_confirmation' => 'nullable|string|max:255',
        ]);

        // Logo uploads go to the private `uploads` disk; the column keeps the bare file name.
        foreach (['logo', 'square_logo'] as $field) {
            unset($v[$field]);
            if ($request->hasFile($field)) {
                $v[$field] = Uploads::putUploadedKeepingName(Uploads::SETTINGS, $request->file($field));
            }
        }

        // Blank form fields arrive as null; the UHID columns are NOT NULL, so a blank prefix/suffix means "none" and a
        // blank padding means the default. A request that does not send them leaves the saved values alone.
        foreach (['uhid_prefix', 'uhid_suffix'] as $part) {
            if (array_key_exists($part, $v)) {
                $v[$part] = (string) ($v[$part] ?? '');
            }
        }
        if (array_key_exists('uhid_padding', $v)) {
            $v['uhid_padding'] = $v['uhid_padding'] ?? Uhid::DEFAULT_PADDING;
        }

        // ABHA terms: the structured editor wins; a request that sends neither leaves the saved terms alone.
        if ($request->has('abha_terms_items') || $request->has('abha_terms_title')) {
            $v['abha_creation_terms'] = AbhaTerms::toJson([
                'title' => $v['abha_terms_title'] ?? null,
                'items' => $v['abha_terms_items'] ?? [],
            ]);
        } elseif (array_key_exists('abha_creation_terms', $v)) {
            $v['abha_creation_terms'] = AbhaTerms::toJson($v['abha_creation_terms']);
        }
        unset($v['abha_terms_title'], $v['abha_terms_items']);

        // Handle admin password change: only if all three fields were submitted and non-empty.
        unset($v['current_admin_password'], $v['new_admin_password'], $v['new_admin_password_confirmation']);
        $current  = $request->input('current_admin_password');
        $newPw    = $request->input('new_admin_password');
        $confirmPw = $request->input('new_admin_password_confirmation');
        if ($current !== null && $current !== '' && $newPw !== null && $newPw !== '') {
            if ($newPw !== $confirmPw) {
                return response()->json(['success' => false, 'message' => 'New passwords do not match.'], 422);
            }
            $existing = Setting::find(1);
            $storedHash = $existing?->getRawOriginal('admin_password') ?? ($existing->admin_password ?? '');
            if (!Hash::check($current, $storedHash)) {
                return response()->json(['success' => false, 'message' => 'Current admin password is incorrect.'], 422);
            }
            $v['admin_password'] = Hash::make($newPw);
        }

        Setting::updateOrCreate(['id' => 1], $v);
        SettingsService::clearCache();

        return response()->json(['success' => true, 'message' => 'Settings saved successfully.']);
    }

    public function updateIdFields(Request $request): JsonResponse
    {
        if (! $this->isUnlocked($request)) {
            return response()->json(['success' => false, 'message' => 'Page is locked. Please enter the admin password first.'], 403);
        }

        $v = $request->validate([
            'doctor_department_id'    => 'nullable|integer',
            'nurse_department_id'     => 'nullable|integer',
            'pathology_department_id' => 'nullable|integer',
            'opd_department_id'       => 'nullable|integer',
            'radiology_department_id' => 'nullable|integer',
            'endoscopy_department_id' => 'nullable|integer',
            'dialysis_department_id'  => 'nullable|integer',
            'procedure_department_id' => 'nullable|integer',
            'pharmacy_department_id'  => 'nullable|integer',
            'doctor_designation_id'   => 'nullable|integer',
            'nurse_designation_id'    => 'nullable|integer',
        ]);

        IdFieldSetting::updateOrCreate(['id' => 1], $v);
        SettingsService::clearCache();

        return response()->json(['success' => true, 'message' => 'ID field settings saved.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isUnlocked(Request $request): bool
    {
        if (! $request->session()->get(self::SESSION_FLAG, false)) {
            return false;
        }

        $unlockedAt = (int) $request->session()->get(self::SESSION_FLAG_AT, 0);

        if ($unlockedAt === 0 || now()->timestamp - $unlockedAt > self::UNLOCK_TTL_MINUTES * 60) {
            $this->clearUnlock($request);
            return false;
        }

        return true;
    }

    private function clearUnlock(Request $request): void
    {
        $request->session()->forget([self::SESSION_FLAG, self::SESSION_FLAG_AT]);
    }
}
