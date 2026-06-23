<?php
// Cleanup duplicate program-aksi rows: keep the row with the smallest id per (kode_rek, nama_rincian)
// Usage: php cleanup_program_aksi_duplicates.php
try {
    $dbh = new PDO('mysql:host=127.0.0.1;port=3306;dbname=simevlap;charset=utf8mb4','root','', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // find ids to delete (duplicates)
    $sql = "SELECT id FROM program WHERE jenis_program = 'program-aksi' AND id NOT IN (SELECT MIN(id) FROM program WHERE jenis_program = 'program-aksi' GROUP BY kode_rek, nama_rincian)";
    $stmt = $dbh->query($sql);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!$ids) {
        echo "No duplicates found.\n";
        exit(0);
    }

    // fetch rows to backup
    $in = implode(',', array_map('intval', $ids));
    $rows = $dbh->query("SELECT * FROM program WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);

    $timestamp = (new DateTime())->format('Ymd_His');
    $backupFile = __DIR__ . "/program_aksi_duplicates_backup_{$timestamp}.json";
    file_put_contents($backupFile, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo "Found duplicates: " . count($ids) . " rows. Backup saved to: $backupFile\n";

    // perform deletion inside transaction
    $dbh->beginTransaction();
    $delSql = "DELETE FROM program WHERE id IN ($in)";
    $deleted = $dbh->exec($delSql);
    $dbh->commit();

    $before = $dbh->query("SELECT COUNT(*) FROM program WHERE jenis_program = 'program-aksi'")->fetchColumn();
    echo "Deleted rows: $deleted\n";
    echo "Remaining program-aksi rows: $before\n";

} catch (Exception $e) {
    if (isset($dbh) && $dbh->inTransaction()) $dbh->rollBack();
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
