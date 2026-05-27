<?php
/**
 * One-click "Mark as paid" endpoint reached from notification emails.
 *
 * Auth: signed HMAC token in the query string, per-user secret stored in
 * user.notification_action_secret. No session required — anyone holding
 * the email can click (same trust as the email itself).
 *
 * Query: u=<userId>&s=<subscriptionId>&e=<expiresAtUnix>&t=<hmac>
 */

require_once __DIR__ . '/includes/notification_actions.php';

$db = new SQLite3(__DIR__ . '/db/wallos.db');
$db->busyTimeout(5000);

function wallosRenderMarkPaidPage(string $title, string $bodyHtml, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} — Wallos</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f4f6fa;
            color: #1a1f36;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #1a1f2b; color: #f0f3f8; }
            .card { background: #232938; box-shadow: 0 6px 24px rgba(0,0,0,0.4); }
            .muted { color: #a5b0c2; }
            .btn { background: #5a6cff; color: #fff; }
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 24px rgba(18, 30, 64, 0.08);
            padding: 32px 28px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .icon { font-size: 44px; line-height: 1; margin-bottom: 12px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        .muted { color: #5b6778; font-size: 14px; margin: 0 0 24px; }
        .btn {
            display: inline-block;
            padding: 10px 18px;
            background: #4c5fd5;
            color: #fff;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        {$bodyHtml}
    </div>
</body>
</html>
HTML;
    exit;
}

$userId = isset($_GET['u']) ? (int) $_GET['u'] : 0;
$subscriptionId = isset($_GET['s']) ? (int) $_GET['s'] : 0;
$expiresAt = isset($_GET['e']) ? (int) $_GET['e'] : 0;
$signature = (string) ($_GET['t'] ?? '');

if ($userId <= 0 || $subscriptionId <= 0 || $expiresAt <= 0 || $signature === '') {
    wallosRenderMarkPaidPage('Invalid link', '<div class="icon">⚠️</div><h1>Invalid link</h1><p class="muted">This mark-as-paid link is missing required information.</p>', 400);
}

$secret = wallosGetOrCreateUserActionSecret($db, $userId);
if ($secret === null) {
    wallosRenderMarkPaidPage('Link not recognised', '<div class="icon">⚠️</div><h1>Link not recognised</h1><p class="muted">We could not verify this link.</p>', 404);
}

if (!wallosVerifyActionToken('markpaid', $userId, $subscriptionId, $expiresAt, $signature, $secret)) {
    wallosRenderMarkPaidPage('Link expired', '<div class="icon">⌛</div><h1>This link has expired</h1><p class="muted">Open Wallos and mark the subscription paid from the app.</p>', 410);
}

// Look up subscription name for a friendlier confirmation.
$nameStmt = $db->prepare("SELECT name FROM subscriptions WHERE id = :id AND user_id = :userId");
$nameStmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
$nameStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$subRow = $nameStmt->execute()->fetchArray(SQLITE3_ASSOC);
$subscriptionName = $subRow['name'] ?? 'Subscription';
$safeName = htmlspecialchars($subscriptionName, ENT_QUOTES, 'UTF-8');

if (!wallosMarkSubscriptionPaid($db, $userId, $subscriptionId)) {
    wallosRenderMarkPaidPage('Could not update', '<div class="icon">⚠️</div><h1>Could not update</h1><p class="muted">The subscription may have been deleted.</p>', 404);
}

wallosRenderMarkPaidPage(
    'Marked as paid',
    '<div class="icon">✓</div><h1>' . $safeName . ' marked as paid</h1><p class="muted">The next payment date has been advanced.</p><a class="btn" href="/">Open Wallos</a>'
);
