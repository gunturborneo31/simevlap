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
use App\Http\Controllers\VerifikatorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


use Illuminate\Support\Facades\Auth;
use Inertia\Inertia as InertiaRender;
use Illuminate\Http\Request;

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

// Temporary debug route to return Resume JSON payload without auth (local dev only)
Route::get('/__debug/resume-json', function (Request $request) {
    $controller = app(\App\Http\Controllers\ResumeController::class);
    return $controller->index($request);
});
// Temporary debug route: fetch RENJA komponen + indikator for given opd_id/kode/tahun
Route::get('/__debug/renja-indikator', function (Request $request) {
    $opdId = (int) $request->query('opd_id');
    $kode = (string) $request->query('kode');
    $tahun = $request->query('tahun') !== null ? (int) $request->query('tahun') : null;

    $query = DB::table('komponen_anggaran as ka')
        ->leftJoin('indikator_anggaran as ia', 'ia.komponen_anggaran_id', '=', 'ka.id')
        ->select(['ka.*', 'ia.nama_indikator'])
        ->where('ka.opd_id', $opdId)
        ->where('ka.document_type', 'renja')
        ->where(function ($q) use ($kode) {
            $q->where('ka.kode_program', $kode)
              ->orWhere('ka.kode', $kode);
        });

    if ($tahun !== null) {
        $query->where('ka.tahun', $tahun);
    }

    $rows = $query->get();

    return response()->json($rows);
});

// Temporary debug route: search komponen_anggaran by name (substring) and return indicators
Route::get('/__debug/komponen-search', function (Request $request) {
    $q = (string) $request->query('q');
    $opdId = $request->query('opd_id') ? (int) $request->query('opd_id') : null;
    $tahun = $request->query('tahun') ? (int) $request->query('tahun') : null;

    $query = DB::table('komponen_anggaran as ka')
        ->leftJoin('indikator_anggaran as ia', 'ia.komponen_anggaran_id', '=', 'ka.id')
        ->select(['ka.*', 'ia.nama_indikator'])
        ->where('ka.document_type', 'renja')
        ->where('ka.nama_komponen', 'like', "%{$q}%");

    if ($opdId !== null) {
        $query->where('ka.opd_id', $opdId);
    }
    if ($tahun !== null) {
        $query->where('ka.tahun', $tahun);
    }

    $rows = $query->get();

    return response()->json($rows);
});

// Temporary debug route: search indikator text and return komponen + indikator rows
Route::get('/__debug/indikator-search', function (Request $request) {
    $q = (string) $request->query('q');
    $opdId = $request->query('opd_id') ? (int) $request->query('opd_id') : null;
    $tahun = $request->query('tahun') ? (int) $request->query('tahun') : null;

    $query = DB::table('indikator_anggaran as ia')
        ->leftJoin('komponen_anggaran as ka', 'ka.id', '=', 'ia.komponen_anggaran_id')
        ->select(['ia.*', 'ka.kode', 'ka.nama_komponen', 'ka.opd_id', 'ka.tahun'])
        ->where('ia.nama_indikator', 'like', "%{$q}%");

    if ($opdId !== null) {
        $query->where('ka.opd_id', $opdId);
    }
    if ($tahun !== null) {
        $query->where('ka.tahun', $tahun);
    }

    $rows = $query->get();

    return response()->json($rows);
});

// Temporary debug route: return program-aksi rows + parents without auth (inspect payload)
Route::get('/__debug/relasi/program-aksi', [DataDasarController::class, 'debugRelasiProgramAksi']);
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout')->middleware('auth');


