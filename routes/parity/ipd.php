<?php

use App\Http\Controllers\IpdController;
use Illuminate\Support\Facades\Route;

// IPD module endpoints that have no equivalent in routes/web.php. Included inside the authenticated group in
// routes/web.php; every route name starts with `ipd.`, which is covered by the `ipd` entry of
// config/route_permissions.php.
Route::prefix('ipd')->name('ipd.')->controller(IpdController::class)->group(function () {
    // Medications tab "Make Copy": stores a new prescription (with its medicines) from an existing one.
    Route::post('{id}/prescriptions/copy', 'copyPrescription')->whereNumber('id')->name('prescriptions.copy');

    // Patient file JSON used to pre-fill the admission-examination form.
    Route::get('{id}/patient-file/data', 'getPatientFileJson')->whereNumber('id')->name('patient-file.data');
});
