<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/notification_actions.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$subscriptionId = (int) ($data["id"] ?? 0);
if ($subscriptionId <= 0) {
    apiError(translate("error", $i18n), 400);
}

if (wallosMarkSubscriptionPaid($db, (int) $userId, $subscriptionId)) {
    apiSuccess(["id" => $subscriptionId], translate('payment_recorded', $i18n));
} else {
    apiError(translate("error", $i18n), 404);
}
