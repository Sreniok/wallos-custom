<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/api_response.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    apiError(translate('session_expired', $i18n), 401);
}

$vehicleId = (int) ($_GET['id'] ?? 0);
if ($vehicleId <= 0) {
    apiError(translate('invalid_input', $i18n));
}

$stmt = $db->prepare("SELECT id, name, make, model, registration, fuel_type, logo_url, payer_user_id FROM fuel_vehicles WHERE id = :vehicleId AND user_id = :userId");
$stmt->bindValue(':vehicleId', $vehicleId, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$vehicle = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

if (!$vehicle) {
    apiError(translate('invalid_input', $i18n));
}

apiSuccess(['vehicle' => $vehicle]);

?>
