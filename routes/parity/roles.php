<?php

use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

// Roles & permissions JSON endpoints used by the roles screen modals and the role-permissions screen.
// Included inside the authenticated group in routes/web.php; names start with `roles.`
// (covered by the `roles` => m_roles_and_permissions entry of config/route_permissions.php).
// roles.index / roles.store / roles.permissions / roles.destroy are declared in routes/web.php.
Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
    Route::get('{id}',               'show')->whereNumber('id')->name('show');
    Route::put('{id}',               'update')->whereNumber('id')->name('update');
    Route::put('{id}/permissions',   'savePermissions')->whereNumber('id')->name('permissions.update');
});
