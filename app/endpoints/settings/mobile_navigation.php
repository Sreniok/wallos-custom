<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$mobile_nav = $data['value'];

// Validate input
if (!isset($mobile_nav) || !is_bool($mobile_nav)) {
    apiError(translate("error", $i18n));
}

$stmt = $db->prepare('UPDATE settings SET mobile_nav = :mobile_nav WHERE user_id = :userId');
$stmt->bindParam(':mobile_nav', $mobile_nav, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate("success", $i18n));
} else {
    apiError(translate("error", $i18n));
}