<?php
require_once __DIR__ . '/../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../includes/validate_endpoint_admin.php';

$filename = $_POST['file'] ?? '';
if (!preg_match('/^backup_[a-f0-9]+\.zip$/', $filename)) {
    apiError('Invalid backup filename', 400);
}

$tmpDir = realpath(__DIR__ . '/../../.tmp');
if ($tmpDir === false) {
    apiError('Temporary backup directory is not available', 500);
}

$filePath = realpath($tmpDir . '/' . $filename);
if ($filePath === false || strncmp($filePath, $tmpDir . DIRECTORY_SEPARATOR, strlen($tmpDir) + 1) !== 0 || !is_file($filePath)) {
    apiError('Backup file not found', 404);
}

$downloadName = 'Wallos-Backup-' . (new DateTime())->format('Ymd-His') . '.zip';

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

readfile($filePath);
unlink($filePath);
exit;
