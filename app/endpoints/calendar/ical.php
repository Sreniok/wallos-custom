<?php

require_once __DIR__ . '/../../includes/subscription_dates.php';
require_once __DIR__ . '/../../includes/formatting_helpers.php';

$db = new SQLite3(__DIR__ . '/../../db/wallos.db');
$db->busyTimeout(5000);

function wallosIcalFail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function wallosIcalEscape($value): string
{
    $value = (string) $value;
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(';', '\;', $value);
    $value = str_replace(',', '\,', $value);
    $value = str_replace(["\r\n", "\n", "\r"], '\n', $value);
    return $value;
}

function wallosIcalDate($date): string
{
    return (new DateTimeImmutable($date))->format('Ymd');
}

function wallosIcalRrule($cycle, $frequency): ?string
{
    $frequency = max(1, (int) $frequency);
    switch ((int) $cycle) {
        case 1:
            $freq = 'DAILY';
            break;
        case 2:
            $freq = 'WEEKLY';
            break;
        case 3:
            $freq = 'MONTHLY';
            break;
        case 4:
            $freq = 'YEARLY';
            break;
        default:
            return null;
    }

    return "FREQ={$freq};INTERVAL={$frequency}";
}

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    wallosIcalFail(400, 'Missing calendar token.');
}

$stmt = $db->prepare('SELECT id, username, main_currency, ical_enabled FROM user WHERE ical_token = :token LIMIT 1');
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    wallosIcalFail(404, 'Calendar feed not found.');
}

if (empty($user['ical_enabled'])) {
    wallosIcalFail(403, 'Calendar feed is disabled.');
}

$userId = (int) $user['id'];

$settingsStmt = $db->prepare('SELECT adjust_to_working_day FROM settings WHERE user_id = :userId');
$settingsStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$settingsResult = $settingsStmt->execute();
$settings = $settingsResult->fetchArray(SQLITE3_ASSOC) ?: [];
$adjustToWorkingDay = !empty($settings['adjust_to_working_day']);

$stmt = $db->prepare("
    SELECT
        s.*,
        c.code AS currency_code,
        c.symbol AS currency_symbol,
        pm.name AS payment_method_name,
        h.name AS payer_name,
        cat.name AS category_name
    FROM subscriptions s
    LEFT JOIN currencies c ON c.id = s.currency_id
    LEFT JOIN payment_methods pm ON pm.id = s.payment_method_id AND pm.user_id = s.user_id
    LEFT JOIN household h ON h.id = s.payer_user_id AND h.user_id = s.user_id
    LEFT JOIN categories cat ON cat.id = s.category_id AND cat.user_id = s.user_id
    WHERE s.user_id = :userId
      AND s.inactive = 0
      AND s.next_payment IS NOT NULL
      AND s.next_payment != ''
    ORDER BY s.next_payment ASC
");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$host = $_SERVER['HTTP_HOST'] ?? 'wallos';
$dtstamp = gmdate('Ymd\THis\Z');
$calendarName = 'Wallos - ' . ($user['username'] ?: 'Subscriptions');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Wallos//Subscription Calendar//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'NAME:' . wallosIcalEscape($calendarName),
    'X-WR-CALNAME:' . wallosIcalEscape($calendarName),
    'X-WR-TIMEZONE:UTC',
];

while ($subscription = $result->fetchArray(SQLITE3_ASSOC)) {
    $paymentDate = getAdjustedPaymentDate($subscription, null, $adjustToWorkingDay);
    if ($paymentDate === null) {
        continue;
    }

    try {
        $start = new DateTimeImmutable($paymentDate);
    } catch (Exception $e) {
        continue;
    }

    $end = $start->modify('+1 day');
    $currency = [
        'code' => $subscription['currency_code'] ?? '',
        'symbol' => $subscription['currency_symbol'] ?? '',
    ];
    $price = !empty($currency['code'])
        ? formatPrice($subscription['price'], $currency['code'], [$currency])
        : number_format((float) $subscription['price'], 2);
    $renewal = !empty($subscription['auto_renew']) ? 'Automatic renewal' : 'Manual renewal';
    $descriptionParts = [
        'Price: ' . $price,
        'Cycle: ' . ltrim(wallosCycleSuffix($subscription['cycle'] ?? 0, $subscription['frequency'] ?? 1), '/'),
        'Renewal: ' . $renewal,
    ];

    if (!empty($subscription['payment_method_name'])) {
        $descriptionParts[] = 'Payment method: ' . $subscription['payment_method_name'];
    }
    if (!empty($subscription['payer_name'])) {
        $descriptionParts[] = 'Payer: ' . $subscription['payer_name'];
    }
    if (!empty($subscription['category_name'])) {
        $descriptionParts[] = 'Category: ' . $subscription['category_name'];
    }
    if (!empty($subscription['notes'])) {
        $descriptionParts[] = 'Notes: ' . $subscription['notes'];
    }
    if (!empty($subscription['url'])) {
        $descriptionParts[] = 'URL: ' . $subscription['url'];
    }

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . wallosIcalEscape('wallos-subscription-' . $subscription['id'] . '-user-' . $userId . '@' . $host);
    $lines[] = 'DTSTAMP:' . $dtstamp;
    $lines[] = 'SUMMARY:' . wallosIcalEscape($subscription['name']);
    $lines[] = 'DESCRIPTION:' . wallosIcalEscape(implode("\n", $descriptionParts));
    $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
    $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
    $rrule = wallosIcalRrule($subscription['cycle'] ?? 0, $subscription['frequency'] ?? 1);
    if ($rrule !== null) {
        $lines[] = 'RRULE:' . $rrule;
    }
    if (!empty($subscription['url'])) {
        $lines[] = 'URL:' . wallosIcalEscape($subscription['url']);
    }
    $lines[] = 'STATUS:CONFIRMED';
    $lines[] = 'TRANSP:TRANSPARENT';
    $lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="wallos-subscriptions.ics"');
header('Cache-Control: private, max-age=300');
echo implode("\r\n", $lines) . "\r\n";

