<?php

use App\Http\Controllers\DcdController;
use Illuminate\Support\Facades\Route;

// Day care (DCD): lookups / actions used by the DCD pages that had no named route of their own.
// Included inside the authenticated group in routes/web.php; names start with `dcd.` (covered by `dcd` => m_dcd).
// The rest of the DCD routes (list, prescriptions, charges, discharge, ...) live in routes/web.php.
Route::prefix('dcd')->name('dcd.')->controller(DcdController::class)->group(function () {
    // Create DCD modal: patient summary for an OPD number.
    Route::get('opd-patients/{opdId}', 'getOpdIdPatient')->whereNumber('opdId')->name('opd-patient');

    // Overview: vitals / symptoms / remarks (stored on the DCD's OPD visit).
    Route::put('{id}/overview', 'updateOverview')->whereNumber('id')->name('overview.update');

    // Patient file as JSON (summernote fields are filled from it).
    Route::get('{id}/patient-file/data', 'getPatientFileJson')->whereNumber('id')->name('patient-file.show');

    // Shift To IPD modal: current DCD / bed details, then the admission itself.
    Route::get('{id}/to-ipd', 'ipdAdmissionPrefill')->whereNumber('id')->name('to-ipd.show');
    Route::post('{id}/to-ipd', 'shiftToIpdLegacy')->whereNumber('id')->name('to-ipd.store');
});
