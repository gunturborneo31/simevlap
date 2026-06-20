<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DataDasarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\IkuController;
use App\Http\Controllers\KepmenController;
use App\Http\Controllers\OpdController;
use App\Http\Controllers\RealisasiController;
use App\Http\Controllers\RenstraController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


use Illuminate\Support\Facades\Auth;
use Inertia\Inertia as InertiaRender;

Route::get('/', function () {
    // Landing page dashboard hijau (Dashboard.vue), baik login maupun tidak
    $user = Auth::user();
    $stats = [
        'total_opd' => \App\Models\Opd::where('is_active', true)->count(),
        'total_program' => \App\Models\Program::count(),
        'total_realisasi' => \App\Models\Realisasi::count(),
    ];
    return InertiaRender::render('Dashboard', [
        'stats' => $stats,
        'user' => $user ? $user->load('opd') : null,
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout')->middleware('auth');


Route::middleware(['auth'])->group(function () {
        // Toggle prioritas program
        Route::post('/data-dasar/program/{program}/prioritas', [DataDasarController::class, 'togglePrioritas'])->name('data-dasar.program.toggle-prioritas');
        // Daftar program prioritas per OPD
        Route::get('/data-dasar/program-prioritas', [DataDasarController::class, 'programPrioritas'])->name('data-dasar.program-prioritas.index');
    // Dashboard klasik setelah login
    Route::get('/dashboard', function () {
        $user = Auth::user();
        $stats = [
            'total_opd' => \App\Models\Opd::where('is_active', true)->count(),
            'total_program' => \App\Models\Program::count(),
            'total_realisasi' => \App\Models\Realisasi::count(),
        ];
        return InertiaRender::render('DashboardClassic', [
            'stats' => $stats,
            'user' => $user->load('opd'),
        ]);
    })->name('dashboard');

    Route::get('/data-dasar', [DataDasarController::class, 'index'])->name('data-dasar.index');
    Route::get('/data-dasar/bank-data', [DataDasarController::class, 'menu'])->name('data-dasar.bank-data');
    Route::get('/data-dasar/bank-data/{level}', [DataDasarController::class, 'level'])->name('data-dasar.bank-data.level');
    Route::post('/data-dasar/bank-data/{level}', [DataDasarController::class, 'storeLevel'])->name('data-dasar.bank-data.level.store');
    Route::put('/data-dasar/bank-data/{level}/{id}', [DataDasarController::class, 'updateLevel'])->name('data-dasar.bank-data.level.update');
    Route::delete('/data-dasar/bank-data/{level}/{id}', [DataDasarController::class, 'destroyLevel'])->name('data-dasar.bank-data.level.destroy');

    Route::get('/data-dasar/relasi', [DataDasarController::class, 'relasiMenu'])->name('data-dasar.relasi');
    Route::get('/data-dasar/relasi/ringkasan', [DataDasarController::class, 'relasiRingkasan'])->name('data-dasar.relasi.ringkasan');
    Route::get('/data-dasar/relasi/{level}', [DataDasarController::class, 'relasiLevel'])->name('data-dasar.relasi.level');
    Route::put('/data-dasar/relasi/{level}/parent/{parentId}', [DataDasarController::class, 'updateRelasiByParent'])->name('data-dasar.relasi.level.parent.update');
    Route::put('/data-dasar/relasi/{level}/{id}', [DataDasarController::class, 'updateRelasi'])->name('data-dasar.relasi.level.update');
    Route::post('/data-dasar/visi', [DataDasarController::class, 'storeVisi'])->name('data-dasar.visi.store');
    Route::put('/data-dasar/visi/{visi}', [DataDasarController::class, 'updateVisi'])->name('data-dasar.visi.update');
    Route::delete('/data-dasar/visi/{visi}', [DataDasarController::class, 'destroyVisi'])->name('data-dasar.visi.destroy');
    Route::post('/data-dasar/program', [DataDasarController::class, 'storeProgram'])->name('data-dasar.program.store');
    Route::put('/data-dasar/program/{program}', [DataDasarController::class, 'updateProgram'])->name('data-dasar.program.update');
    Route::delete('/data-dasar/program/{program}', [DataDasarController::class, 'destroyProgram'])->name('data-dasar.program.destroy');
    Route::post('/data-dasar/kegiatan', [DataDasarController::class, 'storeKegiatan'])->name('data-dasar.kegiatan.store');
    Route::put('/data-dasar/kegiatan/{kegiatan}', [DataDasarController::class, 'updateKegiatan'])->name('data-dasar.kegiatan.update');
    Route::delete('/data-dasar/kegiatan/{kegiatan}', [DataDasarController::class, 'destroyKegiatan'])->name('data-dasar.kegiatan.destroy');
    Route::post('/data-dasar/sub-kegiatan', [DataDasarController::class, 'storeSubKegiatan'])->name('data-dasar.sub-kegiatan.store');
    Route::put('/data-dasar/sub-kegiatan/{subKegiatan}', [DataDasarController::class, 'updateSubKegiatan'])->name('data-dasar.sub-kegiatan.update');
    Route::delete('/data-dasar/sub-kegiatan/{subKegiatan}', [DataDasarController::class, 'destroySubKegiatan'])->name('data-dasar.sub-kegiatan.destroy');

    Route::prefix('data-dasar')->group(function () {
        Route::resource('dokumen', DokumenController::class)->only(['index', 'store', 'destroy']);
        Route::resource('dokumen/iku', IkuController::class)->parameters(['dokumen/iku' => 'iku']);
            Route::resource('urusan', \App\Http\Controllers\UrusanController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('bidang-urusan', \App\Http\Controllers\BidangUrusanController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::get('/realisasi', [RealisasiController::class, 'index'])->name('realisasi.index');
    Route::post('/realisasi', [RealisasiController::class, 'store'])->name('realisasi.store');
    Route::put('/realisasi/{realisasi}', [RealisasiController::class, 'update'])->name('realisasi.update');
    Route::delete('/realisasi/{realisasi}', [RealisasiController::class, 'destroy'])->name('realisasi.destroy');

    Route::get('/resume', [ResumeController::class, 'index'])->name('resume.index');

    Route::middleware('role:superadmin')->prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::resource('opd', OpdController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('user', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('kepmen', KepmenController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('kepmen/{kepmen}/activate', [KepmenController::class, 'activate'])->name('kepmen.activate');
    });

    // Anggaran DPA
    Route::post('data-dasar/dokumen/dpa/bulk-pagu', [\App\Http\Controllers\KomponenAnggaranController::class, 'bulkUpdatePagu'])
        ->name('anggaran.bulk-pagu');
    Route::post('data-dasar/dokumen/dpa/bulk-save', [\App\Http\Controllers\KomponenAnggaranController::class, 'bulkSave'])
        ->name('anggaran.bulk-save');
    Route::post('data-dasar/dokumen/dpa/attach-master', [\App\Http\Controllers\KomponenAnggaranController::class, 'attachFromMaster'])
        ->name('anggaran.attach-master');
    Route::resource('data-dasar/dokumen/dpa', \App\Http\Controllers\KomponenAnggaranController::class)->names('anggaran');
    Route::post('data-dasar/dokumen/dpa/{anggaran}/indikator', [\App\Http\Controllers\KomponenAnggaranController::class, 'storeIndikator'])
        ->name('anggaran.indikator.store');
    Route::put('data-dasar/dokumen/dpa/indikator/{indikator}', [\App\Http\Controllers\KomponenAnggaranController::class, 'updateIndikator'])
        ->name('anggaran.indikator.update');
    Route::delete('data-dasar/dokumen/dpa/indikator/{indikator}', [\App\Http\Controllers\KomponenAnggaranController::class, 'destroyIndikator'])
        ->name('anggaran.indikator.destroy');
    Route::post('data-dasar/dokumen/dpa/realisasi-annotation', [\App\Http\Controllers\KomponenAnggaranController::class, 'upsertRealisasiAnnotation'])
        ->name('anggaran.realisasi-annotation.upsert');
    Route::post('data-dasar/dokumen/dpa/realisasi-evidence', [\App\Http\Controllers\KomponenAnggaranController::class, 'uploadRealisasiEvidence'])
        ->name('anggaran.realisasi-evidence.upload');
    Route::get('data-dasar/dokumen/dpa/realisasi-evidence/{evidence}/view', [\App\Http\Controllers\KomponenAnggaranController::class, 'viewRealisasiEvidence'])
        ->name('anggaran.realisasi-evidence.view');

    // Rencana Strategis (RENSTRA)
    Route::post('data-dasar/dokumen/renstra/bulk-save', [RenstraController::class, 'bulkSave'])->name('renstra.bulk-save');
    Route::post('data-dasar/dokumen/renstra/attach-master', [RenstraController::class, 'attachFromMaster'])->name('renstra.attach-master');
    Route::resource('data-dasar/dokumen/renstra', RenstraController::class)->names('renstra');
    Route::post('data-dasar/dokumen/renstra/{renstra}/indikator', [RenstraController::class, 'storeIndikator'])->name('renstra.indikator.store');
    Route::put('data-dasar/dokumen/renstra/indikator/{indikator}', [RenstraController::class, 'updateIndikator'])->name('renstra.indikator.update');
    Route::delete('data-dasar/dokumen/renstra/indikator/{indikator}', [RenstraController::class, 'destroyIndikator'])->name('renstra.indikator.destroy');

    // Rencana Kerja (RENJA)
    Route::post('data-dasar/dokumen/renja/bulk-pagu', [\App\Http\Controllers\RenjaController::class, 'bulkUpdatePagu'])
        ->name('renja.bulk-pagu');
    Route::post('data-dasar/dokumen/renja/bulk-save', [\App\Http\Controllers\RenjaController::class, 'bulkSave'])
        ->name('renja.bulk-save');
    Route::post('data-dasar/dokumen/renja/attach-master', [\App\Http\Controllers\RenjaController::class, 'attachFromMaster'])
        ->name('renja.attach-master');
    Route::resource('data-dasar/dokumen/renja', \App\Http\Controllers\RenjaController::class)->names('renja');
    Route::post('data-dasar/dokumen/renja/{anggaran}/indikator', [\App\Http\Controllers\RenjaController::class, 'storeIndikator'])
        ->name('renja.indikator.store');
    Route::put('data-dasar/dokumen/renja/indikator/{indikator}', [\App\Http\Controllers\RenjaController::class, 'updateIndikator'])
        ->name('renja.indikator.update');
    Route::delete('data-dasar/dokumen/renja/indikator/{indikator}', [\App\Http\Controllers\RenjaController::class, 'destroyIndikator'])
        ->name('renja.indikator.destroy');
});