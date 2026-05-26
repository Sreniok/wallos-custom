<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/api_response.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);
$vehicleId = (int) ($data['id'] ?? 0);

if ($vehicleId <= 0) {
    apiError(translate('invalid_input', $i18n));
}

$deleteExpensesStmt = $db->prepare("DELETE FROM expenses WHERE vehicle_id = :vehicleId AND user_id = :userId");
$deleteExpensesStmt->bindValue(':vehicleId', $vehicleId, SQLITE3_INTEGER);
$deleteExpensesStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$deleteExpensesStmt->execute();

$deleteStmt = $db->prepare("DELETE FROM fuel_vehicles WHERE id = :vehicleId AND user_id = :userId");
$deleteStmt->bindValue(':vehicleId', $vehicleId, SQLITE3_INTEGER);
$deleteStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($deleteStmt->execute()) {
    apiSuccess(null, translate('vehicle_deleted', $i18n));
}

apiError(translate('unknown_error', $i18n));

?>
