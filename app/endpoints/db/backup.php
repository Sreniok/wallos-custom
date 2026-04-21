<?php
require_once __DIR__ . '/../../includes/connect_endpoint.php';
require_once __DIR__ . '/../../includes/validate_endpoint_admin.php';
require_once __DIR__ . '/../../includes/backup_helpers.php';

$filename = "backup_" . uniqid() . ".zip";
$zipPath = __DIR__ . "/../../.tmp/" . $filename;

try {
    $numberOfFilesAdded = createWallosBackupZip($zipPath, dirname(__DIR__, 2));

    echo json_encode([
        "success" => true,
        "message" => "Zip file created successfully",
        "numFiles" => $numberOfFilesAdded,
        "file" => $filename
    ]);
} catch (Throwable $error) {
    echo json_encode([
        "success" => false,
        "message" => $error->getMessage()
    ]);
}
