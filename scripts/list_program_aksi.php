<?php
try {
    $dbh = new PDO('mysql:host=127.0.0.1;port=3306;dbname=simevlap;charset=utf8mb4','root','');
    $stmt = $dbh->query("SELECT id, kode_rek, nama_rincian, document_type, kepmen_id, is_prioritas, tahun, created_at FROM program WHERE jenis_program='program-aksi' ORDER BY kode_rek, id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
