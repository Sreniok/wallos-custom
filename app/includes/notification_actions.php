<?php
/**
 * Signed-token helpers for one-click actions embedded in notification emails
 * (e.g. "Mark as paid"). Auth model: anyone with the email can click — same
 * trust as the email itself. Tokens are scoped to a single subscription and
 * expire automatically.
 */

if (!function_exists('wallosGetOrCreateUserActionSecret')) {

    function wallosGetOrCreateUserActionSecret(SQLite3 $db, int $userId): ?string
    {
        $stmt = $db->prepare("SELECT notification_action_secret FROM user WHERE id = :userId");
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row === false) {
            return null;
        }

        $secret = $row['notification_action_secret'] ?? null;
        if (!empty($secret)) {
            return $secret;
        }

        $secret = bin2hex(random_bytes(32));
        $update = $db->prepare("UPDATE user SET notification_action_secret = :secret WHERE id = :userId");
        $update->bindValue(':secret', $secret, SQLITE3_TEXT);
        $update->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $update->execute();
        return $secret;
    }

    function wallosSignActionToken(string $action, int $userId, int $subscriptionId, int $expiresAt, string $secret): string
    {
        $payload = $action . '|' . $userId . '|' . $subscriptionId . '|' . $expiresAt;
        return hash_hmac('sha256', $payload, $secret);
    }

    function wallosVerifyActionToken(string $action, int $userId, int $subscriptionId, int $expiresAt, string $signature, string $secret): bool
    {
        if ($expiresAt < time()) {
            return false;
        }
        $expected = wallosSignActionToken($action, $userId, $subscriptionId, $expiresAt, $secret);
        return hash_equals($expected, $signature);
    }

    function wallosBuildMarkPaidUrl(SQLite3 $db, int $userId, int $subscriptionId, string $serverUrl, int $ttlSeconds = 2592000): ?string
    {
        $secret = wallosGetOrCreateUserActionSecret($db, $userId);
        if ($secret === null) {
            return null;
        }

        $expiresAt = time() + max(60, $ttlSeconds);
        $signature = wallosSignActionToken('markpaid', $userId, $subscriptionId, $expiresAt, $secret);

        $serverUrl = $serverUrl !== '' ? rtrim($serverUrl, '/') : '';
        $base = $serverUrl !== '' ? $serverUrl : '';
        return $base . '/markpaid_email.php?'
            . http_build_query([
                'u' => $userId,
                's' => $subscriptionId,
                'e' => $expiresAt,
                't' => $signature,
            ]);
    }

    /**
     * Advance a subscription's next_payment by one cycle and stamp the
     * last_payment_date. Returns true on success. Shared by both the AJAX
     * markpaid endpoint and the email-driven one so behaviour stays in sync.
     */
    function wallosMarkSubscriptionPaid(SQLite3 $db, int $userId, int $subscriptionId): bool
    {
        require_once __DIR__ . '/subscription_dates.php';

        $stmt = $db->prepare("SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $subscription = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($subscription === false) {
            return false;
        }

        $today = (new DateTime())->format('Y-m-d');
        $nextPaymentDate = new DateTime($subscription['next_payment']);
        $interval = getSubscriptionInterval($subscription['cycle'], $subscription['frequency']);
        $nextPaymentDate->add($interval);

        $sql = "UPDATE subscriptions SET next_payment = :nextPaymentDate, last_payment_date = :lastPaymentDate";
        $regularPrice = $subscription['regular_price'] ?? null;
        if ($regularPrice !== null && $regularPrice !== '') {
            $sql .= ", price = :regularPrice";
        }
        $sql .= " WHERE id = :id";

        $update = $db->prepare($sql);
        $update->bindValue(':nextPaymentDate', $nextPaymentDate->format('Y-m-d'), SQLITE3_TEXT);
        $update->bindValue(':lastPaymentDate', $today, SQLITE3_TEXT);
        if ($regularPrice !== null && $regularPrice !== '') {
            $update->bindValue(':regularPrice', $regularPrice, SQLITE3_FLOAT);
        }
        $update->bindValue(':id', $subscriptionId, SQLITE3_INTEGER);

        return (bool) $update->execute();
    }
}
