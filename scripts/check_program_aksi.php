<?php
try {
    $dbh = new PDO('mysql:host=127.0.0.1;port=3306;dbname=simevlap;charset=utf8mb4','root','');
    $stmt = $dbh->query("SELECT kode_rek, nama_rincian, jenis_program, COUNT(*) as cnt FROM program WHERE jenis_program='program-aksi' GROUP BY kode_rek, nama_rincian, jenis_program ORDER BY cnt DESC LIMIT 200");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
