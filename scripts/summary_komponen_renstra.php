<?php
try {
    $dbh = new PDO('mysql:host=127.0.0.1;port=3306;dbname=simevlap;charset=utf8mb4','root','');
    $totalStmt = $dbh->query("SELECT COUNT(*) AS total FROM komponen_anggaran WHERE document_type='renstra' AND jenis='program'");
    $total = (int)$totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $countsStmt = $dbh->query("SELECT opd_id, COUNT(*) AS cnt FROM komponen_anggaran WHERE document_type='renstra' AND jenis='program' GROUP BY opd_id ORDER BY opd_id");
    $counts = $countsStmt->fetchAll(PDO::FETCH_ASSOC);

    // get sample 3 per opd
    $samples = [];
    $opdIds = array_map(fn($r)=>$r['opd_id'], $counts);
    foreach ($opdIds as $opd) {
        $stmt = $dbh->prepare("SELECT id, opd_id, kode, nama_komponen FROM komponen_anggaran WHERE document_type='renstra' AND jenis='program' AND opd_id = :opd ORDER BY id LIMIT 3");
        $stmt->execute([':opd' => $opd]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $samples[$opd] = $rows;
    }

    $out = ['total' => $total, 'counts' => $counts, 'samples' => $samples];
    echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
