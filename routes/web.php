<?php

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Everything is server-rendered (Blade). Access levels:
|   guest                      -> sign-in page only
|   auth + role:super_admin,admin -> the inventory (admin.* routes)
|   + role:super_admin          -> master data and user accounts
| Nothing is ever hard-deleted through these routes except unused master
| data options; devices/employees/accounts are retired or deactivated.
*/

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Sign-in (max 10 attempts per minute per IP)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [AuthController::class, 'home'])->name('home');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Super Admin and Admin
    Route::prefix('admin')->name('admin.')->middleware('role:super_admin,admin')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::post('company-scope', Admin\CompanyScopeController::class)->name('company-scope'); // sidebar company switcher

        // Devices: CRUD (no delete) + hand-over actions + purchase order file
        Route::resource('devices', Admin\DeviceController::class)->except('destroy');
        Route::post('devices/{device}/assign', [Admin\DeviceController::class, 'assign'])->name('devices.assign');
        Route::post('devices/{device}/return', [Admin\DeviceController::class, 'return'])->name('devices.return');
        Route::post('devices/{device}/status', [Admin\DeviceController::class, 'status'])->name('devices.status');
        Route::get('devices/{device}/po-file', [Admin\DeviceController::class, 'poFile'])->name('devices.po-file');

        Route::resource('employees', Admin\EmployeeController::class)->except('destroy');

        // Requests: file, then approve/reject -> complete (or cancel while pending)
        Route::get('requests', [Admin\RequestController::class, 'index'])->name('requests.index');
        Route::get('requests/create', [Admin\RequestController::class, 'create'])->name('requests.create');
        Route::post('requests', [Admin\RequestController::class, 'store'])->name('requests.store');
        Route::get('requests/{deviceRequest}', [Admin\RequestController::class, 'show'])->name('requests.show');
        Route::post('requests/{deviceRequest}/approve', [Admin\RequestController::class, 'approve'])->name('requests.approve');
        Route::post('requests/{deviceRequest}/reject', [Admin\RequestController::class, 'reject'])->name('requests.reject');
        Route::post('requests/{deviceRequest}/complete', [Admin\RequestController::class, 'complete'])->name('requests.complete');
        Route::post('requests/{deviceRequest}/cancel', [Admin\RequestController::class, 'cancel'])->name('requests.cancel');

        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit-logs/export', [Admin\AuditLogController::class, 'export'])->name('audit.export');

        // Super Admin only
        Route::middleware('role:super_admin')->group(function () {
            // dropdown lists (Lookup), device types and companies
            Route::get('master-data', [Admin\MasterDataController::class, 'index'])->name('master.index');
            Route::post('master-data', [Admin\MasterDataController::class, 'store'])->name('master.store');
            Route::put('master-data/{lookup}', [Admin\MasterDataController::class, 'update'])->name('master.update');
            Route::delete('master-data/{lookup}', [Admin\MasterDataController::class, 'destroy'])->name('master.destroy');
            Route::post('master-data/device-types', [Admin\MasterDataController::class, 'storeType'])->name('master.types.store');
            Route::put('master-data/device-types/{deviceType}', [Admin\MasterDataController::class, 'updateType'])->name('master.types.update');
            Route::post('master-data/companies', [Admin\MasterDataController::class, 'storeCompany'])->name('master.companies.store');
            Route::put('master-data/companies/{company}', [Admin\MasterDataController::class, 'updateCompany'])->name('master.companies.update');

            // login accounts
            Route::resource('users', Admin\UserController::class)->except(['show', 'destroy']);
        });
    });
});
