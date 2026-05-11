<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$subscriptionId = $data["id"];
$query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptionToClone = $result->fetchArray(SQLITE3_ASSOC);
if ($subscriptionToClone === false) {
    apiError(translate("error", $i18n), 404);
}

$query = "INSERT INTO subscriptions (
    name, logo, price, regular_price, currency_id, next_payment, last_payment_date, auto_renew, start_date,
    cycle, frequency, notes, payment_method_id, payer_user_id, category_id, notify, url, inactive,
    notify_days_before, user_id, cancellation_date, replacement_subscription_id, ended_at, completion_notified,
    adjust_to_working_day
) VALUES (
    :name, :logo, :price, :regular_price, :currency_id, :next_payment, :last_payment_date, :auto_renew, :start_date,
    :cycle, :frequency, :notes, :payment_method_id, :payer_user_id, :category_id, :notify, :url, :inactive,
    :notify_days_before, :user_id, :cancellation_date, :replacement_subscription_id, NULL, 0,
    :adjust_to_working_day
)";
$cloneStmt = $db->prepare($query);
$cloneStmt->bindValue(':name', $subscriptionToClone['name'], SQLITE3_TEXT);
$cloneStmt->bindValue(':logo', $subscriptionToClone['logo'], SQLITE3_TEXT);
$cloneStmt->bindValue(':price', $subscriptionToClone['price'], SQLITE3_TEXT);
$cloneStmt->bindValue(':regular_price', $subscriptionToClone['regular_price'], $subscriptionToClone['regular_price'] === null ? SQLITE3_NULL : SQLITE3_FLOAT);
$cloneStmt->bindValue(':currency_id', $subscriptionToClone['currency_id'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':next_payment', $subscriptionToClone['next_payment'], SQLITE3_TEXT);
$cloneStmt->bindValue(':last_payment_date', $subscriptionToClone['last_payment_date'], $subscriptionToClone['last_payment_date'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
$cloneStmt->bindValue(':auto_renew', $subscriptionToClone['auto_renew'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':start_date', $subscriptionToClone['start_date'], SQLITE3_TEXT);
$cloneStmt->bindValue(':cycle', $subscriptionToClone['cycle'], SQLITE3_TEXT);
$cloneStmt->bindValue(':frequency', $subscriptionToClone['frequency'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':notes', $subscriptionToClone['notes'], SQLITE3_TEXT);
$cloneStmt->bindValue(':payment_method_id', $subscriptionToClone['payment_method_id'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':payer_user_id', $subscriptionToClone['payer_user_id'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':category_id', $subscriptionToClone['category_id'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':notify', $subscriptionToClone['notify'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':url', $subscriptionToClone['url'], SQLITE3_TEXT);
$cloneStmt->bindValue(':inactive', $subscriptionToClone['inactive'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':notify_days_before', $subscriptionToClone['notify_days_before'], SQLITE3_INTEGER);
$cloneStmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$cloneStmt->bindValue(':cancellation_date', $subscriptionToClone['cancellation_date'], $subscriptionToClone['cancellation_date'] === null ? SQLITE3_NULL : SQLITE3_TEXT);
$cloneStmt->bindValue(':replacement_subscription_id', $subscriptionToClone['replacement_subscription_id'], $subscriptionToClone['replacement_subscription_id'] === null ? SQLITE3_NULL : SQLITE3_INTEGER);
$cloneStmt->bindValue(':adjust_to_working_day', $subscriptionToClone['adjust_to_working_day'] ?? 0, SQLITE3_INTEGER);

if ($cloneStmt->execute()) {
    apiSuccess(["id" => $db->lastInsertRowID()], translate('success', $i18n));
} else {
    apiError(translate("error", $i18n));
}

$db->close();
?>
