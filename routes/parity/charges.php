<?php

use App\Http\Controllers\ChargeLookupController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\SortOrderController;
use Illuminate\Support\Facades\Route;

// Charges master, pharmacy catalogue, shared charge lookups and generic row ordering (legacy ajax.php flags
// save_/update_ charge categories, charges, pharma categories and products, drag-sort, charge pickers).
// Included inside the authenticated group in routes/web.php; route names are covered by the `charges`, `master.*`,
// `charges.lookup` and `master.sort-order` entries of config/route_permissions.php.

// ── Shared charge lookups (OPD / IPD / DCD / billing charge + prescription modals, master charge forms) ──
Route::prefix('charges/lookup')->name('charges.lookup.')->controller(ChargeLookupController::class)->group(function () {
    Route::get('departments',             'departments')->name('departments');
    Route::get('categories',              'categories')->name('categories');
    Route::get('charges',                 'charges')->name('charges');
    Route::get('amount',                  'amount')->name('amount');
    Route::get('prescription-details',    'prescriptionDetails')->name('prescription-details');
    Route::get('prescription-charges',    'prescriptionCharges')->name('prescription-charges');
    Route::get('charge-for-prescription', 'chargeForPrescription')->name('charge-for-prescription');
    Route::get('department-name',         'departmentName')->name('department-name');
    Route::get('category-name',           'categoryName')->name('category-name');
    Route::get('charge-name',             'chargeName')->name('charge-name');
});

// ── Drag-sort of rows; {table} is limited to the whitelist in SortOrderController::SORTABLE_TABLES ──
Route::patch('sort-order/{table}', [SortOrderController::class, 'update'])
    ->where('table', implode('|', array_keys(SortOrderController::SORTABLE_TABLES)))
    ->name('master.sort-order.update');

Route::prefix('master')->name('master.')->controller(MasterController::class)->group(function () {

    // ── Charge categories (create: master.charge-categories.store, show: master.charge-categories.show) ──
    Route::match(['put', 'patch', 'post'], 'charge-categories/{id}', 'updateChargeCategory')->whereNumber('id')->name('charge-categories.update');

    // ── Charges of a category (list/create: master.charge-category-charges[.store], delete: master.charges.destroy) ──
    Route::get('charges/{id}',                  'showCategoryCharge')->whereNumber('id')->name('charges.show');
    Route::match(['put', 'patch'], 'charges/{id}', 'updateCategoryCharge')->whereNumber('id')->name('charges.update');
    Route::match(['put', 'patch'], 'charges/{id}/amount', 'updateCategoryChargeAmount')->whereNumber('id')->name('charges.amount.update');

    // ── Pharmacy product categories (list/create: master.pharmacy-categories[.store]) ──
    Route::get('pharmacy-categories/options',   'pharmacyCategoryOptions')->name('pharmacy-categories.options');
    Route::get('pharmacy-categories/{id}',      'showPharmacyCategory')->whereNumber('id')->name('pharmacy-categories.show');
    Route::match(['put', 'patch'], 'pharmacy-categories/{id}', 'updatePharmacyCategory')->whereNumber('id')->name('pharmacy-categories.update');

    // ── Products of a pharmacy category (list: master.pharmacy-category-products) ──
    Route::post('pharmacy-categories/{id}/products', 'storeCategoryProduct')->whereNumber('id')->name('pharmacy-category-products.store');
    Route::get('pharmacy-category-products/{id}',    'showCategoryProduct')->whereNumber('id')->name('pharmacy-category-products.show');
    Route::match(['put', 'patch'], 'pharmacy-category-products/{id}', 'updateCategoryProduct')->whereNumber('id')->name('pharmacy-category-products.update');
    Route::delete('pharmacy-category-products/{id}', 'destroyCategoryProduct')->whereNumber('id')->name('pharmacy-category-products.destroy');
});
