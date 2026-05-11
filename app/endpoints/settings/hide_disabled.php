<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$hide_disabled = $data['value'];

// Validate input
if (!isset($hide_disabled) || !is_bool($hide_disabled)) {
    apiError(translate("error", $i18n));
}

$stmt = $db->prepare('UPDATE settings SET hide_disabled = :hide_disabled WHERE user_id = :userId');
$stmt->bindParam(':hide_disabled', $hide_disabled, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate("success", $i18n));
} else {
    apiError(translate("error", $i18n));
}