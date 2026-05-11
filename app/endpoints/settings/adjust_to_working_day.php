<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$adjust_to_working_day = $data['value'];

if (!isset($adjust_to_working_day) || !is_bool($adjust_to_working_day)) {
    apiError(translate("error", $i18n));
}

$stmt = $db->prepare('UPDATE settings SET adjust_to_working_day = :adjust_to_working_day WHERE user_id = :userId');
$stmt->bindParam(':adjust_to_working_day', $adjust_to_working_day, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate("success", $i18n));
} else {
    apiError(translate("error", $i18n));
}
