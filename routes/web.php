<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendancePasskeyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DeviceChangeRequestController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeePasskeyController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\RequireSecureWebAuthn;

/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profil', [PasswordController::class, 'edit'])->name('profile.password.edit');
    Route::put('/profil/mot-de-passe', [PasswordController::class, 'update'])->name('profile.password.update');
});

Route::redirect('/', '/pointage');

/*
|--------------------------------------------------------------------------
| Espace employé (authentifié) — pointage QR + historique
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/pointage', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::post('/pointage/check', [AttendanceController::class, 'check'])->middleware([RequireSecureWebAuthn::class, 'throttle:10,1'])->name('attendance.check');
    Route::post('/pointage/appareil/demande', [AttendanceController::class, 'requestDeviceChange'])->middleware([RequireSecureWebAuthn::class, 'throttle:5,1'])->name('attendance.device.request');
    Route::get('/historique', [AttendanceController::class, 'history'])->name('attendance.history');

    Route::middleware(RequireSecureWebAuthn::class)->group(function () {
        Route::get('/activation-empreinte', [EmployeePasskeyController::class, 'show'])->name('passkeys.activate');
        Route::post('/activation-empreinte/code', [EmployeePasskeyController::class, 'verifyActivationCode'])->middleware('throttle:5,1')->name('passkeys.activation-code');
        Route::get('/activation-empreinte/options', [EmployeePasskeyController::class, 'registrationOptions'])->middleware('throttle:10,1')->name('passkeys.registration-options');
        Route::post('/activation-empreinte/enregistrer', [EmployeePasskeyController::class, 'store'])->middleware('throttle:10,1')->name('passkeys.registration-store');
        Route::get('/pointage/passkey/options', [AttendancePasskeyController::class, 'options'])->middleware('throttle:10,1')->name('attendance.passkey.options');
        Route::post('/pointage/passkey/verify', [AttendancePasskeyController::class, 'verify'])->middleware('throttle:10,1')->name('attendance.passkey.verify');
        Route::post('/pointage/passkey/echec', [AttendancePasskeyController::class, 'failed'])->middleware('throttle:10,1')->name('attendance.passkey.failure');
    });
});

/*
|--------------------------------------------------------------------------
| Espace administration (auth + rôle admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export/{format}', [DashboardController::class, 'export'])
        ->where('format', 'pdf|xlsx')
        ->name('dashboard.export');

    Route::get('/pointage/qrcode', [AttendanceController::class, 'qrcode'])->name('attendance.qrcode');

    Route::get('/demandes-appareils', [DeviceChangeRequestController::class, 'index'])->name('devices.requests.index');
    Route::patch('/demandes-appareils/{deviceChangeRequest}/valider', [DeviceChangeRequestController::class, 'approve'])->name('devices.requests.approve');
    Route::patch('/demandes-appareils/{deviceChangeRequest}/rejeter', [DeviceChangeRequestController::class, 'reject'])->name('devices.requests.reject');

    Route::get('/employes', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employes/{employee}/historique', [AttendanceController::class, 'employeeHistory'])->name('employees.history');
    Route::post('/employes', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employes/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::post('/employes/{employee}/passkey/activation-code', [EmployeeController::class, 'generatePasskeyActivationCode'])->middleware('throttle:10,1')->name('employees.passkey.activation-code');
    Route::patch('/employes/{employee}/device-fallback', [EmployeeController::class, 'setDeviceFallback'])->name('employees.device-fallback');
    Route::delete('/employes/{employee}/appareil', [DeviceChangeRequestController::class, 'reset'])->name('employees.device.reset');
    Route::delete('/employes/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::get('/services', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/services', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/services/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/services/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::put('/sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
});
