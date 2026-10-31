<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

// Billing, sale bills, collections report and patients (formerly dispatched through ajax.php flags).
// Included inside the authenticated + module.permission group in routes/web.php.
// Billing list/show/split-payments/store/destroy, sale-bills.data / reset-invoice-number-two and
// reports.collections.data / reports.create-table-pdf are already declared in web.php.

// Edit Bill modal (FormData posted with _method=PUT).
Route::match(['put', 'patch', 'post'], 'billing/{id}', [BillingController::class, 'update'])->whereNumber('id')->name('billing.update');

// Patient picker (select2): [{id, text}] of patients that are not currently admitted.
Route::get('patients/dropdown', [PatientController::class, 'dropdown'])->name('patients.dropdown');
