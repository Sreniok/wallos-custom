<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ical_helpers.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);
$enabled = $data['enabled'] ?? null;

if (!is_bool($enabled)) {
    apiError(translate('error', $i18n));
}

$token = wallosEnsureIcalToken($db, (int) $userId);

$stmt = $db->prepare('UPDATE user SET ical_enabled = :enabled WHERE id = :userId');
$stmt->bindValue(':enabled', $enabled ? 1 : 0, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if (!$stmt->execute()) {
    apiError(translate('error', $i18n));
}

$urls = wallosGetIcalUrls($token);
apiSuccess([
    'enabled' => $enabled,
    'feed_url' => $urls['http'],
    'webcal_url' => $urls['webcal'],
], translate('success', $i18n));

