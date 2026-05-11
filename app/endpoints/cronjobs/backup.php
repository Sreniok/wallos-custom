<?php
require_once __DIR__ . '/../../includes/backup_helpers.php';

$backupDir = getenv('WALLOS_BACKUP_PATH') ?: '/var/www/backups';
$retentionDays = getenv('WALLOS_BACKUP_RETENTION_DAYS') ?: 30;
$timestamp = (new DateTime())->format('Ymd-His');
$zipPath = rtrim($backupDir, '/') . "/Wallos-Backup-$timestamp.zip";

try {
    $numberOfFilesAdded = createWallosBackupZip($zipPath, dirname(__DIR__, 2));
    $deletedOldBackups = cleanupOldWallosBackups($backupDir, $retentionDays);

    echo "Backup created: $zipPath\n";
    echo "Files added: $numberOfFilesAdded\n";
    echo "Old backups deleted: $deletedOldBackups\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Backup failed: " . $error->getMessage() . "\n");
    exit(1);
}
