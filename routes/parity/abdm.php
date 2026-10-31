<?php

use App\Http\Controllers\Abdm\AbdmController;
use App\Http\Controllers\Abdm\AbdmSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ABDM parity routes (authenticated group - see routes/web.php)
|--------------------------------------------------------------------------
*/

// ── Settings (legacy abdm-settings.php; m_roles_and_permissions + admin password gate) ──
Route::get('abdm/settings',               [AbdmSettingsController::class, 'show'])->name('abdm.settings');
Route::post('abdm/settings',              [AbdmSettingsController::class, 'handlePost'])->name('abdm.settings.post');
Route::post('abdm/settings/unlock',       [AbdmSettingsController::class, 'unlock'])->name('abdm.settings.unlock');
Route::post('abdm/settings/logout',       [AbdmSettingsController::class, 'lock'])->name('abdm.settings.logout');
Route::post('abdm/settings/save',         [AbdmSettingsController::class, 'save'])->name('abdm.settings.save');
Route::post('abdm/settings/bridge-url',   [AbdmSettingsController::class, 'updateBridgeUrl'])->name('abdm.settings.bridge-url');
Route::post('abdm/settings/bridge-check', [AbdmSettingsController::class, 'checkBridgeUrl'])->name('abdm.settings.bridge-check');

/*
| Already registered in routes/web.php (abdm.* group) and reused by the ABDM panels:
|   abdm.link-token.{check,request}
|   abdm.{opd,ipd,dcd}.care-context.{check,link}         (POST abdm/{module}/{id}/care-context/...)
|   abdm.hiu.artefact.fetch, abdm.hiu.health-data.request (POST, consent_id in the body)
|   abdm.identity.* incl. update-mobile.* / update-email.* / aadhaar-login-*   (ABHA Account Services)
| The routes below are the per-action endpoints that replaced the action-dispatching
| abdm/{hiu,link,care_context,health_info,abha_account}_ajax.php scripts.
*/
Route::prefix('abdm')->name('abdm.')->group(function () {
    // ── Health-information status polling (Link-to-ABDM modal; query: opd_id | ipd_id | dcd_id) ──
    Route::prefix('health-info')->name('health-info.')->group(function () {
        Route::get('consent',       [AbdmController::class, 'healthInfoConsent'])->name('consent');
        Route::get('push-status',   [AbdmController::class, 'healthInfoPushStatus'])->name('push-status');
        Route::post('retry-push',   [AbdmController::class, 'retryHealthInfoPush'])->name('retry-push');
    });

    // ── HIU: consent requests, patient panel, received records ──
    Route::prefix('hiu')->name('hiu.')->group(function () {
        // patient pickers (Consent Requests page select2 / offcanvas lookup)
        Route::get('patients/dropdown', [AbdmController::class, 'consentPatientsDropdown'])->name('patients.dropdown');
        Route::get('patients/search',   [AbdmController::class, 'hiuSearchPatientByAbha'])->name('patients.search');
        Route::get('patients/{patientId}', [AbdmController::class, 'hiuPatientShow'])->whereNumber('patientId')->name('patients.show');

        // per-patient HIU panel on OPD / IPD / DCD visits
        Route::get('patients/{patientId}/status',            [AbdmController::class, 'hiuPatientStatus'])->whereNumber('patientId')->name('patients.status');
        Route::post('patients/{patientId}/consent-requests', [AbdmController::class, 'hiuPatientConsentRequest'])->whereNumber('patientId')->name('patients.consent-requests.store');
        Route::post('patients/{patientId}/consent-status',   [AbdmController::class, 'hiuPatientCheckConsentStatus'])->whereNumber('patientId')->name('patients.consent-status');

        // Consent Requests page
        Route::get('consents',                      [AbdmController::class, 'hiuConsentsIndex'])->name('consents.index');
        Route::post('consents',                     [AbdmController::class, 'hiuConsentsStore'])->name('consents.store');
        Route::get('consents/{id}',                 [AbdmController::class, 'hiuConsentsShow'])->whereNumber('id')->name('consents.show');
        Route::post('consents/{id}/refresh-status', [AbdmController::class, 'hiuConsentsRefreshStatus'])->whereNumber('id')->name('consents.refresh-status');

        // received records (consent artefact id is a string, not numeric)
        Route::get('consents/{consentId}/records', [AbdmController::class, 'hiuConsentRecords'])->name('consents.records.index');
        Route::get('records/{recordId}',           [AbdmController::class, 'hiuRecordShow'])->whereNumber('recordId')->name('records.show');
    });
});
