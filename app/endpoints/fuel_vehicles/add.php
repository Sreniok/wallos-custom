<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/api_response.php';

$name = trim($_POST['name'] ?? '');
$make = trim($_POST['make'] ?? '');
$model = trim($_POST['model'] ?? '');
$registration = trim($_POST['registration'] ?? '');
$vehicleId = (int) ($_POST['id'] ?? 0);
$payerUserId = (int) ($_POST['payer_user_id'] ?? 0);
$logoUrl = trim($_POST['logo_url'] ?? '');
$fuelType = $_POST['fuel_type'] ?? 'petrol';
$fuelType = in_array($fuelType, ['petrol', 'diesel'], true) ? $fuelType : 'petrol';

if ($name === '') {
    $name = trim($make . ' ' . $model);
}

if ($name === '') {
    apiError(translate('fill_all_fields', $i18n));
}

$payerUserId = $payerUserId > 0 ? $payerUserId : null;
if ($payerUserId !== null) {
    $memberStmt = $db->prepare("SELECT id FROM household WHERE id = :payerUserId AND user_id = :userId");
    $memberStmt->bindValue(':payerUserId', $payerUserId, SQLITE3_INTEGER);
    $memberStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $memberResult = $memberStmt->execute();
    if (!$memberResult->fetchArray(SQLITE3_ASSOC)) {
        apiError(translate('invalid_input', $i18n));
    }
}

if ($vehicleId > 0) {
    $stmt = $db->prepare("UPDATE fuel_vehicles
        SET name = :name,
            make = :make,
            model = :model,
            registration = :registration,
            fuel_type = :fuelType,
            logo_url = :logoUrl,
            payer_user_id = :payerUserId
        WHERE id = :vehicleId AND user_id = :userId");
    $stmt->bindValue(':vehicleId', $vehicleId, SQLITE3_INTEGER);
} else {
    $stmt = $db->prepare("INSERT INTO fuel_vehicles (
        user_id, name, make, model, registration, fuel_type, logo_url, payer_user_id
    ) VALUES (
        :userId, :name, :make, :model, :registration, :fuelType, :logoUrl, :payerUserId
    )");
}

$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindValue(':name', $name, SQLITE3_TEXT);
$stmt->bindValue(':make', $make, SQLITE3_TEXT);
$stmt->bindValue(':model', $model, SQLITE3_TEXT);
$stmt->bindValue(':registration', $registration, SQLITE3_TEXT);
$stmt->bindValue(':fuelType', $fuelType, SQLITE3_TEXT);
$stmt->bindValue(':logoUrl', $logoUrl, SQLITE3_TEXT);
if ($payerUserId === null) {
    $stmt->bindValue(':payerUserId', null, SQLITE3_NULL);
} else {
    $stmt->bindValue(':payerUserId', $payerUserId, SQLITE3_INTEGER);
}

if ($stmt->execute()) {
    apiSuccess(['id' => $vehicleId > 0 ? $vehicleId : $db->lastInsertRowID()], translate('vehicle_saved', $i18n));
}

apiError(translate('unknown_error', $i18n));

?>
