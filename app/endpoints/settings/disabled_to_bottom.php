<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$disabled_to_bottom = $data['value'];

// Validate input
if (!isset($disabled_to_bottom) || !is_bool($disabled_to_bottom)) {
    apiError(translate("error", $i18n));
}

$stmt = $db->prepare('UPDATE settings SET disabled_to_bottom = :disabled_to_bottom WHERE user_id = :userId');
$stmt->bindParam(':disabled_to_bottom', $disabled_to_bottom, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate("success", $i18n));
} else {
    apiError(translate("error", $i18n));
}
