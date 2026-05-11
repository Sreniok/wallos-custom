<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/subscription_dates.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$currentDate = new DateTime();
$currentDateString = $currentDate->format('Y-m-d');

$subscriptionId = $data["id"] ?? null;
$query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptionToUpdate = $result->fetchArray(SQLITE3_ASSOC);

if ($subscriptionToUpdate === false) {
    apiError(translate("error", $i18n), 404);
}

$nextPaymentDate = new DateTime($subscriptionToUpdate['next_payment']);
$interval = getSubscriptionInterval($subscriptionToUpdate['cycle'], $subscriptionToUpdate['frequency']);
$nextPaymentDate->add($interval);

$updateQuery = "UPDATE subscriptions
    SET next_payment = :nextPaymentDate,
        last_payment_date = :lastPaymentDate";
$regularPrice = $subscriptionToUpdate['regular_price'];
if ($regularPrice !== null && $regularPrice !== '') {
    $updateQuery .= ", price = :regularPrice";
}
$updateQuery .= " WHERE id = :subscriptionId";

$updateStmt = $db->prepare($updateQuery);
$updateStmt->bindValue(':nextPaymentDate', $nextPaymentDate->format('Y-m-d'), SQLITE3_TEXT);
$updateStmt->bindValue(':lastPaymentDate', $currentDateString, SQLITE3_TEXT);
if ($regularPrice !== null && $regularPrice !== '') {
    $updateStmt->bindValue(':regularPrice', $regularPrice, SQLITE3_FLOAT);
}
$updateStmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);

if ($updateStmt->execute()) {
    apiSuccess(["id" => $subscriptionId], translate('payment_recorded', $i18n));
} else {
    apiError(translate("error", $i18n));
}
