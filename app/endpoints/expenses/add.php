<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/api_response.php';

$category = $_POST['category'] ?? 'fuel';
$name = trim($_POST['name'] ?? '');
$amount = (float) ($_POST['amount'] ?? 0);
$currencyId = (int) ($_POST['currency_id'] ?? 0);
$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
$expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
$quantity = $_POST['quantity'] ?? null;
$unit = trim($_POST['unit'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$allowedCategories = ['fuel'];
if (!in_array($category, $allowedCategories, true)) {
    apiError(translate('invalid_input', $i18n));
}

if ($name === '') {
    $name = translate('petrol', $i18n);
}

$parsedDate = DateTime::createFromFormat('!Y-m-d', $expenseDate);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $expenseDate || $amount <= 0 || $currencyId <= 0) {
    apiError(translate('fill_all_fields', $i18n));
}

$currencyStmt = $db->prepare('SELECT id FROM currencies WHERE id = :currencyId AND user_id = :userId');
$currencyStmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
$currencyStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$currencyResult = $currencyStmt->execute();
if (!$currencyResult->fetchArray(SQLITE3_ASSOC)) {
    apiError(translate('invalid_input', $i18n));
}

if ($vehicleId > 0) {
    $vehicleStmt = $db->prepare('SELECT id, name FROM fuel_vehicles WHERE id = :vehicleId AND user_id = :userId');
    $vehicleStmt->bindValue(':vehicleId', $vehicleId, SQLITE3_INTEGER);
    $vehicleStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $vehicleResult = $vehicleStmt->execute();
    $vehicle = $vehicleResult ? $vehicleResult->fetchArray(SQLITE3_ASSOC) : false;

    if (!$vehicle) {
        apiError(translate('invalid_input', $i18n));
    }

    $name = $vehicle['name'];
} else {
    $vehicleId = null;
}

$quantity = $quantity === null || $quantity === '' ? null : (float) $quantity;
$unit = in_array($unit, ['l', 'gal_us'], true) ? $unit : null;
$unitPrice = ($quantity !== null && $quantity > 0) ? $amount / $quantity : null;

$stmt = $db->prepare("INSERT INTO expenses (
    user_id, vehicle_id, category, name, amount, currency_id, expense_date, quantity, unit, unit_price, notes
) VALUES (
    :userId, :vehicleId, :category, :name, :amount, :currencyId, :expenseDate, :quantity, :unit, :unitPrice, :notes
)");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
if ($vehicleId === null) {
    $stmt->bindValue(':vehicleId', null, SQLITE3_NULL);
} else {
    $stmt->bindValue(':vehicleId', $vehicleId, SQLITE3_INTEGER);
}
$stmt->bindValue(':category', $category, SQLITE3_TEXT);
$stmt->bindValue(':name', $name, SQLITE3_TEXT);
$stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
$stmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
$stmt->bindValue(':expenseDate', $expenseDate, SQLITE3_TEXT);
if ($quantity === null) {
    $stmt->bindValue(':quantity', null, SQLITE3_NULL);
} else {
    $stmt->bindValue(':quantity', $quantity, SQLITE3_FLOAT);
}
if ($unit === null) {
    $stmt->bindValue(':unit', null, SQLITE3_NULL);
} else {
    $stmt->bindValue(':unit', $unit, SQLITE3_TEXT);
}
if ($unitPrice === null) {
    $stmt->bindValue(':unitPrice', null, SQLITE3_NULL);
} else {
    $stmt->bindValue(':unitPrice', $unitPrice, SQLITE3_FLOAT);
}
$stmt->bindValue(':notes', $notes, SQLITE3_TEXT);

if ($stmt->execute()) {
    apiSuccess(null, translate('expense_saved', $i18n));
}

apiError(translate('unknown_error', $i18n));

?>
