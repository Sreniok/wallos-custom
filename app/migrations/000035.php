<?php
// This migration clears the total_yearly_cost table as the calculation up to this point was incorrect

$migrationName = basename(__FILE__);
$stmt = $db->prepare('SELECT COUNT(*) as count FROM migrations WHERE migration = :migration OR migration LIKE :migrationPath');
$stmt->bindValue(':migration', $migrationName, SQLITE3_TEXT);
$stmt->bindValue(':migrationPath', '%/' . $migrationName, SQLITE3_TEXT);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

if ((int) ($row['count'] ?? 0) === 0) {
    $db->exec('DELETE FROM total_yearly_cost');
}
