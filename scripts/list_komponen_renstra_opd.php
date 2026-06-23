<?php
$opd = $argv[1] ?? null;
if (!$opd) { echo "Usage: php list_komponen_renstra_opd.php <opd_id>\n"; exit(1); }
try {
    $dbh = new PDO('mysql:host=127.0.0.1;port=3306;dbname=simevlap;charset=utf8mb4','root','');
    $stmt = $dbh->prepare("SELECT id, opd_id, kode, nama_komponen FROM komponen_anggaran WHERE document_type='renstra' AND jenis='program' AND opd_id = :opd ORDER BY id");
    $stmt->execute([':opd' => (int)$opd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
