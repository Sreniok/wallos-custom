<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);
$designTheme = $data['design'] ?? '';

$allowed = ['', 'glass', 'minimal', 'neo', 'vibrant', 'modern'];
if (!in_array($designTheme, $allowed, true)) {
    apiError(translate("error", $i18n));
}

$stmt = $db->prepare('UPDATE settings SET design_theme = :design_theme WHERE user_id = :userId');
$stmt->bindValue(':design_theme', $designTheme, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate("success", $i18n));
} else {
    apiError(translate("error", $i18n));
}
