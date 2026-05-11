<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/ical_helpers.php';

$token = wallosGenerateIcalToken();
$stmt = $db->prepare('UPDATE user SET ical_token = :token WHERE id = :userId');
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if (!$stmt->execute()) {
    apiError(translate('error', $i18n));
}

$urls = wallosGetIcalUrls($token);
apiSuccess([
    'feed_url' => $urls['http'],
    'webcal_url' => $urls['webcal'],
], translate('ical_token_regenerated', $i18n));

