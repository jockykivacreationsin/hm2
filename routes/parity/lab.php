<?php

use App\Http\Controllers\LabResultController;
use Illuminate\Support\Facades\Route;

// Lab investigations: per-service result entry, saved-result detail modal and radiology notes
// (legacy {opd,ipd,dcd}-lab-investigation-result.php + ajax.php savetestdetailedresults*/viewtestdetail*/*_radiology_note).
// Included inside the authenticated group in routes/web.php; names stay under the covered `lab-results` prefix.
Route::prefix('lab-results/{module}/service/{serviceId}')
    ->where(['module' => 'opd|ipd|dcd', 'serviceId' => '[0-9]+'])
    ->name('lab-results.')
    ->group(function () {
        Route::get('/',              [LabResultController::class, 'resultForm'])->name('result');
        Route::post('/',             [LabResultController::class, 'saveResult'])->name('result.save');
        Route::get('/detail',        [LabResultController::class, 'detail'])->name('detail');
        Route::get('/form-partial',  [LabResultController::class, 'resultFormPartial'])->name('result.form-partial');
        Route::get('/radiology-note',  [LabResultController::class, 'radiologyNote'])->name('radiology-note');
        Route::post('/radiology-note', [LabResultController::class, 'saveRadiologyNote'])->name('radiology-note.save');
    });
