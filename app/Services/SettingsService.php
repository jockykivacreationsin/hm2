<?php
namespace App\Services;

use App\Models\Setting;
use App\Models\IdFieldSetting;
use App\Support\AbhaTerms;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private ?array $settings = null;
    private ?array $idFieldSettings = null;

    public function getSettings(): array
    {
        if ($this->settings === null) {
            $this->settings = Cache::remember('hospital_settings', config('hospital.settings_cache_ttl', 600), function () {
                try {
                    $row = Setting::find(1);
                    return $row ? $row->toArray() : $this->defaultSettings();
                } catch (\Throwable $e) {
                    return $this->defaultSettings();
                }
            });
        }
        return $this->settings;
    }

    public function getIdFieldSettings(): array
    {
        if ($this->idFieldSettings === null) {
            $this->idFieldSettings = Cache::remember('id_field_settings', config('hospital.settings_cache_ttl', 600), function () {
                try {
                    $row = IdFieldSetting::find(1);
                    return $row ? $row->toArray() : $this->defaultIdFieldSettings();
                } catch (\Throwable $e) {
                    return $this->defaultIdFieldSettings();
                }
            });
        }
        return $this->idFieldSettings;
    }

    public function get(string $key, mixed $default = ''): mixed
    {
        return $this->getSettings()[$key] ?? $default;
    }

    public function getIdField(string $key, int $default = 0): int
    {
        return (int) ($this->getIdFieldSettings()[$key] ?? $default);
    }

    public static function clearCache(): void
    {
        Cache::forget('hospital_settings');
        Cache::forget('id_field_settings');

        // The service is a singleton that also keeps its own copy for the rest of the process.
        if (app()->resolved(self::class)) {
            $service = app(self::class);
            $service->settings = null;
            $service->idFieldSettings = null;
        }
    }

    /**
     * ABDM ABHA-creation Terms & Conditions (settings.abha_creation_terms, JSON: {title, items:[{id,text}]}).
     * Staff must accept them before an ABHA can be created via Aadhaar. Read fresh (not from the settings cache)
     * so an edit in Settings takes effect immediately, exactly like the legacy pages.
     *
     * @return array{title:string,items:array<int,string>}
     */
    public function abhaTerms(): array
    {
        try {
            $raw = Setting::query()->whereKey(1)->value('abha_creation_terms');
        } catch (\Throwable $e) {
            return ['title' => AbhaTerms::DEFAULT_TITLE, 'items' => []];
        }

        // AbhaTerms::parse also reads JSON that an old Settings page had wrapped in <p> / entity-encoded.
        $terms = AbhaTerms::parse($raw);

        return ['title' => $terms['title'], 'items' => array_column($terms['items'], 'text')];
    }

    private function defaultSettings(): array
    {
        return [
            'hospital_name' => '', 'address' => '', 'phone_number' => '',
            'mobile_number' => '', 'bmh_billing_start_number' => 0,
            'ivy_billing_start_number' => 0, 'slogan_general' => '',
            'slogan_hospital' => '', 'logo' => '', 'square_logo' => '', 'signer' => '',
            'uhid_prefix' => 'BMH', 'uhid_suffix' => '', 'uhid_padding' => 7,
        ];
    }

    private function defaultIdFieldSettings(): array
    {
        return [
            'doctor_department_id' => 0, 'nurse_department_id' => 0,
            'pathology_department_id' => 0, 'opd_department_id' => 0,
            'radiology_department_id' => 0, 'endoscopy_department_id' => 0,
            'dialysis_department_id' => 0, 'procedure_department_id' => 0,
            'pharmacy_department_id' => 0, 'doctor_designation_id' => 0,
            'nurse_designation_id' => 0,
        ];
    }
}
