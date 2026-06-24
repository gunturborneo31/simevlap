<?php
// routes/api.php - add temporary debug route

Route::get('/debug-resume-data', function () {
    $selectedYear = 2026;
    $opdId = 20;
    
    // Simulate the exact backend logic
    $renjaRows = \App\Models\KomponenAnggaran::with(['indikator'])
        ->whereRaw('LOWER(document_type) = ?', ['renja'])
        ->where('tahun', $selectedYear)
        ->get();
    
    $renjaMap = [];
    foreach ($renjaRows as $r) {
        $jenis = $r->jenis ?? 'program';
        $kode = $r->kode ?? '';
        $opd_id = $r->opd_id ?? '';
        $renjaMapKey = implode('|', [$jenis, $kode, $opd_id]);
        $renjaMap[$renjaMapKey] = [
            'pagu' => $r->pagu ?? 0,
            'dokumen' => strtoupper((string) ($r->document_type ?? 'RENJA')),
        ];
    }
    
    // Get DPA for program 5.03.01.2.01 (kegiatan)
    $dpaItem = \App\Models\KomponenAnggaran::where('document_type', 'dpa')
        ->where('tahun', $selectedYear)
        ->where('opd_id', $opdId)
        ->where('kode', '5.03.01.2.01')
        ->first();
    
    if (!$dpaItem) return response()->json(['error' => 'DPA not found'], 404);
    
    // Simulate attachment logic
    $dpaLevel = $dpaItem->jenis ?? 'program';
    $dpaJenis = match($dpaLevel) {
        'kegiatan' => 'kegiatan',
        'sub' => 'sub_kegiatan',
        default => 'program',
    };
    
    $rkKey = implode('|', [$dpaJenis, $dpaItem->kode, $opdId]);
    $renja_found = $renjaMap[$rkKey] ?? null;
    
    return response()->json([
        'dpa' => [
            'kode' => $dpaItem->kode,
            'jenis' => $dpaLevel,
        ],
        'dpaJenis' => $dpaJenis,
        'renjaKey' => $rkKey,
        'renjaFound' => $renja_found ? true : false,
        'renjaPagu' => $renja_found['pagu'] ?? 0,
    ]);
});
?>
