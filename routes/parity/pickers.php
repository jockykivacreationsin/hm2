<?php

use App\Http\Controllers\PickerController;
use Illuminate\Support\Facades\Route;

// Module-neutral select2 sources (GET, `q` = search term, answer [{id, text}]). Included inside the authenticated group in
// routes/web.php; the `pickers` prefix is authorised for every module that uses them in config/route_permissions.php.
Route::prefix('pickers')->name('pickers.')->controller(PickerController::class)->group(function () {
    Route::get('doctors', 'doctors')->name('doctors');
    Route::get('months',  'months')->name('months');
});
