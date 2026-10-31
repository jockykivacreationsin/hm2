<?php

use App\Http\Controllers\IpdController;
use App\Http\Controllers\LabResultController;
use App\Http\Controllers\OpdController;
use Illuminate\Support\Facades\Route;

// OPD-module endpoints that have no route in web.php: Select2 sources and the helpers of the charges page.
// Included inside the authenticated group in routes/web.php (names stay under the covered opd / dcd / lab-results prefixes).

// Doctor picker (consultant on the overview "Add Doctor" modal and the prescription forms).
Route::get('opd/doctors/dropdown', [OpdController::class, 'dropdownDoctors'])->name('opd.doctors.dropdown');

// Charges page pickers; the lookups live in IpdController because the IPD charges page uses the same ones.
Route::get('opd/charges/dropdown-ad-charges', [IpdController::class, 'dropdownAdCharges'])->name('opd.charges.dropdown-ad-charges');
Route::get('opd/charges/department-name',     [IpdController::class, 'getDepartmentName'])->name('opd.charges.department-name');
Route::get('opd/charges/charge-name',         [IpdController::class, 'getChargeName'])->name('opd.charges.charge-name');


// Test pickers shared by the OPD / IPD / DCD prescription, charge and lab modals.
Route::prefix('lab-results/dropdown')->name('lab-results.dropdown.')->group(function () {
    Route::get('pathology-tests', [LabResultController::class, 'dropdownPathologyTests'])->name('pathology-tests');
    Route::get('radiology-tests', [LabResultController::class, 'dropdownRadiologyTests'])->name('radiology-tests');
    Route::get('procedures',      [LabResultController::class, 'dropdownProcedures'])->name('procedures');
});
