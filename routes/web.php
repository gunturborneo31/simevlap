<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BankDataController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\KepmenController;
use App\Http\Controllers\OpdController;
use App\Http\Controllers\RealisasiController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) return redirect()->route('dashboard');
    return Inertia::render('Welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout')->middleware('auth');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/bank-data', [BankDataController::class, 'index'])->name('bank-data.index');
    Route::post('/bank-data/visi', [BankDataController::class, 'storeVisi'])->name('bank-data.visi.store');
    Route::put('/bank-data/visi/{visi}', [BankDataController::class, 'updateVisi'])->name('bank-data.visi.update');
    Route::delete('/bank-data/visi/{visi}', [BankDataController::class, 'destroyVisi'])->name('bank-data.visi.destroy');
    Route::post('/bank-data/program', [BankDataController::class, 'storeProgram'])->name('bank-data.program.store');
    Route::put('/bank-data/program/{program}', [BankDataController::class, 'updateProgram'])->name('bank-data.program.update');
    Route::delete('/bank-data/program/{program}', [BankDataController::class, 'destroyProgram'])->name('bank-data.program.destroy');
    Route::post('/bank-data/kegiatan', [BankDataController::class, 'storeKegiatan'])->name('bank-data.kegiatan.store');
    Route::put('/bank-data/kegiatan/{kegiatan}', [BankDataController::class, 'updateKegiatan'])->name('bank-data.kegiatan.update');
    Route::delete('/bank-data/kegiatan/{kegiatan}', [BankDataController::class, 'destroyKegiatan'])->name('bank-data.kegiatan.destroy');
    Route::post('/bank-data/sub-kegiatan', [BankDataController::class, 'storeSubKegiatan'])->name('bank-data.sub-kegiatan.store');
    Route::put('/bank-data/sub-kegiatan/{subKegiatan}', [BankDataController::class, 'updateSubKegiatan'])->name('bank-data.sub-kegiatan.update');
    Route::delete('/bank-data/sub-kegiatan/{subKegiatan}', [BankDataController::class, 'destroySubKegiatan'])->name('bank-data.sub-kegiatan.destroy');

    Route::resource('dokumen', DokumenController::class)->only(['index', 'store', 'destroy']);

    Route::get('/realisasi', [RealisasiController::class, 'index'])->name('realisasi.index');
    Route::post('/realisasi', [RealisasiController::class, 'store'])->name('realisasi.store');
    Route::put('/realisasi/{realisasi}', [RealisasiController::class, 'update'])->name('realisasi.update');
    Route::delete('/realisasi/{realisasi}', [RealisasiController::class, 'destroy'])->name('realisasi.destroy');

    Route::get('/resume', [ResumeController::class, 'index'])->name('resume.index');

    Route::middleware('role:superadmin')->prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::resource('opd', OpdController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('user', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('kepmen', KepmenController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
