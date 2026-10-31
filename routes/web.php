<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Abdm\AbdmSettingsController;
use App\Http\Controllers\GeneratedPdfController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\OpdController;
use App\Http\Controllers\IpdController;
use App\Http\Controllers\DcdController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\DbBackupController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleBillController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\LabResultController;
use App\Http\Controllers\PeriodicMedicineController;
use App\Http\Controllers\Reports\PdfReportController;
use App\Http\Controllers\Abdm\AbdmController;
use App\Http\Controllers\Abdm\AbdmIdentityController;
use App\Http\Controllers\WellnessController;
use App\Http\Controllers\ImmunizationController;
use App\Http\Controllers\PatientDocumentController;

// ── Public ────────────────────────────────────────────────────
Route::get('/',                    [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',              [AuthController::class, 'login'])->name('login.post');
Route::post('/login/verify-otp',   [AuthController::class, 'verifyOtp'])->name('login.verify-otp');
Route::post('/login/resend-otp',   [AuthController::class, 'resendOtp'])->name('login.resend-otp');
Route::post('/logout',             [AuthController::class, 'logout'])->name('logout');

// ── Password reset (public) ────────────────────────────────────
use App\Http\Controllers\PasswordResetController;
Route::get( '/forgot-password',         [PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('/forgot-password',         [PasswordResetController::class, 'sendLink'])->name('password.email');
Route::get( '/reset-password/{token}',  [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password',          [PasswordResetController::class, 'reset'])->name('password.update');

// ── Uploaded images (any logged-in user: the header, dashboard and every module show staff/patient photos) ──
Route::get('uploads/{folder}/{file}', [UploadController::class, 'show'])
    ->middleware('auth')
    ->where('folder', 'patients|staffmembers|settings|charges')
    ->where('file', '[^/\\\\]+')
    ->name('uploads.show');

// ── Authenticated ─────────────────────────────────────────────
// 'module.permission' = baseline m_* check per route name (config/route_permissions.php)
Route::middleware(['auth', 'module.permission'])->group(function () {

    // Photo cropper (Slim) target for the patient and staff forms.
    Route::post('uploads/photo/{kind}', [UploadController::class, 'storePhoto'])
        ->where('kind', 'patient|staff')
        ->name('uploads.photo.store');

    // Parity routes (legacy bmhabdm conversion) — one file per module in routes/parity/*.php.
    // Loaded FIRST so specific routes win over the generic /{id} wildcards declared below.
    foreach (glob(base_path('routes/parity/*.php')) as $parityRoutes) {
        require $parityRoutes;
    }

    // Old `page.php?id=` URLs (staff bookmarks) -> their Laravel routes. See config/legacy_urls.php.
    require base_path('routes/legacy.php');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/module-charges', [DashboardController::class, 'moduleCharges'])->name('dashboard.module-charges');

    // ── Patients ───────────────────────────────────────────
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/',                   [PatientController::class, 'index'])->name('index');
        Route::get('/data',               [PatientController::class, 'data'])->name('data');
        Route::get('/autocomplete',       [PatientController::class, 'autocomplete'])->name('autocomplete');
        Route::post('/',                  [PatientController::class, 'store'])->name('store');
        Route::post('/{id}/identity',     [PatientController::class, 'updateIdentity'])->name('identity.update');
        Route::get('/{id}',               [PatientController::class, 'show'])->name('show');
        Route::post('/{id}',              [PatientController::class, 'update'])->name('update');
        Route::delete('/{id}',            [PatientController::class, 'destroy'])->name('destroy');
        Route::get('/{id}/visit-history', [PatientController::class, 'visitHistory'])->name('visit-history');
        Route::get('/{id}/history',       [PatientController::class, 'history'])->whereNumber('id')->name('history');
    });

    // ── OPD ────────────────────────────────────────────────
    Route::prefix('opd')->name('opd.')->group(function () {
        Route::get('/',                          [OpdController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/data',   [OpdController::class, 'data'])->name('data');
        Route::get('/patient-autocomplete',      [OpdController::class, 'patientAutocomplete'])->name('patient-autocomplete');
        Route::post('/',                         [OpdController::class, 'store'])->name('store');
        Route::get('/patient/{id}',              [OpdController::class, 'patientDetails'])->whereNumber('id')->name('patient');

        Route::post('/{id}/collect-payment',     [OpdController::class, 'collectPayment'])->name('collect-payment');
        Route::post('/{id}/status',              [OpdController::class, 'updateStatus'])->name('status');

        Route::get('/{id}',                      [OpdController::class, 'show'])->name('show');
        Route::post('/{id}',                     [OpdController::class, 'update'])->name('update');
        Route::post('/{id}/overview',            [OpdController::class, 'updateOverview'])->name('update-overview');
        Route::post('/{id}/doctors',             [OpdController::class, 'addDoctor'])->name('doctors.add');
        Route::delete('/doctors/{id}',           [OpdController::class, 'removeDoctor'])->name('doctors.remove');

        Route::get('/{id}/prescriptions',        [OpdController::class, 'prescriptions'])->name('prescriptions');
        Route::post('/{id}/prescriptions',       [OpdController::class, 'storePrescription'])->name('prescriptions.store');
        Route::delete('/prescriptions/{rxId}',   [OpdController::class, 'destroyPrescription'])->name('prescriptions.destroy');

        Route::get('/{id}/charges',                     [OpdController::class, 'charges'])->name('charges');
        Route::post('/{id}/charges',                    [OpdController::class, 'storeCharge'])->name('charges.store');
        Route::get('/charges/{chargeId}/get',           [OpdController::class, 'getCharge'])->name('charges.get');
        Route::match(['put', 'post'], '/charges/{chargeId}/update', [OpdController::class, 'updateCharge'])->name('charges.update');
        Route::delete('/charges/{chargeId}',            [OpdController::class, 'destroyCharge'])->name('charges.destroy');
        Route::post('/{id}/charges/collect-payments',   [OpdController::class, 'collectPayments'])->name('charges.collect-payments');
        Route::get('/charges/{chargeId}/split-payments',[OpdController::class, 'getSplitPayments'])->name('charges.split-payments');
        Route::post('/charges/prescription-details',    [OpdController::class, 'getPrescriptionDetails'])->name('charges.prescription-details');
        Route::post('/charges/prescription-td',          [OpdController::class, 'getPrescriptionTD'])->name('charges.prescription-td');
        Route::post('/charges/charge-for-prescription', [OpdController::class, 'getChargeForPrescription'])->name('charges.charge-for-prescription');
        Route::match(['get', 'post'], '/charges/charge-amount', [OpdController::class, 'getChargeAmount'])->name('charges.charge-amount');


        Route::get('/{id}/lab-investigations',   [OpdController::class, 'labInvestigations'])->name('lab-investigations');
        Route::post('/lab-note/{serviceId}',     [OpdController::class, 'storeLabNote'])->name('lab-note.store');

        Route::get('/{id}/procedures',           [OpdController::class, 'procedures'])->name('procedures');
        Route::get('/procedure-note/{svcId}',    [OpdController::class, 'getProcedureNote'])->name('procedure-note.get');
        Route::post('/procedure-note/{svcId}',   [OpdController::class, 'storeProcedureNote'])->name('procedure-note.store');

        Route::get('/{id}/visits',               [OpdController::class, 'visits'])->name('visits');
        Route::get('/{id}/wellness',             [WellnessController::class, 'index'])->defaults('module', 'opd')->whereNumber('id')->name('wellness');
        Route::post('/{id}/wellness',            [WellnessController::class, 'store'])->defaults('module', 'opd')->whereNumber('id')->name('wellness.store');
        Route::get('/{id}/immunization',         [ImmunizationController::class, 'index'])->defaults('module', 'opd')->whereNumber('id')->name('immunization');
        Route::post('/{id}/immunization',        [ImmunizationController::class, 'store'])->defaults('module', 'opd')->whereNumber('id')->name('immunization.store');
        Route::get('/{id}/patient-documents',    [PatientDocumentController::class, 'page'])->defaults('module', 'opd')->whereNumber('id')->name('patient-documents');
    });

    // ── Lab Results ─────────────────────────────────────────
    Route::prefix('lab-results')->name('lab-results.')->group(function () {
        // Old per-visit "Lab Results" tab (legacy has none) now redirects to the Lab Investigations list;
        // per-service result entry routes live in routes/parity/lab.php
        Route::get('/opd/{opdId}',   [LabResultController::class, 'opdResults'])->whereNumber('opdId')->name('opd');
        Route::get('/ipd/{ipdId}',   [LabResultController::class, 'ipdResults'])->whereNumber('ipdId')->name('ipd');
        Route::get('/dcd/{dcdId}',   [LabResultController::class, 'dcdResults'])->whereNumber('dcdId')->name('dcd');
        Route::get('/subtests/{chargeId}', [LabResultController::class, 'subTestsForCharge'])->name('subtests');
    });

    // ── IPD ────────────────────────────────────────────────
    Route::prefix('ipd')->name('ipd.')->group(function () {
        Route::get('/',                          [IpdController::class, 'index'])->name('index');
        Route::get('/data',                      [IpdController::class, 'data'])->name('data');
        Route::get('/bed-picker',                [IpdController::class, 'bedPicker'])->name('bed-picker');
        Route::get('/opd-patient/{opdId}',       [IpdController::class, 'opdPatient'])->whereNumber('opdId')->name('opd-patient');
        Route::post('/',                         [IpdController::class, 'store'])->name('store');

        Route::get('/discharge-approvals',       [IpdController::class, 'dischargeApprovals'])->name('discharge-approvals');
        Route::get('/{id}',                      [IpdController::class, 'show'])->whereNumber('id')->name('show');

        Route::get('/{id}/prescriptions',        [IpdController::class, 'prescriptions'])->name('prescriptions');
        Route::post('/{id}/prescriptions',       [IpdController::class, 'storePrescription'])->name('prescriptions.store');
        Route::delete('/prescriptions/{rxId}',   [IpdController::class, 'destroyPrescription'])->name('prescriptions.destroy');

        Route::get('/{id}/charges',              [IpdController::class, 'charges'])->name('charges');
        Route::post('/{id}/charges',             [IpdController::class, 'storeCharge'])->name('charges.store');
        Route::get('/charges/{chargeId}/get',    [IpdController::class, 'getCharge'])->name('charges.get');
        Route::post('/charges/{chargeId}/update', [IpdController::class, 'updateCharge'])->name('charges.update');
        Route::delete('/charges/{chargeId}',     [IpdController::class, 'destroyCharge'])->name('charges.destroy');
        Route::post('/{id}/charges/collect-payments', [IpdController::class, 'collectPayments'])->name('charges.collect-payments');
        Route::get('/charges/{chargeId}/split-payments', [IpdController::class, 'getSplitPayments'])->name('charges.split-payments');
        Route::post('/charges/prescription-details', [IpdController::class, 'getPrescriptionDetails'])->name('charges.prescription-details');
        Route::post('/charges/prescription-td',   [IpdController::class, 'getPrescriptionTD'])->name('charges.prescription-td');
        Route::post('/charges/charge-for-prescription', [IpdController::class, 'getChargeForPrescription'])->name('charges.charge-for-prescription');
        Route::match(['get', 'post'], '/charges/charge-amount', [IpdController::class, 'getChargeAmount'])->name('charges.charge-amount');
        Route::match(['get', 'post'], '/charges/dropdown-ad-charges', [IpdController::class, 'dropdownAdCharges'])->name('charges.dropdown-ad-charges');
        Route::match(['get', 'post'], '/charges/dropdown-departments', [IpdController::class, 'dropdownDepartments'])->name('charges.dropdown-departments');
        Route::match(['get', 'post'], '/charges/get-department-name', [IpdController::class, 'getDepartmentName'])->name('charges.get-department-name');
        Route::match(['get', 'post'], '/charges/get-category-name', [IpdController::class, 'getCategoryName'])->name('charges.get-category-name');
        Route::match(['get', 'post'], '/charges/get-charge-name', [IpdController::class, 'getChargeName'])->name('charges.get-charge-name');

        Route::get('/{id}/consultations',             [IpdController::class, 'consultations'])->name('consultations');
        Route::post('/{id}/consultations',            [IpdController::class, 'storeConsultation'])->name('consultations.store');
        Route::get('/consultations/{cId}',            [IpdController::class, 'getConsultation'])->name('consultations.get');
        Route::match(['put', 'post'], '/consultations/{cId}/update', [IpdController::class, 'updateConsultation'])->name('consultations.update');
        Route::delete('/consultations/{cId}',         [IpdController::class, 'destroyConsultation'])->name('consultations.destroy');

        Route::get('/{id}/vital-monitoring',          [IpdController::class, 'vitalMonitoring'])->name('vital-monitoring');
        Route::post('/{id}/vital-monitoring',         [IpdController::class, 'storeVital'])->name('vital-monitoring.store');
        Route::put('/vital-monitoring/{id}',          [IpdController::class, 'updateVitalMonitoring'])->name('vital-monitoring.update');
        Route::get('/vital-monitoring/{id}/get',      [IpdController::class, 'getVitalMonitoring'])->name('vital-monitoring.get');
        Route::delete('/vital-monitoring/{id}',       [IpdController::class, 'destroyVitalMonitoring'])->name('vital-monitoring.destroy');

        Route::get('/{id}/intake-output',             [IpdController::class, 'intakeOutput'])->name('intake-output');
        Route::post('/{id}/intake-output',            [IpdController::class, 'storeIntakeOutput'])->name('intake-output.store');
        Route::put('/intake-output/{id}',             [IpdController::class, 'updateIntakeOutput'])->name('intake-output.update');
        Route::get('/intake-output/{id}/get',         [IpdController::class, 'getIntakeOutput'])->name('intake-output.get');
        Route::delete('/intake-output/{id}',          [IpdController::class, 'destroyIntakeOutput'])->name('intake-output.destroy');

        Route::get('/{id}/lab-investigations',   [IpdController::class, 'labInvestigations'])->name('lab-investigations');
        Route::post('/lab-note/{serviceId}',     [IpdController::class, 'storeLabNote'])->name('lab-note.store');

        Route::get('/{id}/treatments',           [IpdController::class, 'treatments'])->name('treatments');
        Route::post('/{id}/treatments',          [IpdController::class, 'storeTreatment'])->name('treatments.store');
        Route::put('/treatments/{id}',           [IpdController::class, 'updateTreatment'])->name('treatments.update');
        Route::get('/treatments/{id}/get',       [IpdController::class, 'getTreatment'])->name('treatments.get');

        Route::get('/{id}/bed-history',          [IpdController::class, 'bedHistory'])->name('bed-history');
        Route::post('/{id}/bed/change',          [IpdController::class, 'changeBed'])->name('bed.change');

        Route::get('/{id}/progress-sheet',       [IpdController::class, 'progressSheet'])->name('progress-sheet');
        Route::post('/{id}/progress-sheet',      [IpdController::class, 'saveProgressSheet'])->name('progress-sheet.save');

        Route::get('/{id}/rmo-notes',            [IpdController::class, 'rmoNotes'])->name('rmo-notes');
        Route::post('/{id}/rmo-notes',           [IpdController::class, 'saveRmoNotes'])->name('rmo-notes.save');

        Route::get('/{id}/patient-file',         [IpdController::class, 'patientFile'])->name('patient-file');
        Route::post('/{id}/patient-file',        [IpdController::class, 'savePatientFile'])->name('patient-file.save');

        Route::get('/{id}/discharge',            [IpdController::class, 'dischargeForm'])->name('discharge');
        Route::post('/{id}/discharge',           [IpdController::class, 'discharge'])->name('discharge.store');
        Route::post('/{id}/approve-discharge',   [IpdController::class, 'approveDischarge'])->name('discharge.approve');
        Route::get('/{id}/wellness',             [WellnessController::class, 'index'])->defaults('module', 'ipd')->whereNumber('id')->name('wellness');
        Route::post('/{id}/wellness',            [WellnessController::class, 'store'])->defaults('module', 'ipd')->whereNumber('id')->name('wellness.store');
        Route::get('/{id}/immunization',         [ImmunizationController::class, 'index'])->defaults('module', 'ipd')->whereNumber('id')->name('immunization');
        Route::post('/{id}/immunization',        [ImmunizationController::class, 'store'])->defaults('module', 'ipd')->whereNumber('id')->name('immunization.store');
        Route::get('/{id}/patient-documents',    [PatientDocumentController::class, 'page'])->defaults('module', 'ipd')->whereNumber('id')->name('patient-documents');
    });

    // ── DCD ────────────────────────────────────────────────
    Route::prefix('dcd')->name('dcd.')->group(function () {
        Route::get('/',                          [DcdController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/data',   [DcdController::class, 'data'])->name('data');
        Route::post('/',                         [DcdController::class, 'store'])->name('store');

        Route::get('/{id}',                      [DcdController::class, 'show'])->whereNumber('id')->name('show');

        Route::get('/{id}/prescriptions',        [DcdController::class, 'prescriptions'])->name('prescriptions');
        Route::post('/{id}/prescriptions',       [DcdController::class, 'storePrescription'])->name('prescriptions.store');

        Route::get('/{id}/charges',              [DcdController::class, 'charges'])->name('charges');
        Route::post('/{id}/charges',             [DcdController::class, 'storeCharge'])->name('charges.store');

        Route::get('/{id}/lab-investigations',   [DcdController::class, 'labInvestigations'])->name('lab-investigations');
        Route::post('/lab-note/{serviceId}',     [DcdController::class, 'storeLabNote'])->name('lab-note.store');

        Route::get('/{id}/discharge',            [DcdController::class, 'dischargeForm'])->name('discharge');
        Route::post('/{id}/discharge',           [DcdController::class, 'discharge'])->name('discharge.store');
        Route::post('/{id}/approve-discharge',   [DcdController::class, 'approveDischarge'])->name('discharge.approve');
        Route::get('/{id}/wellness',             [WellnessController::class, 'index'])->defaults('module', 'dcd')->whereNumber('id')->name('wellness');
        Route::post('/{id}/wellness',            [WellnessController::class, 'store'])->defaults('module', 'dcd')->whereNumber('id')->name('wellness.store');
        Route::get('/{id}/immunization',         [ImmunizationController::class, 'index'])->defaults('module', 'dcd')->whereNumber('id')->name('immunization');
        Route::post('/{id}/immunization',        [ImmunizationController::class, 'store'])->defaults('module', 'dcd')->whereNumber('id')->name('immunization.store');
        Route::get('/{id}/patient-documents',    [PatientDocumentController::class, 'page'])->defaults('module', 'dcd')->whereNumber('id')->name('patient-documents');
    });

    // ── Wellness (shared record-level routes) ──────────────
    Route::prefix('wellness')->name('wellness.')->group(function () {
        Route::get('/{id}',    [WellnessController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}',    [WellnessController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [WellnessController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // ── Immunization (shared record-level routes) ──────────
    Route::prefix('immunization')->name('immunization.')->group(function () {
        Route::get('/{id}',    [ImmunizationController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}',    [ImmunizationController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [ImmunizationController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // ── Patient Documents ──────────────────────────────────
    Route::prefix('patient-documents')->name('patient-documents.')->group(function () {
        Route::get('/',         [PatientDocumentController::class, 'index'])->name('index');
        Route::post('/',        [PatientDocumentController::class, 'store'])->name('store');
        Route::get('/types',    [PatientDocumentController::class, 'types'])->name('types');
        Route::get('/{id}/download', [PatientDocumentController::class, 'download'])->whereNumber('id')->name('download');
        Route::delete('/{id}',  [PatientDocumentController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // ── Billing ────────────────────────────────────────────
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/',                [BillingController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/data', [BillingController::class, 'data'])->name('data');
        Route::post('/',               [BillingController::class, 'store'])->name('store');
        Route::get('/module-charges',  [BillingController::class, 'getModuleCharges'])->name('module-charges');
        Route::get('/{id}/split-payments', [BillingController::class, 'getBillingSplitPayments'])->whereNumber('id')->name('split-payments');
        Route::get('/{id}',            [BillingController::class, 'show'])->whereNumber('id')->name('show');
        Route::patch('/{id}/cancel',        [BillingController::class, 'cancelInvoice'])->whereNumber('id')->name('cancel');
        Route::delete('/{id}',              [BillingController::class, 'destroy'])->name('destroy');
    });

    // ── Sale Bills ─────────────────────────────────────────
    Route::prefix('sale-bills')->name('sale-bills.')->group(function () {
        Route::get('/',                [SaleBillController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/data', [SaleBillController::class, 'data'])->name('data');
        Route::post('/reset-invoice-number-two', [SaleBillController::class, 'resetInvoiceNumberTwo'])->name('reset-invoice-number-two');
        Route::post('/{id}/payment',   [SaleBillController::class, 'collectPayment'])->name('payment');
    });

    // ── Staff ──────────────────────────────────────────────
    // All staff routes live in routes/parity/staff.php

    // ── Beds ───────────────────────────────────────────────
    Route::prefix('beds')->name('beds.')->group(function () {
        Route::get('/',                  [BedController::class, 'index'])->name('index');
        Route::get('/ui',                [BedController::class, 'bedsUi'])->name('ui');
        Route::get('/data',              [BedController::class, 'data'])->name('data');
        Route::get('/available',         [BedController::class, 'available'])->name('available');
        Route::post('/',                 [BedController::class, 'store'])->name('store');
        Route::post('/copy',             [BedController::class, 'copyBed'])->name('copy');
        Route::post('/empty',            [BedController::class, 'emptyBed'])->name('empty');
        Route::get('/floor-areas-by-floor/{floorId}', [BedController::class, 'floorAreasByFloor'])->whereNumber('floorId')->name('floor-areas-by-floor');
        Route::get('/{id}',              [BedController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/{id}',             [BedController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}',           [BedController::class, 'destroy'])->whereNumber('id')->name('destroy');

        Route::get('/floors',            [BedController::class, 'floors'])->name('floors');
        Route::get('/floors/{id}',       [BedController::class, 'getFloor'])->whereNumber('id')->name('floors.show');
        Route::post('/floors',           [BedController::class, 'storeFloor'])->name('floors.store');
        Route::post('/floors/{id}',      [BedController::class, 'updateFloor'])->whereNumber('id')->name('floors.update');

        Route::get('/ward-categories',   [BedController::class, 'wardCategories'])->name('ward-categories');
        Route::get('/ward-categories/{id}', [BedController::class, 'getWardCategory'])->whereNumber('id')->name('ward-categories.show');
        Route::post('/ward-categories',  [BedController::class, 'storeWardCategory'])->name('ward-categories.store');
        Route::post('/ward-categories/{id}', [BedController::class, 'updateWardCategory'])->whereNumber('id')->name('ward-categories.update');

        Route::get('/floor-areas',       [MasterController::class, 'floorAreas'])->name('floor-areas');
        Route::get('/floor-areas/{id}',  [MasterController::class, 'getFloorArea'])->whereNumber('id')->name('floor-areas.show');
        Route::post('/floor-areas',      [MasterController::class, 'storeFloorArea'])->name('floor-areas.store');
        Route::post('/floor-areas/{id}', [MasterController::class, 'updateFloorArea'])->whereNumber('id')->name('floor-areas.update');
    });

    // ── Charges ────────────────────────────────────────────
    Route::prefix('charges')->name('charges.')->group(function () {
        Route::get('/',                  [ChargeController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/data', [ChargeController::class, 'data'])->name('data');
        Route::get('/autocomplete',      [ChargeController::class, 'autocomplete'])->name('autocomplete');
        Route::get('/categories-by-dept/{deptId?}', [ChargeController::class, 'categoriesByDepartment'])->name('categories-by-dept');
        Route::post('/update-code',       [ChargeController::class, 'updateCode'])->name('update-code');
        Route::post('/',                 [ChargeController::class, 'store'])->name('store');
        Route::get('/{id}',              [ChargeController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/{id}',             [ChargeController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}',           [ChargeController::class, 'destroy'])->whereNumber('id')->name('destroy');

        Route::get('/departments',       [ChargeController::class, 'departments'])->name('departments');
        Route::post('/departments',      [ChargeController::class, 'storeDepartment'])->name('departments.store');
    });

    // ── Master ─────────────────────────────────────────────
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/tpa',                   [MasterController::class, 'tpa'])->name('tpa');
        Route::get('/tpa/{id}',              [MasterController::class, 'getTpa'])->whereNumber('id')->name('tpa.show');
        Route::post('/tpa',                  [MasterController::class, 'storeTpa'])->name('tpa.store');
        Route::delete('/tpa/{id}',           [MasterController::class, 'destroyTpa'])->name('tpa.destroy');

        Route::get('/pharmacy',              [MasterController::class, 'pharmacy'])->name('pharmacy');
        Route::post('/pharmacy',             [MasterController::class, 'storeProduct'])->name('pharmacy.store');
        Route::delete('/pharmacy/{id}',      [MasterController::class, 'destroyProduct'])->name('pharmacy.destroy');
        Route::post('/pharmacy/search',      [MasterController::class, 'searchProducts'])->name('pharmacy.search');

        Route::get('/pharmacy-categories',   [MasterController::class, 'pharmacyCategories'])->name('pharmacy-categories');
        Route::post('/pharmacy-categories',  [MasterController::class, 'storePharmacyCategory'])->name('pharmacy-categories.store');

        Route::get('/charge-categories',     [MasterController::class, 'chargeCategories'])->name('charge-categories');
        Route::get('/charge-categories/{id}', [MasterController::class, 'getChargeCategory'])->whereNumber('id')->name('charge-categories.show');
        Route::post('/charge-categories',    [MasterController::class, 'storeChargeCategory'])->name('charge-categories.store');

        Route::get('/biochemistry-units',          [MasterController::class, 'biochemistryUnits'])->name('biochemistry-units');
        Route::get('/biochemistry-units/options',  [MasterController::class, 'bunitOptions'])->name('biochemistry-units.options');
        Route::get('/biochemistry-units/{id}',     [MasterController::class, 'getUnit'])->whereNumber('id')->name('biochemistry-units.show');
        Route::post('/biochemistry-units',         [MasterController::class, 'storeUnit'])->name('biochemistry-units.store');
        Route::delete('/biochemistry-units/{id}',  [MasterController::class, 'destroyUnit'])->whereNumber('id')->name('biochemistry-units.destroy');

        Route::get('/biochemistry-tests',          [MasterController::class, 'biochemistryTests'])->name('biochemistry-tests');

        Route::get('/biochemistry-sub-tests',      [MasterController::class, 'biochemistrySubTests'])->name('biochemistry-sub-tests');
        Route::get('/biochemistry-sub-tests/{id}', [MasterController::class, 'getSubTest'])->whereNumber('id')->name('biochemistry-sub-tests.show');
        Route::post('/biochemistry-sub-tests',     [MasterController::class, 'storeBiochemistrySubTest'])->name('biochemistry-sub-tests.store');
        Route::delete('/biochemistry-sub-tests/{id}', [MasterController::class, 'destroyBiochemistrySubTest'])->whereNumber('id')->name('biochemistry-sub-tests.destroy');

        Route::get('/biochemistry',          [MasterController::class, 'biochemistry'])->name('biochemistry');
        Route::post('/biochemistry/subtest', [MasterController::class, 'storeBiochemistrySubTest'])->name('biochemistry.subtest.store');
        Route::post('/biochemistry/subtest/{id}', [MasterController::class, 'updateBiochemistrySubTest'])->name('biochemistry.subtest.update');
        Route::delete('/biochemistry/subtest/{id}', [MasterController::class, 'destroyBiochemistrySubTest'])->name('biochemistry.subtest.destroy');
        Route::post('/biochemistry/unit',    [MasterController::class, 'storeUnit'])->name('biochemistry.unit.store');

        Route::get('/diseases',              [MasterController::class, 'diseases'])->name('diseases');
        Route::post('/diseases',             [MasterController::class, 'storeDisease'])->name('diseases.store');
        Route::match(['post', 'put'], '/diseases/{id}', [MasterController::class, 'updateDisease'])->whereNumber('id')->name('diseases.update');
        Route::get('/diseases/{id}',         [MasterController::class, 'getDisease'])->whereNumber('id')->name('diseases.get');
        Route::delete('/diseases/{id}',      [MasterController::class, 'destroyDisease'])->whereNumber('id')->name('diseases.destroy');

        Route::get('/floor-areas',           [MasterController::class, 'floorAreas'])->name('floor-areas');
        Route::get('/floor-areas/{id}',      [MasterController::class, 'getFloorArea'])->whereNumber('id')->name('floor-areas.show');
        Route::post('/floor-areas',          [MasterController::class, 'storeFloorArea'])->name('floor-areas.store');
        Route::post('/floor-areas/{id}',     [MasterController::class, 'updateFloorArea'])->whereNumber('id')->name('floor-areas.update');
        Route::delete('/floor-areas/{id}',   [MasterController::class, 'destroyFloorArea'])->whereNumber('id')->name('floor-areas.destroy');

        Route::get('/hr-departments',        [MasterController::class, 'hrDepartments'])->name('hr-departments');
        Route::post('/hr-departments',       [MasterController::class, 'storeHrDepartment'])->name('hr-departments.store');
        Route::post('/designations',         [MasterController::class, 'storeDesignation'])->name('designations.store');
    });

    // ── Roles & Permissions ───────────────────────────────
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/',                      [RoleController::class, 'index'])->name('index');
        Route::post('/',                     [RoleController::class, 'store'])->name('store');
        Route::get('/{id}/permissions',      [RoleController::class, 'permissions'])->name('permissions');
        Route::post('/{id}/permissions',     [RoleController::class, 'savePermissions'])->name('permissions.save');
        Route::delete('/{id}',               [RoleController::class, 'destroy'])->name('destroy');
    });

    // ── Settings ───────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/',           [SettingController::class, 'index'])->name('index');
        Route::post('/',          [SettingController::class, 'update'])->name('update');
        Route::post('/id-fields', [SettingController::class, 'updateIdFields'])->name('id-fields');
        Route::post('/unlock',    [SettingController::class, 'unlock'])->name('unlock');
        Route::post('/lock',      [SettingController::class, 'lock'])->name('lock');
    });

    // ── Database Backups ────────────────────────────────────
    Route::prefix('db-backups')->name('db-backups.')->group(function () {
        Route::get('/',                     [DbBackupController::class, 'index'])->name('index');
        Route::post('/',                    [DbBackupController::class, 'store'])->name('store');
        Route::post('/verify-password',     [DbBackupController::class, 'verifyPassword'])->name('verify-password');
        Route::post('/{filename}/download', [DbBackupController::class, 'verifyAndDownload'])->name('verify-download');
        Route::get('/{filename}/download',  [DbBackupController::class, 'download'])->name('download');
        Route::delete('/{filename}',        [DbBackupController::class, 'destroy'])->name('destroy');
    });
    Route::get('master/backups',           [DbBackupController::class, 'index'])->name('master.backups');

    // ── Reports ────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/collections',                 [ReportController::class, 'collections'])->name('collections');
        Route::match(['get', 'post'], '/collections/data', [ReportController::class, 'collectionsData'])->name('collections.data');
        Route::post('/create-table-pdf',           [ReportController::class, 'createTablePdf'])->name('create-table-pdf');
        Route::get('/opd',                         [ReportController::class, 'opd'])->name('opd');
        Route::get('/ipd',                         [ReportController::class, 'ipd'])->name('ipd');
    });

    // ── PHI Audit Log ──────────────────────────────────────
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/',        [AuditLogController::class, 'index'])->name('index');
        Route::post('/data',   [AuditLogController::class, 'data'])->name('data');
        Route::get('/export',  [AuditLogController::class, 'export'])->name('export');
    });

    // ── PDF Reports ────────────────────────────────────────
    Route::prefix('pdf')->name('pdf.')->group(function () {
        // OPD
        Route::get('/opd/{opdId}/prescription',       [PdfReportController::class, 'opdPrescription'])->name('opd.prescription');
        Route::get('/opd/{opdId}/bill/{chargeId}',    [PdfReportController::class, 'opdBill'])->name('opd.bill');
        Route::get('/opd/{opdId}/visit-summary',      [PdfReportController::class, 'opdVisitSummary'])->name('opd.visit-summary');
        Route::get('/opd/{opdId}/consolidated-bill',  [PdfReportController::class, 'opdConsolidatedBill'])->name('opd.consolidated-bill');
        Route::get('/opd/{opdId}/lab-report',         [PdfReportController::class, 'opdLabReport'])->name('opd.lab-report');
        Route::get('/receipt/{chargeId}',             [PdfReportController::class, 'receipt'])->name('receipt');

        // IPD
        Route::get('/ipd/{ipdId}/prescription',       [PdfReportController::class, 'ipdPrescription'])->name('ipd.prescription');
        Route::get('/ipd/{ipdId}/discharge-summary',  [PdfReportController::class, 'ipdDischargeSummary'])->name('ipd.discharge-summary');
        Route::get('/ipd/{ipdId}/consolidated-bill',  [PdfReportController::class, 'ipdConsolidatedBill'])->name('ipd.consolidated-bill');
        Route::get('/ipd/{ipdId}/discharge-bill',     [PdfReportController::class, 'ipdDischargeBill'])->name('ipd.discharge-bill');
        Route::get('/ipd/{ipdId}/lab-report',         [PdfReportController::class, 'ipdLabReport'])->name('ipd.lab-report');
        Route::get('/ipd/{ipdId}/admission-consent',  [PdfReportController::class, 'admissionConsent'])->name('ipd.admission-consent');

        // DCD
        Route::get('/dcd/{dcdId}/prescription',       [PdfReportController::class, 'dcdPrescription'])->name('dcd.prescription');
        Route::get('/dcd/{dcdId}/discharge-summary',  [PdfReportController::class, 'dcdDischargeSummary'])->name('dcd.discharge-summary');
        Route::get('/dcd/{dcdId}/consolidated-bill',  [PdfReportController::class, 'dcdConsolidatedBill'])->name('dcd.consolidated-bill');
        Route::get('/dcd/{dcdId}/lab-report',         [PdfReportController::class, 'dcdLabReport'])->name('dcd.lab-report');
    });

    // ── Periodic Medicine ──────────────────────────────────
    Route::prefix('periodic-medicine')->name('periodic-medicine.')->group(function () {
        Route::get('/',                                  [PeriodicMedicineController::class, 'index'])->name('index');
        Route::get('/data',                              [PeriodicMedicineController::class, 'data'])->name('data');
        Route::match(['get', 'post'], '/issue-detail',   [PeriodicMedicineController::class, 'getIssueMedicineDetail'])->name('issue-detail');
        Route::post('/',                                 [PeriodicMedicineController::class, 'store'])->name('store');

        Route::get('due-days',                           [PeriodicMedicineController::class, 'dueDays'])->name('due-days');
        Route::match(['get', 'post'], 'due-days/data',   [PeriodicMedicineController::class, 'dueDaysData'])->name('due-days.data');

        Route::get('issue-list',                         [PeriodicMedicineController::class, 'issueList'])->name('issue-list');
        Route::match(['get', 'post'], 'issue-list/data', [PeriodicMedicineController::class, 'issueListData'])->name('issue-list.data');
        Route::match(['get', 'post'], 'issue-list/patient-record', [PeriodicMedicineController::class, 'getPatientMedicineRecord'])->name('issue-list.patient-record');
        Route::match(['get', 'post'], 'issue-list/issue-medicines', [PeriodicMedicineController::class, 'getPatientIssueMedicines'])->name('issue-list.issue-medicines');
        Route::post('issue-list/save',                   [PeriodicMedicineController::class, 'storePatientMedicine'])->name('issue-list.save');
        Route::post('issue-list/update',                 [PeriodicMedicineController::class, 'updatePatientMedicine'])->name('issue-list.update');
        Route::match(['post', 'delete'], 'issue-list/delete', [PeriodicMedicineController::class, 'deletePatientIssuedMedicine'])->name('issue-list.delete');
        Route::post('issue-list/save-patient',           [PeriodicMedicineController::class, 'savePatient'])->name('issue-list.save-patient');
        Route::match(['get', 'post'], 'issue-list/dropdown-patients', [PeriodicMedicineController::class, 'dropdownPatients'])->name('issue-list.dropdown-patients');
        Route::match(['get', 'post'], 'issue-list/dropdown-medicines', [PeriodicMedicineController::class, 'dropdownMedicines'])->name('issue-list.dropdown-medicines');
        Route::match(['get', 'post'], 'issue-list/dropdown-dose-interval', [PeriodicMedicineController::class, 'dropdownDoseInterval'])->name('issue-list.dropdown-dose-interval');

        Route::get('{patientId}/issued',                 [PeriodicMedicineController::class, 'issuedList'])->whereNumber('patientId')->name('issued');
        Route::get('/{id}',                              [PeriodicMedicineController::class, 'show'])->whereNumber('id')->name('show');
        Route::delete('/{id}',                           [PeriodicMedicineController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });


    // ── IPD new tabs ───────────────────────────────
    Route::get('ipd/{id}/procedures',              [IpdController::class, 'procedures'])->name('ipd.procedures');
    Route::get('ipd/procedure-note/{serviceId}',   [IpdController::class, 'getProcedureNote'])->name('ipd.procedure-note.get');
    Route::post('ipd/procedure-note/{serviceId}',  [IpdController::class, 'storeProcedureNote'])->name('ipd.procedure-note.store');
    Route::get('ipd/{id}/medications',             [IpdController::class, 'medications'])->name('ipd.medications');

    // ── DCD new tabs ───────────────────────────────
    Route::get('dcd/{id}/patient-file',            [DcdController::class, 'patientFile'])->name('dcd.patient-file');
    Route::post('dcd/{id}/patient-file',           [DcdController::class, 'saveDcdPatientFile'])->name('dcd.patient-file.save');
    Route::get('dcd/{id}/procedures',              [DcdController::class, 'procedures'])->name('dcd.procedures');
    Route::get('dcd/procedure-note/{serviceId}',   [DcdController::class, 'getProcedureNote'])->name('dcd.procedure-note.get');
    Route::post('dcd/procedure-note/{serviceId}',  [DcdController::class, 'storeProcedureNote'])->name('dcd.procedure-note.store');
    Route::get('dcd/{id}/medications',             [DcdController::class, 'medications'])->name('dcd.medications');
    Route::get('dcd/{id}/visits',                  [DcdController::class, 'visits'])->name('dcd.visits');
    Route::get('dcd/discharge-approvals',          [DcdController::class, 'dischargeApprovals'])->name('dcd.discharge-approvals');

    // ── PDF new ──────────────────────────────────────
    Route::get('pdf/ipd/{ipdId}/treatment',         [PdfReportController::class, 'ipdTreatmentPdf'])->name('pdf.ipd.treatment');
    Route::get('pdf/ipd/{ipdId}/vital-monitoring',  [PdfReportController::class, 'ipdVitalMonitoringPdf'])->name('pdf.ipd.vital-monitoring');
    Route::get('pdf/ipd/{ipdId}/intake-output',     [PdfReportController::class, 'ipdIntakeOutputPdf'])->name('pdf.ipd.intake-output');
    Route::get('pdf/dcd/{dcdId}/discharge-bill',    [PdfReportController::class, 'dcdDischargeBill'])->name('pdf.dcd.discharge-bill');
    Route::get('pdf/sale-bills',                    [PdfReportController::class, 'saleBillsReport'])->name('pdf.sale-bills');
    Route::get('pdf/collections',                   [PdfReportController::class, 'collectionsReport'])->name('pdf.collections');
    Route::get('pdf/{filename}', GeneratedPdfController::class)->where('filename', '[A-Za-z0-9._\- ]+\.pdf')->name('pdf.static-file');    Route::get('reports/pdf/{filename}', GeneratedPdfController::class)->where('filename', '[A-Za-z0-9._\- ]+\.pdf')->name('reports.pdf.static-file');
    // ── ABDM expanded ─────────────────────────────
    Route::prefix('abdm')->name('abdm.')->group(function () {
        Route::get('/abha-account-services', [AbdmController::class, 'abhaAccountServices'])->name('abha-account-services');
        Route::get('/consent-requests',      [AbdmController::class, 'consentRequests'])->name('consent-requests');
        Route::post('/consent-requests',     [AbdmController::class, 'initiateConsentRequest'])->name('consent-requests.store');

        // settings page + its POST actions: Controllers/Abdm/AbdmSettingsController (routes/parity/abdm.php)
        // Link token (M2 Step 1)
        Route::post('/link-token/check',     [AbdmController::class, 'checkLinkToken'])->name('link-token.check');
        Route::post('/link-token/request',   [AbdmController::class, 'requestLinkToken'])->name('link-token.request');
        // Care context (M2 Step 2)
        Route::post('/opd/{opdId}/care-context/check', [AbdmController::class, 'checkOpdCareContext'])->name('opd.care-context.check');
        Route::post('/opd/{opdId}/care-context/link',  [AbdmController::class, 'linkOpdCareContext'])->name('opd.care-context.link');
        Route::post('/ipd/{ipdId}/care-context/check', [AbdmController::class, 'checkIpdCareContext'])->name('ipd.care-context.check');
        Route::post('/ipd/{ipdId}/care-context/link',  [AbdmController::class, 'linkIpdCareContext'])->name('ipd.care-context.link');
        Route::post('/dcd/{dcdId}/care-context/check', [AbdmController::class, 'checkDcdCareContext'])->name('dcd.care-context.check');
        Route::post('/dcd/{dcdId}/care-context/link',  [AbdmController::class, 'linkDcdCareContext'])->name('dcd.care-context.link');
        // HIU
        Route::post('/hiu/consent/request',  [AbdmController::class, 'hiuRequestConsent'])->name('hiu.consent.request');
        Route::post('/hiu/artefact/fetch',   [AbdmController::class, 'hiuFetchArtefact'])->name('hiu.artefact.fetch');
        Route::post('/hiu/records/list',       [AbdmController::class, 'hiuListRecords'])->name('hiu.records.list');
        Route::post('/hiu/health-data/request', [AbdmController::class, 'hiuRequestHealthData'])->name('hiu.health-data.request');
        Route::post('/hiu/records/detail',      [AbdmController::class, 'hiuGetRecordDetail'])->name('hiu.records.detail');

        // Identity (M1 - ABHA verification)
        // OTP endpoints are rate limited (AppServiceProvider::registerRateLimiters): "send" makes ABDM text a real person,
        // "verify" is where OTPs could be guessed.
        Route::prefix('identity')->name('identity.')->group(function () {
            Route::post('/aadhaar-send-otp',       [AbdmIdentityController::class, 'aadhaarSendOtp'])->middleware('throttle:abdm-otp-send')->name('aadhaar-send-otp');
            Route::post('/aadhaar-verify-otp',     [AbdmIdentityController::class, 'aadhaarVerifyOtp'])->middleware('throttle:abdm-otp-verify')->name('aadhaar-verify-otp');
            Route::post('/aadhaar-create-address', [AbdmIdentityController::class, 'aadhaarCreateAbhaAddress'])->name('aadhaar-create-address');
            Route::post('/abha-login-send-otp',    [AbdmIdentityController::class, 'abhaLoginSendOtp'])->middleware('throttle:abdm-otp-send')->name('abha-login-send-otp');
            Route::post('/abha-login-verify-otp',  [AbdmIdentityController::class, 'abhaLoginVerifyOtp'])->middleware('throttle:abdm-otp-verify')->name('abha-login-verify-otp');
            Route::post('/aadhaar-login-send-otp', [AbdmIdentityController::class, 'aadhaarLoginSendOtp'])->middleware('throttle:abdm-otp-send')->name('aadhaar-login-send-otp');
            Route::post('/aadhaar-login-verify-otp',[AbdmIdentityController::class,'aadhaarLoginVerifyOtp'])->middleware('throttle:abdm-otp-verify')->name('aadhaar-login-verify-otp');
            Route::post('/download-abha-card',     [AbdmIdentityController::class, 'downloadAbhaCard'])->name('download-abha-card');
            Route::post('/save-profile-photo',     [AbdmIdentityController::class, 'saveProfilePhoto'])->name('save-profile-photo');
            Route::post('/check-identity-match',   [AbdmIdentityController::class, 'checkIdentityMatch'])->name('check-identity-match');
            Route::post('/mobile-send-otp',          [AbdmIdentityController::class, 'mobileSendOtp'])->middleware('throttle:abdm-otp-send')->name('mobile-send-otp');
            Route::post('/mobile-verify-otp',        [AbdmIdentityController::class, 'mobileVerifyOtp'])->middleware('throttle:abdm-otp-verify')->name('mobile-verify-otp');

            // ABHA account services: update mobile / email (legacy abha_account_ajax.php)
            Route::post('/update-mobile/login-otp',   [AbdmIdentityController::class, 'updateMobileLoginOtp'])->middleware('throttle:abdm-otp-send')->name('update-mobile.login-otp');
            Route::post('/update-mobile/verify-login',[AbdmIdentityController::class, 'updateMobileVerifyLogin'])->middleware('throttle:abdm-otp-verify')->name('update-mobile.verify-login');
            Route::post('/update-mobile/send-otp',    [AbdmIdentityController::class, 'updateMobileSendOtp'])->middleware('throttle:abdm-otp-send')->name('update-mobile.send-otp');
            Route::post('/update-mobile/verify-otp',  [AbdmIdentityController::class, 'updateMobileVerifyOtp'])->middleware('throttle:abdm-otp-verify')->name('update-mobile.verify-otp');
            Route::post('/update-email/login-otp',    [AbdmIdentityController::class, 'updateEmailLoginOtp'])->middleware('throttle:abdm-otp-send')->name('update-email.login-otp');
            Route::post('/update-email/verify-login', [AbdmIdentityController::class, 'updateEmailVerifyLogin'])->middleware('throttle:abdm-otp-verify')->name('update-email.verify-login');
            Route::post('/update-email/send-link',    [AbdmIdentityController::class, 'updateEmailSendLink'])->middleware('throttle:abdm-otp-send')->name('update-email.send-link');
        });
    });

    // ── IPD new routes ──────────────────────────────────────
    Route::get('ipd/{id}/visits',                [IpdController::class, 'visits'])->name('ipd.visits');
    Route::post('ipd/visits/detail',             [IpdController::class, 'getVisitDetail'])->name('ipd.visits.detail');
    Route::post('opd/visits/detail',             [IpdController::class, 'getVisitDetail'])->name('opd.visits.detail');
    Route::post('dcd/visits/detail',             [IpdController::class, 'getVisitDetail'])->name('dcd.visits.detail');
    Route::match(['get', 'post'], 'abdm-settings.php', [AbdmSettingsController::class, 'legacy'])->name('abdm.settings.legacy');
    Route::get('db-backup.php', [DbBackupController::class, 'legacyBackup'])->name('db-backups.legacy-backup');
    Route::get('db_backups/{filename}', [DbBackupController::class, 'downloadDirect'])->name('db-backups.legacy-file');
    Route::match(['get', 'post'], 'rpt-opd-bill-selected.php', [PdfReportController::class, 'opdSelectedBills'])->name('opd.legacy.selected-bills-pdf');
    Route::match(['get', 'post'], 'rpt-ipd-bill-selected.php', [PdfReportController::class, 'ipdSelectedBills'])->name('ipd.legacy.selected-bills-pdf');
    Route::match(['get', 'post'], 'rpt-dcd-bill-selected.php', [PdfReportController::class, 'dcdSelectedBills'])->name('dcd.legacy.selected-bills-pdf');

    Route::get('ipd/discharged',                 [IpdController::class, 'discharged'])->name('ipd.discharged');
    Route::match(['get', 'post'], 'ipd/discharged/data', [IpdController::class, 'dischargedData'])->name('ipd.discharged.data');
    Route::post('ipd/discharged/update-date',     [IpdController::class, 'updateDischargeDate'])->name('ipd.discharged.update-date');
    Route::get('ipd/{id}/discharged',            [IpdController::class, 'viewDischarged'])->name('ipd.view-discharged');
    Route::get('ipd/{id}/view-discharge-approval',[IpdController::class, 'viewDischargeApproval'])->name('ipd.view-discharge-approval');

    // ── DCD new routes ──────────────────────────────────────
    Route::get('dcd/discharged',                 [DcdController::class, 'discharged'])->name('dcd.discharged');
    Route::match(['get', 'post'], 'dcd/discharged/data', [DcdController::class, 'dischargedData'])->name('dcd.discharged.data');
    Route::get('dcd/{id}/discharged',            [DcdController::class, 'viewDischarged'])->name('dcd.view-discharged');
    Route::get('dcd/{id}/view-discharge-approval',[DcdController::class, 'viewDischargeApproval'])->name('dcd.view-discharge-approval');


    // ── Sale Bills new routes ──────────────────────────────
    Route::get('sale-bills/grouped', [SaleBillController::class, 'grouped'])->name('sale-bills.grouped');
    Route::get('sale-bills/search',  [SaleBillController::class, 'search'])->name('sale-bills.search');

    // ── Master new routes ──────────────────────────────────
    Route::get('master/diseases/{id}/patients', [MasterController::class, 'diseasePatients'])->name('master.disease-patients');

    // ── Lab Results cross-module ───────────────────────────
    Route::get('lab-results', [LabResultController::class, 'allResults'])->name('lab-results.all');

    // ── Charge Category Charges ─────────────────────────
    Route::get('master/charge-categories/{id}/charges',    [MasterController::class, 'chargeCategoryCharges'])->name('master.charge-category-charges');
    Route::post('master/charge-categories/{id}/charges',   [MasterController::class, 'storeCategoryCharge'])->name('master.charge-category-charges.store');
    Route::delete('master/charges/{id}',                   [MasterController::class, 'destroyCategoryCharge'])->name('master.charges.destroy');


    // ── OPD prescription get/update ─────────────────────────────────
    Route::get('opd/prescriptions/{rxId}',   [OpdController::class, 'getPrescription'])->name('opd.prescriptions.get');
    Route::put('opd/prescriptions/{rxId}',   [OpdController::class, 'updatePrescription'])->name('opd.prescriptions.update');

    // ── IPD delete + edit ──────────────────────────────────────────
    Route::delete('ipd/vital-monitoring/{id}',      [IpdController::class, 'destroyVitalMonitoring'])->name('ipd.vital-monitoring.destroy');
    Route::delete('ipd/intake-output/{id}',         [IpdController::class, 'destroyIntakeOutput'])->name('ipd.intake-output.destroy');
    Route::delete('ipd/treatments/{id}',            [IpdController::class, 'destroyTreatment'])->name('ipd.treatments.destroy');
    Route::get('ipd/prescriptions/{rxId}/get',      [IpdController::class, 'getPrescription'])->name('ipd.prescriptions.get');
    Route::match(['put', 'post'], 'ipd/prescriptions/{rxId}', [IpdController::class, 'updatePrescription'])->name('ipd.prescriptions.update');
    Route::post('ipd/{ipdId}/discharge-datetime',   [IpdController::class, 'updateDischargeDateTime'])->name('ipd.discharge.update-datetime');

    // ── DCD delete ──────────────────────────────────────────────────
    Route::delete('dcd/charges/{id}',               [DcdController::class, 'destroyCharge'])->name('dcd.charges.destroy');
    Route::get('dcd/charges/{id}',                  [DcdController::class, 'getCharge'])->name('dcd.charges.get');
    Route::match(['put', 'post'], 'dcd/charges/{id}', [DcdController::class, 'updateCharge'])->name('dcd.charges.update');
    Route::post('dcd/{id}/collect-payments',        [DcdController::class, 'collectPayments'])->name('dcd.charges.collect-payments');
    Route::get('dcd/charges/{id}/split-payments',   [DcdController::class, 'getSplitPayments'])->name('dcd.charges.split-payments');
    Route::delete('dcd/prescriptions/{id}',         [DcdController::class, 'destroyPrescription'])->name('dcd.prescriptions.destroy');
    Route::get('dcd/prescriptions/{rxId}/get',      [DcdController::class, 'getPrescription'])->name('dcd.prescriptions.get');
    Route::match(['put', 'post'], 'dcd/prescriptions/{rxId}', [DcdController::class, 'updatePrescription'])->name('dcd.prescriptions.update');
    Route::post('dcd/{id}/shift-to-ipd',            [DcdController::class, 'shiftToIpd'])->name('dcd.shift-to-ipd');

    // ── IPD doctors (consultant list) ─────────────────────────
    Route::post('ipd/{id}/doctor',                  [IpdController::class, 'addDoctor'])->name('ipd.doctor.add');
    Route::delete('ipd/doctor/{ipdDoctorId}',       [IpdController::class, 'removeDoctor'])->name('ipd.doctor.remove');
    Route::post('ipd/{id}/tpa',                     [IpdController::class, 'updateTpa'])->name('ipd.tpa.update');
    Route::post('ipd/{id}/overview',                [IpdController::class, 'updateOverview'])->whereNumber('id')->name('ipd.overview.update');

    // ── PDF detailed discharge bills ───────────────────────
    Route::get('pdf/ipd/{ipdId}/discharge-detailed-bill', [PdfReportController::class, 'ipdDischargeDetailedBill'])->name('pdf.ipd.discharge-detailed-bill');
    Route::get('pdf/dcd/{dcdId}/discharge-detailed-bill', [PdfReportController::class, 'dcdDischargeDetailedBill'])->name('pdf.dcd.discharge-detailed-bill');

    // ── HIU consent status check ──────────────────────────
    Route::post('abdm/hiu/consent/status',          [AbdmController::class, 'hiuCheckConsentStatus'])->name('abdm.hiu.consent.status');

    // ── Biochemistry unit delete ───────────────────────────
    Route::delete('master/biochemistry/unit/{id}',   [MasterController::class, 'destroyUnit'])->name('master.biochemistry.unit.destroy');

    // ── Pharmacy category products ──────────────────────
    Route::get('master/pharmacy-categories/{id}/products', [MasterController::class, 'pharmacyCategoryProducts'])->name('master.pharmacy-category-products');

    // ── New PDF routes ───────────────────────────────────
    Route::get('pdf/opd/{opdId}/detailed-bill',           [PdfReportController::class, 'opdDetailedBill'])->name('pdf.opd.detailed-bill');
    Route::post('pdf/opd/selected-bills',                 [PdfReportController::class, 'opdSelectedBills'])->name('pdf.opd.selected-bills');
    Route::post('pdf/ipd/selected-bills',                 [PdfReportController::class, 'ipdSelectedBills'])->name('pdf.ipd.selected-bills');
    Route::post('pdf/dcd/selected-bills',                 [PdfReportController::class, 'dcdSelectedBills'])->name('pdf.dcd.selected-bills');
    Route::get('pdf/ipd/{ipdId}/patients-file',           [PdfReportController::class, 'ipdPatientsFile'])->name('pdf.ipd.patients-file');
    Route::get('pdf/staff-roster',                        [PdfReportController::class, 'staffRosterPdf'])->name('pdf.staff-roster');

    // ── OPD Card + Discharge by discharge_id aliases ──────────
    Route::get('pdf/opd/{opdId}/card',                    [PdfReportController::class, 'opdCard'])->name('pdf.opd.card');
    Route::get('pdf/opd/{opdId}/receipt',                 [PdfReportController::class, 'opdReceipt'])->name('pdf.opd.receipt');
    Route::get('pdf/opd/{opdId}/card-slip',               [PdfReportController::class, 'opdCardSlip'])->name('pdf.opd.card-slip');
    Route::get('pdf/discharge/{dischargeId}/ipd',         [PdfReportController::class, 'ipdDischargeCard'])->name('pdf.discharge.ipd');
    Route::get('pdf/discharge/{dischargeId}/dcd',         [PdfReportController::class, 'dcdDischargeCard'])->name('pdf.discharge.dcd');


});
