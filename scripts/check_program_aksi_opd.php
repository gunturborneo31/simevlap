<?php
try {
    $dbh = new PDO('mysql:host=127.0.0.1;port=3306;dbname=simevlap;charset=utf8mb4','root','');
    $stmt = $dbh->query("SELECT id, opd_id, kode_rek, nama_rincian, is_prioritas FROM program WHERE jenis_program='program-aksi' ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
