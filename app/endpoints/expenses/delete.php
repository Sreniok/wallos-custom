<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/api_response.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);
$expenseId = (int) ($data['id'] ?? 0);

if ($expenseId <= 0) {
    apiError(translate('invalid_input', $i18n));
}

$stmt = $db->prepare("DELETE FROM expenses WHERE id = :expenseId AND user_id = :userId AND category = 'fuel'");
$stmt->bindValue(':expenseId', $expenseId, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate('fuel_entry_deleted', $i18n));
}

apiError(translate('unknown_error', $i18n));

?>
