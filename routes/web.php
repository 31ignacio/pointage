<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
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
    Route::post('/pointage/check', [AttendanceController::class, 'check'])->name('attendance.check');
    Route::get('/historique', [AttendanceController::class, 'history'])->name('attendance.history');
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

    Route::get('/employes', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employes/{employee}/historique', [AttendanceController::class, 'employeeHistory'])->name('employees.history');
    Route::post('/employes', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employes/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
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