Route::middleware(['auth'])->group(function () {
        // Toggle prioritas program
        Route::post('/data-dasar/program/{program}/prioritas', [DataDasarController::class, 'togglePrioritas'])
            ->middleware('role:superadmin|admin|verifikator')
            ->name('data-dasar.program.toggle-prioritas');
        // Daftar program prioritas per OPD
        Route::get('/data-dasar/program-prioritas', [DataDasarController::class, 'programPrioritas'])
            ->middleware('role:superadmin|admin|verifikator')
            ->name('data-dasar.program-prioritas.index');
    // Dashboard klasik setelah login
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:superadmin|admin|verifikator|opd')
        ->name('dashboard');

    Route::middleware('role:opd')->prefix('dashboard/dokumen')->name('dashboard.dokumen.')->group(function () {
        Route::post('/', [DashboardController::class, 'storeDokumen'])->name('store');
        Route::delete('/{dokumen}', [DashboardController::class, 'destroyDokumen'])->name('destroy');
    });

    Route::get('dashboard/dokumen/{dokumen}/view', [DashboardController::class, 'viewDokumen'])
        ->middleware('role:superadmin|admin|opd')
        ->name('dashboard.dokumen.view');

    Route::middleware('role:superadmin|admin|verifikator')->group(function () {
        Route::get('/data-dasar', [DataDasarController::class, 'index'])->name('data-dasar.index');
        Route::get('/data-dasar/bank-data', [DataDasarController::class, 'menu'])->name('data-dasar.bank-data');
        Route::get('/data-dasar/ikk-unmapped', [DataDasarController::class, 'ikkUnmapped'])->name('data-dasar.ikk-unmapped.index');
        Route::put('/data-dasar/ikk-unmapped/{indikator}/assign-opd', [DataDasarController::class, 'assignIkkOpd'])->name('data-dasar.ikk-unmapped.assign-opd');
        Route::get('/data-dasar/bank-data/{level}', [DataDasarController::class, 'level'])->name('data-dasar.bank-data.level');
        Route::post('/data-dasar/bank-data/{level}', [DataDasarController::class, 'storeLevel'])->name('data-dasar.bank-data.level.store');
        Route::put('/data-dasar/bank-data/{level}/{id}', [DataDasarController::class, 'updateLevel'])->name('data-dasar.bank-data.level.update');
        Route::delete('/data-dasar/bank-data/{level}/{id}', [DataDasarController::class, 'destroyLevel'])->name('data-dasar.bank-data.level.destroy');

        Route::get('/data-dasar/relasi', [DataDasarController::class, 'relasiMenu'])->name('data-dasar.relasi');
        Route::get('/data-dasar/relasi/ringkasan', [DataDasarController::class, 'relasiRingkasan'])->name('data-dasar.relasi.ringkasan');
        Route::get('/data-dasar/relasi/{level}', [DataDasarController::class, 'relasiLevel'])->name('data-dasar.relasi.level');
        Route::put('/data-dasar/relasi/{level}/parent/{parentId}', [DataDasarController::class, 'updateRelasiByParent'])->middleware('role:superadmin')->name('data-dasar.relasi.level.parent.update');
        Route::put('/data-dasar/relasi/{level}/{id}', [DataDasarController::class, 'updateRelasi'])->middleware('role:superadmin')->name('data-dasar.relasi.level.update');
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
    });

    Route::prefix('data-dasar')->group(function () {
        Route::resource('dokumen', DokumenController::class)->only(['index'])->middleware('role:superadmin|admin|verifikator|opd');
        Route::resource('dokumen', DokumenController::class)->only(['store', 'update', 'destroy'])->middleware('role:superadmin|admin|verifikator|opd');
        Route::get('dokumen/{dokumen}/view', [DokumenController::class, 'view'])->middleware('role:superadmin|admin|verifikator|opd')->name('dokumen.view');
        Route::resource('dokumen/iku', IkuController::class)->only(['index'])->middleware('role:superadmin|admin|verifikator')->parameters(['dokumen/iku' => 'iku']);
        Route::resource('dokumen/iku', IkuController::class)->except(['index', 'show'])->middleware('role:superadmin|admin|verifikator')->parameters(['dokumen/iku' => 'iku']);
        Route::resource('urusan', \App\Http\Controllers\UrusanController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('role:superadmin|admin|verifikator');
        Route::resource('bidang-urusan', \App\Http\Controllers\BidangUrusanController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('role:superadmin|admin|verifikator');
    });

    Route::middleware('role:superadmin|admin|verifikator|opd')->group(function () {
        Route::get('/realisasi', [RealisasiController::class, 'index'])->name('realisasi.index');
        Route::post('/realisasi', [RealisasiController::class, 'store'])->name('realisasi.store');
        Route::put('/realisasi/{realisasi}', [RealisasiController::class, 'update'])->name('realisasi.update');
        Route::delete('/realisasi/{realisasi}', [RealisasiController::class, 'destroy'])->name('realisasi.destroy');
    });

    Route::get('/resume', [ResumeController::class, 'index'])->name('resume.index');
    Route::get('/resume/export', [ResumeController::class, 'export'])->name('resume.export');
    Route::get('/resume/dokumen/{dokumen}/view', [ResumeController::class, 'viewDokumen'])->name('resume.dokumen.view');

    Route::middleware('role:verifikator')->prefix('verifikator')->name('verifikator.')->group(function () {
        Route::get('/', [VerifikatorController::class, 'index'])->name('index');
        Route::post('/{realisasi}/verify', [VerifikatorController::class, 'verify'])->name('verify');
        Route::post('/verify-by-reference', [VerifikatorController::class, 'verifyByReference'])->name('verify-by-reference');
    });

    Route::middleware('role:superadmin|admin')->prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::resource('opd', OpdController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('user', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('kepmen', KepmenController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('kepmen/{kepmen}/activate', [KepmenController::class, 'activate'])->name('kepmen.activate');
    });

    // Anggaran DPA
    Route::post('data-dasar/dokumen/dpa/bulk-pagu', [\App\Http\Controllers\KomponenAnggaranController::class, 'bulkUpdatePagu'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('anggaran.bulk-pagu');
    Route::post('data-dasar/dokumen/dpa/bulk-save', [\App\Http\Controllers\KomponenAnggaranController::class, 'bulkSave'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('anggaran.bulk-save');
    Route::post('data-dasar/dokumen/dpa/attach-master', [\App\Http\Controllers\KomponenAnggaranController::class, 'attachFromMaster'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('anggaran.attach-master');
    Route::resource('data-dasar/dokumen/dpa', \App\Http\Controllers\KomponenAnggaranController::class)->only(['index'])->names('anggaran');
    Route::resource('data-dasar/dokumen/dpa', \App\Http\Controllers\KomponenAnggaranController::class)->except(['index', 'show'])->middleware('role:superadmin|admin|verifikator')->names('anggaran');
    Route::post('data-dasar/dokumen/dpa/{anggaran}/indikator', [\App\Http\Controllers\KomponenAnggaranController::class, 'storeIndikator'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('anggaran.indikator.store');
    Route::put('data-dasar/dokumen/dpa/indikator/{indikator}', [\App\Http\Controllers\KomponenAnggaranController::class, 'updateIndikator'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('anggaran.indikator.update');
    Route::delete('data-dasar/dokumen/dpa/indikator/{indikator}', [\App\Http\Controllers\KomponenAnggaranController::class, 'destroyIndikator'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('anggaran.indikator.destroy');
    Route::post('data-dasar/dokumen/dpa/realisasi-annotation', [\App\Http\Controllers\KomponenAnggaranController::class, 'upsertRealisasiAnnotation'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('anggaran.realisasi-annotation.upsert');
    Route::post('data-dasar/dokumen/dpa/realisasi-evidence', [\App\Http\Controllers\KomponenAnggaranController::class, 'uploadRealisasiEvidence'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('anggaran.realisasi-evidence.upload');
    Route::get('data-dasar/dokumen/dpa/realisasi-evidence/{evidence}/view', [\App\Http\Controllers\KomponenAnggaranController::class, 'viewRealisasiEvidence'])
        ->name('anggaran.realisasi-evidence.view');

    // Rencana Strategis (RENSTRA)
    Route::post('data-dasar/dokumen/renstra/bulk-save', [RenstraController::class, 'bulkSave'])->middleware('role:superadmin|admin|verifikator')->name('renstra.bulk-save');
    Route::post('data-dasar/dokumen/renstra/attach-master', [RenstraController::class, 'attachFromMaster'])->middleware('role:superadmin|admin|verifikator')->name('renstra.attach-master');
    Route::resource('data-dasar/dokumen/renstra', RenstraController::class)->only(['index'])->names('renstra');
    Route::resource('data-dasar/dokumen/renstra', RenstraController::class)->except(['index', 'show'])->middleware('role:superadmin|admin|verifikator')->names('renstra');
    Route::post('data-dasar/dokumen/renstra/{renstra}/indikator', [RenstraController::class, 'storeIndikator'])->middleware('role:superadmin|admin|verifikator')->name('renstra.indikator.store');
    Route::put('data-dasar/dokumen/renstra/indikator/{indikator}', [RenstraController::class, 'updateIndikator'])->middleware('role:superadmin|admin|verifikator')->name('renstra.indikator.update');
    Route::delete('data-dasar/dokumen/renstra/indikator/{indikator}', [RenstraController::class, 'destroyIndikator'])->middleware('role:superadmin|admin|verifikator')->name('renstra.indikator.destroy');

    // Rencana Kerja (RENJA)
    Route::post('data-dasar/dokumen/renja/bulk-pagu', [\App\Http\Controllers\RenjaController::class, 'bulkUpdatePagu'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('renja.bulk-pagu');
    Route::post('data-dasar/dokumen/renja/bulk-save', [\App\Http\Controllers\RenjaController::class, 'bulkSave'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('renja.bulk-save');
    Route::post('data-dasar/dokumen/renja/attach-master', [\App\Http\Controllers\RenjaController::class, 'attachFromMaster'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('renja.attach-master');
    Route::resource('data-dasar/dokumen/renja', \App\Http\Controllers\RenjaController::class)->only(['index'])->names('renja');
    Route::resource('data-dasar/dokumen/renja', \App\Http\Controllers\RenjaController::class)->except(['index', 'show'])->middleware('role:superadmin|admin|verifikator')->names('renja');
    Route::post('data-dasar/dokumen/renja/{anggaran}/indikator', [\App\Http\Controllers\RenjaController::class, 'storeIndikator'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('renja.indikator.store');
    Route::put('data-dasar/dokumen/renja/indikator/{indikator}', [\App\Http\Controllers\RenjaController::class, 'updateIndikator'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('renja.indikator.update');
    Route::delete('data-dasar/dokumen/renja/indikator/{indikator}', [\App\Http\Controllers\RenjaController::class, 'destroyIndikator'])
        ->middleware('role:superadmin|admin|verifikator')
        ->name('renja.indikator.destroy');
});