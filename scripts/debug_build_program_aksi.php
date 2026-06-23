<?php
try {
    $dbh = new PDO('mysql:host=127.0.0.1;port=3306;dbname=simevlap;charset=utf8mb4','root','');
    // rows: program-aksi
    $stmt = $dbh->query("SELECT id, opd_id, kode_rek, nama_rincian FROM program WHERE jenis_program='program-aksi' ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // parents: komponen_anggaran renstra program
    $stmt = $dbh->query("SELECT id, opd_id, kode, nama_komponen FROM komponen_anggaran WHERE document_type='renstra' AND jenis='program' ORDER BY kode");
    $komponens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each komponen, try to find matching program
    $parents = [];
    $pstmt = $dbh->prepare("SELECT id, nama_rincian FROM program WHERE kode_rek = :kode AND opd_id = :opd_id LIMIT 1");
    foreach ($komponens as $k) {
        $pstmt->execute([':kode' => $k['kode'], ':opd_id' => $k['opd_id']]);
        $pm = $pstmt->fetch(PDO::FETCH_ASSOC);
        $labelName = $pm && $pm['nama_rincian'] ? $pm['nama_rincian'] : $k['nama_komponen'];
        $parents[] = ['id' => (int)$k['id'], 'label' => (($k['kode'] ? $k['kode'] . ' - ' : '') . $labelName), 'opd_id' => $k['opd_id'], 'kode' => $k['kode'], 'uraian' => $labelName];
    }

    echo json_encode(['rows_count' => count($rows), 'parents_count' => count($parents), 'rows_sample' => array_slice($rows,0,8), 'parents_sample' => array_slice($parents,0,8)], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error'=>$e->getMessage()]);
}
