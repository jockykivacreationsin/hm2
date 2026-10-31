<?php

use App\Http\Controllers\MasterController;
use App\Http\Controllers\PeriodicMedicineController;
use Illuminate\Support\Facades\Route;

// Master data (TPA companies, biochemistry units / sub tests, diseases) and periodic-medicine pickers.
// Included inside the authenticated + module.permission group in routes/web.php; names stay under the covered
// `master.*` / `periodic-medicine.*` prefixes. The remaining list/show/store/destroy routes of these screens are in web.php.

Route::prefix('master')->name('master.')->controller(MasterController::class)->group(function () {
    // Update modals submit FormData with `_method=PUT`.
    Route::match(['put', 'patch', 'post'], 'tpa/{id}',                    'updateTpa')->whereNumber('id')->name('tpa.update');
    Route::match(['put', 'patch', 'post'], 'biochemistry-units/{id}',     'updateUnit')->whereNumber('id')->name('biochemistry-units.update');
    Route::match(['put', 'patch', 'post'], 'biochemistry-sub-tests/{id}', 'updateBiochemistrySubTest')->whereNumber('id')->name('biochemistry-sub-tests.update');
});

Route::prefix('periodic-medicine')->name('periodic-medicine.')->controller(PeriodicMedicineController::class)->group(function () {
    // select2 source (q, page) for the pharma product pickers.
    Route::get('issue-list/dropdown-pharma-product', 'dropdownPharmaProduct')->name('issue-list.dropdown-pharma-product');
});
