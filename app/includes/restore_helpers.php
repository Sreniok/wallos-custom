<?php
require_once __DIR__ . '/safe_zip.php';

if (!defined('WALLOS_RESTORE_MAX_ZIP_BYTES')) {
    define('WALLOS_RESTORE_MAX_ZIP_BYTES', 128 * 1024 * 1024);
}
if (!defined('WALLOS_RESTORE_MAX_FILES')) {
    define('WALLOS_RESTORE_MAX_FILES', 2000);
}
if (!defined('WALLOS_RESTORE_MAX_TOTAL_BYTES')) {
    define('WALLOS_RESTORE_MAX_TOTAL_BYTES', 256 * 1024 * 1024);
}

function wallosRemoveTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }

    rmdir($path);
}

function wallosCleanupRestoreWorkspace(string $tmpDir): void
{
    wallosRemoveTree($tmpDir . '/restore');
    if (file_exists($tmpDir . '/restore.zip')) {
        unlink($tmpDir . '/restore.zip');
    }
}

function wallosValidateSqliteDatabase(string $dbPath): array
{
    if (!is_file($dbPath) || filesize($dbPath) === 0) {
        return ['ok' => false, 'error' => 'wallos.db does not exist in the backup file'];
    }

    try {
        $restoreDb = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $restoreDb->busyTimeout(5000);
        $integrity = @$restoreDb->querySingle('PRAGMA integrity_check');
        $hasUserTable = @$restoreDb->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'user'");
        $restoreDb->close();
    } catch (Throwable $error) {
        return ['ok' => false, 'error' => 'Uploaded wallos.db is not a valid SQLite database'];
    }

    if ($integrity !== 'ok' || (int) $hasUserTable !== 1) {
        return ['ok' => false, 'error' => 'Uploaded wallos.db failed validation'];
    }

    return ['ok' => true];
}

function wallosCopyRestoredLogos(string $restoreDir, string $logosDir): array
{
    $restoreLogosDir = $restoreDir . '/logos';
    if (!is_dir($restoreLogosDir)) {
        return ['ok' => true];
    }

    wallosRemoveTree($logosDir);
    if (!mkdir($logosDir, 0755, true) && !is_dir($logosDir)) {
        return ['ok' => false, 'error' => 'Unable to recreate logo directory'];
    }

    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($restoreLogosDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $extension = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $relativePath = ltrim(substr($file->getPathname(), strlen($restoreDir)), DIRECTORY_SEPARATOR);
        $destination = dirname($logosDir) . DIRECTORY_SEPARATOR . $relativePath;
        $destinationDir = dirname($destination);

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            return ['ok' => false, 'error' => 'Unable to create logo restore directory'];
        }

        if (!copy($file->getPathname(), $destination)) {
            return ['ok' => false, 'error' => 'Unable to copy restored logo'];
        }
    }

    return ['ok' => true];
}

function wallosRestoreBackup(array $uploadedFile, string $appRoot): array
{
    $tmpDir = $appRoot . '/.tmp';
    $restoreDir = $tmpDir . '/restore';
    $restoreZip = $tmpDir . '/restore.zip';
    $dbPath = getenv('WALLOS_DATABASE_FILE') ?: $appRoot . '/db/wallos.db';
    $logosDir = $appRoot . '/images/uploads/logos';
    $rollbackPath = $tmpDir . '/wallos.db.rollback.' . date('YmdHis') . '.' . bin2hex(random_bytes(4));

    if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Failed to upload file'];
    }

    if (($uploadedFile['size'] ?? 0) > WALLOS_RESTORE_MAX_ZIP_BYTES) {
        return ['ok' => false, 'error' => 'Backup file is too large'];
    }

    if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
        return ['ok' => false, 'error' => 'Unable to create restore workspace'];
    }

    wallosCleanupRestoreWorkspace($tmpDir);

    if (!move_uploaded_file($uploadedFile['tmp_name'], $restoreZip)) {
        if (PHP_SAPI !== 'cli' || !rename($uploadedFile['tmp_name'], $restoreZip)) {
            return ['ok' => false, 'error' => 'Failed to upload file'];
        }
    }

    $extractResult = wallosSafeZipExtract($restoreZip, $restoreDir, [
        'max_files' => WALLOS_RESTORE_MAX_FILES,
        'max_total_bytes' => WALLOS_RESTORE_MAX_TOTAL_BYTES,
        'max_entry_bytes' => WALLOS_RESTORE_MAX_TOTAL_BYTES,
    ]);
    if (!$extractResult['ok']) {
        wallosCleanupRestoreWorkspace($tmpDir);
        return ['ok' => false, 'error' => 'Failed to extract the uploaded file: ' . ($extractResult['error'] ?? 'unknown error')];
    }

    $restoredDbPath = $restoreDir . '/wallos.db';
    $validation = wallosValidateSqliteDatabase($restoredDbPath);
    if (!$validation['ok']) {
        wallosCleanupRestoreWorkspace($tmpDir);
        return $validation;
    }

    if (!is_dir(dirname($dbPath)) && !mkdir(dirname($dbPath), 0755, true) && !is_dir(dirname($dbPath))) {
        wallosCleanupRestoreWorkspace($tmpDir);
        return ['ok' => false, 'error' => 'Unable to create database directory'];
    }

    if (file_exists($dbPath) && !copy($dbPath, $rollbackPath)) {
        wallosCleanupRestoreWorkspace($tmpDir);
        return ['ok' => false, 'error' => 'Unable to create database rollback copy'];
    }

    if (!rename($restoredDbPath, $dbPath)) {
        if (file_exists($rollbackPath)) {
            @unlink($rollbackPath);
        }
        wallosCleanupRestoreWorkspace($tmpDir);
        return ['ok' => false, 'error' => 'Unable to replace database'];
    }

    $logosResult = wallosCopyRestoredLogos($restoreDir, $logosDir);
    if (!$logosResult['ok']) {
        if (file_exists($rollbackPath)) {
            @rename($rollbackPath, $dbPath);
        }
        wallosCleanupRestoreWorkspace($tmpDir);
        return $logosResult;
    }

    if (file_exists($rollbackPath)) {
        unlink($rollbackPath);
    }
    wallosCleanupRestoreWorkspace($tmpDir);

    return ['ok' => true];
}
