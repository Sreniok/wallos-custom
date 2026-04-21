<?php

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

require 'settimezone.php';

$date = new DateTime('now');
echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . "<br />\n";
echo $timezone . "<br />\n";

$currentDate = new DateTime();
$currentDateString = $currentDate->format('Y-m-d');

$cycles = array();
$query = "SELECT * FROM cycles";
$result = $db->query($query);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $cycleId = $row['id'];
    $cycles[$cycleId] = $row;
}

$query = "SELECT id, price, regular_price, start_date, next_payment, last_payment_date, frequency, cycle
    FROM subscriptions
    WHERE auto_renew = 1
      AND inactive = 0
      AND (
        next_payment < :currentDate
        OR (
            regular_price IS NOT NULL
            AND regular_price != ''
            AND start_date IS NOT NULL
            AND start_date < next_payment
            AND start_date < :currentDate
            AND price != regular_price
        )
      )";
$stmt = $db->prepare($query);
$stmt->bindValue(':currentDate', $currentDate->format('Y-m-d'));
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptionId = $row['id'];
    $startDate = !empty($row['start_date']) ? new DateTime($row['start_date']) : null;
    $nextPaymentDate = new DateTime($row['next_payment']);
    $lastPaymentDate = !empty($row['last_payment_date']) ? new DateTime($row['last_payment_date']) : null;
    $frequency = $row['frequency'];
    $cycle = $cycles[$row['cycle']]['name'];

    $shouldSwitchToRegularPriceOnly = $row['regular_price'] !== null
        && $row['regular_price'] !== ''
        && $startDate !== null
        && $startDate < $nextPaymentDate
        && $startDate < $currentDate
        && $nextPaymentDate >= $currentDate
        && $row['price'] != $row['regular_price'];

    if ($shouldSwitchToRegularPriceOnly) {
        $updateStmt = $db->prepare("UPDATE subscriptions SET price = :regularPrice WHERE id = :subscriptionId");
        $updateStmt->bindValue(':regularPrice', $row['regular_price'], SQLITE3_FLOAT);
        $updateStmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
        $updateStmt->execute();
        continue;
    }

    // Calculate the interval to add based on the cycle
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

    $completed = false;

    // Add intervals until the next payment date is in the future
    while ($nextPaymentDate < $currentDate) {
        if ($lastPaymentDate && $nextPaymentDate->format('Y-m-d') >= $lastPaymentDate->format('Y-m-d')) {
            $completed = true;
            break;
        }
        $nextPaymentDate->add($interval);
    }

    if ($completed) {
        $updateQuery = "UPDATE subscriptions
            SET inactive = 1,
                ended_at = :endedAt,
                completion_notified = 0
            WHERE id = :subscriptionId";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindValue(':endedAt', $currentDateString, SQLITE3_TEXT);
        $updateStmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
        $updateStmt->execute();
        continue;
    }

    // Update the subscription's next_payment date and switch to the regular amount after the first payment.
    $updateQuery = "UPDATE subscriptions SET next_payment = :nextPaymentDate";
    if ($row['regular_price'] !== null && $row['regular_price'] !== '') {
        $updateQuery .= ", price = :regularPrice";
    }
    $updateQuery .= " WHERE id = :subscriptionId";

    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':nextPaymentDate', $nextPaymentDate->format('Y-m-d'), SQLITE3_TEXT);
    if ($row['regular_price'] !== null && $row['regular_price'] !== '') {
        $updateStmt->bindValue(':regularPrice', $row['regular_price'], SQLITE3_FLOAT);
    }
    $updateStmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
    $updateStmt->execute();
}

$formattedDate = $currentDate->format('Y-m-d');

$deleteQuery = "DELETE FROM last_update_next_payment_date";
$deleteStmt = $db->prepare($deleteQuery);
$deleteResult = $deleteStmt->execute();

$query = "INSERT INTO last_update_next_payment_date (date) VALUES (:formattedDate)";
$stmt = $db->prepare($query);
$stmt->bindParam(':formattedDate', $currentDateString, SQLITE3_TEXT);
$result = $stmt->execute();

echo "Updated next payment dates";
?>
