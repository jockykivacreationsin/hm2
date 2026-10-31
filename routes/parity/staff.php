<?php

use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

// Staff module (staff members, attendance, roster and salary pages + their JSON endpoints). Included inside the
// authenticated group in routes/web.php; every route name starts with `staff.` which is covered by the
// `staff` => m_staff entry of config/route_permissions.php.
// Literal paths are declared before the numeric `{id}` wildcards.
Route::prefix('staff')->name('staff.')->controller(StaffController::class)->group(function () {

    // ── Staff members ─────────────────────────────────────────
    Route::get('/',                    'index')->name('index');
    Route::post('/',                   'store')->name('store');
    Route::get('dropdown/{type}',      'dropdown')->where('type', 'departments|designations|specialities|user-roles|months')->name('dropdown');

    // ── Attendance ────────────────────────────────────────────
    Route::get('attendance',           'attendance')->name('attendance');
    // Not `POST attendance`: that exact URI is the public QR-kiosk endpoint (routes/api.php, api.staff.attendance).
    Route::post('attendance/bulk',     'storeAttendance')->name('attendance.store');
    Route::get('attendance/data',      'attendanceData')->name('attendance.data');
    Route::get('attendance/exists',    'attendanceExists')->name('attendance.exists');
    Route::get('attendance-calendar',  'attendanceCalendar')->name('attendance-calendar');
    Route::get('attendance-report',    'attendanceReport')->name('attendance-report');
    Route::match(['get', 'post'], 'attendance-report/data', 'attendanceReportData')->name('attendance-report.data');
    Route::get('attendance-chart',     'attendanceChartView')->name('attendance-chart');
    Route::match(['get', 'post'], 'attendance-chart/data',  'attendanceChartData')->name('attendance-chart.data');

    // ── Roster ────────────────────────────────────────────────

    Route::get('roster-calendar',      'rosterCalendar')->name('roster-calendar');
    Route::get('roster-dates',         'rosterDates')->name('roster-dates');
    Route::get('roster/staff-options', 'rosterStaffOptions')->name('roster.staff-options');
    Route::post('roster/add',          'addRoster')->name('roster.add');
    Route::get('roster/staff-wise',    'rosterStaffWise')->name('roster.staff-wise');
    Route::get('roster/staff-wise/data', 'rosterStaffWiseData')->name('roster.staff-wise.data');
    Route::delete('roster/{id}',       'removeRoster')->whereNumber('id')->name('roster.remove');
    Route::get('roster/{id}/replace-options', 'rosterReplaceOptions')->whereNumber('id')->name('roster.replace-options');
    Route::put('roster/{id}',          'replaceRoster')->whereNumber('id')->name('roster.replace');

    // ── Salary ────────────────────────────────────────────────
    Route::get('salary',               'salary')->name('salary');
    Route::get('salary/list',          'salaryList')->name('salary.list');
    Route::post('salary/payment',      'storeSalaryPayment')->name('salary.payment');
    Route::get('salary/payment/{id}',  'salaryPaymentDetail')->whereNumber('id')->name('salary.payment.detail');
    Route::post('salary/appraisal',    'storeSalaryAppraisal')->name('salary.appraisal');
    Route::get('salary-settings',      'salarySettings')->name('salary-settings');

    // ── Single staff member (wildcards last) ──────────────────
    Route::get('{id}',                 'show')->whereNumber('id')->name('show');
    Route::put('{id}',                 'update')->whereNumber('id')->name('update');
    Route::delete('{id}',              'destroy')->whereNumber('id')->name('destroy');
    Route::get('{id}/current-salary',  'currentSalary')->whereNumber('id')->name('current-salary');
    Route::get('{id}/appraisals',      'appraisalDetail')->whereNumber('id')->name('appraisals');
});
