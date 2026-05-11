<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';
require_once '../../includes/restore_helpers.php';

if (!isset($_FILES['file'])) {
    apiError("No file uploaded", 400);
}

$db->close();
$restoreResult = wallosRestoreBackup($_FILES['file'], dirname(__DIR__, 2));

if (!$restoreResult['ok']) {
    apiError($restoreResult['error'] ?? "Failed to upload file", 400);
}

apiSuccess(null, translate("success", $i18n));
