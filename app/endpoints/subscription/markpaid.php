<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$currentDate = new DateTime();
$currentDateString = $currentDate->format('Y-m-d');

$cycles = array();
$query = "SELECT * FROM cycles";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycleId = $row['id'];
    $cycles[$cycleId] = $row;
}

$subscriptionId = $data["id"] ?? null;
$query = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptionToUpdate = $result->fetchArray(SQLITE3_ASSOC);

if ($subscriptionToUpdate === false) {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$nextPaymentDate = new DateTime($subscriptionToUpdate['next_payment']);
$frequency = $subscriptionToUpdate['frequency'];
$cycle = $cycles[$subscriptionToUpdate['cycle']]['name'];

$intervalSpec = "P";
if ($cycle == 'Daily') {
    $intervalSpec .= "{$frequency}D";
} elseif ($cycle === 'Weekly') {
    $intervalSpec .= "{$frequency}W";
} elseif ($cycle === 'Monthly') {
    $intervalSpec .= "{$frequency}M";
} elseif ($cycle === 'Yearly') {
    $intervalSpec .= "{$frequency}Y";
}

$interval = new DateInterval($intervalSpec);
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
    echo json_encode([
        "success" => true,
        "message" => translate('payment_recorded', $i18n),
        "id" => $subscriptionId
    ]);
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}
