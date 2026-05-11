<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$convert_currency = $data['value'];

// Validate input
if (!isset($convert_currency) || !is_bool($convert_currency)) {
    apiError(translate("error", $i18n));
}

$stmt = $db->prepare('UPDATE settings SET convert_currency = :convert_currency WHERE user_id = :userId');
$stmt->bindParam(':convert_currency', $convert_currency, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate("success", $i18n));
} else {
    apiError(translate("error", $i18n));
}