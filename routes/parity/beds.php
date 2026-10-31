<?php

use App\Http\Controllers\BedController;
use Illuminate\Support\Facades\Route;

// Beds module: select2 sources used by the Add / Update Bed modals (beds/index). Included inside the authenticated
// group in routes/web.php; names start with `beds.` which is covered by the `beds` => m_beds entry of
// config/route_permissions.php. The floor / ward-category / bed / floor-area CRUD routes live in routes/web.php.
Route::prefix('beds/dropdowns')->name('beds.dropdowns.')->controller(BedController::class)->group(function () {
    Route::get('floors',          'floorOptions')->name('floors');
    Route::get('floor-areas',     'floorAreaOptions')->name('floor-areas');
    Route::get('ward-categories', 'wardCategoryOptions')->name('ward-categories');
    Route::get('bed-types',       'bedTypeOptions')->name('bed-types');
});
