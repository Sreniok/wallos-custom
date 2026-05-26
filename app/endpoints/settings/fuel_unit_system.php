<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/api_response.php';

$fuelUnitSystem = $_POST['fuel_unit_system'] ?? 'eu';
if (!in_array($fuelUnitSystem, ['eu', 'uk', 'us'], true)) {
    apiError(translate('invalid_input', $i18n));
}

$stmt = $db->prepare('UPDATE settings SET fuel_unit_system = :fuelUnitSystem WHERE user_id = :userId');
$stmt->bindValue(':fuelUnitSystem', $fuelUnitSystem, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate('settings_saved', $i18n));
}

apiError(translate('unknown_error', $i18n));

?>
