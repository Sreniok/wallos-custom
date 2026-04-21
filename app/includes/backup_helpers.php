<?php

function addFolderToBackupZip($sourceDir, $zipArchive, $zipDir = '')
{
    if (!is_dir($sourceDir)) {
        return;
    }

    $sourceDir = rtrim($sourceDir, '/') . '/';
    $handle = opendir($sourceDir);

    if ($handle === false) {
        throw new RuntimeException("Unable to open directory: $sourceDir");
    }

    if ($zipDir !== '') {
        $zipArchive->addEmptyDir($zipDir);
    }

    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourcePath = $sourceDir . $file;
        $archivePath = $zipDir . $file;

        if (is_dir($sourcePath)) {
            addFolderToBackupZip($sourcePath, $zipArchive, $archivePath . '/');
        } else {
            $zipArchive->addFile($sourcePath, $archivePath);
        }
    }

    closedir($handle);
}

function createWallosBackupZip($zipPath, $appRoot = null)
{
    $appRoot = $appRoot ?? dirname(__DIR__);
    $zipDir = dirname($zipPath);

    if (!is_dir($zipDir) && !mkdir($zipDir, 0775, true)) {
        throw new RuntimeException("Unable to create backup directory: $zipDir");
    }

    if (!is_writable($zipDir)) {
        throw new RuntimeException("Backup directory is not writable: $zipDir");
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to open backup zip: $zipPath");
    }

    $dbPath = $appRoot . '/db/wallos.db';
    if (file_exists($dbPath)) {
        $zip->addFile($dbPath, 'wallos.db');
    }

    addFolderToBackupZip($appRoot . '/images/uploads/logos', $zip, 'logos/');

    $numberOfFilesAdded = $zip->numFiles;

    if ($zip->close() === false) {
        throw new RuntimeException("Failed to finalize backup zip: $zipPath");
    }

    return $numberOfFilesAdded;
}

function cleanupOldWallosBackups($backupDir, $retentionDays)
{
    $retentionDays = (int) $retentionDays;
    if ($retentionDays <= 0 || !is_dir($backupDir)) {
        return 0;
    }

    $deleted = 0;
    $cutoff = time() - ($retentionDays * 86400);

    foreach (glob(rtrim($backupDir, '/') . '/Wallos-Backup-*.zip') ?: [] as $backupFile) {
        if (is_file($backupFile) && filemtime($backupFile) < $cutoff) {
            unlink($backupFile);
            $deleted++;
        }
    }

    return $deleted;
}
