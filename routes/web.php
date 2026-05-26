<?php

use App\Http\Controllers\Technician\AuthController;
use App\Http\Controllers\Technician\RegistrationController;
use Illuminate\Support\Facades\Route;

// Filament handles the root route (path '')

Route::get('/technician/login', fn () => redirect('/login'))->name('login');
Route::post('/technician/login', [AuthController::class, 'login'])->name('technician.login.store');
Route::post('/technician/logout', [AuthController::class, 'logout'])->name('technician.logout');

Route::middleware('auth')->prefix('technician/registrations')->name('technician.registrations.')->group(function () {
    Route::get('/', [RegistrationController::class, 'index'])->name('index');
    Route::get('/create', [RegistrationController::class, 'create'])->name('create');
    Route::post('/', [RegistrationController::class, 'store'])->name('store');
    Route::get('/{registration}', [RegistrationController::class, 'show'])->name('show');
    Route::get('/{registration}/edit', [RegistrationController::class, 'edit'])->name('edit');
    Route::put('/{registration}', [RegistrationController::class, 'update'])->name('update');
});
