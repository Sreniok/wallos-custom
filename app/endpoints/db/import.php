<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/api_response.php';
require_once '../../includes/restore_helpers.php';

$result = $db->query("SELECT COUNT(*) as count FROM user");
$row = $result->fetchArray(SQLITE3_NUM);
if ($row[0] > 0) {
    apiError("Denied", 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError("Invalid request method", 405);
}

if (!isset($_FILES['file'])) {
    apiError("No file uploaded", 400);
}

$db->close();
$restoreResult = wallosRestoreBackup($_FILES['file'], dirname(__DIR__, 2));

if (!$restoreResult['ok']) {
    apiError($restoreResult['error'] ?? "Failed to upload file", 400);
}

apiSuccess(null, translate("success", $i18n));
