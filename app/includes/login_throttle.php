<?php
/**
 * Lightweight login-attempt throttle: rejects further attempts from an
 * IP after 5 failures within 15 minutes. State is persisted in the
 * `login_attempts` table (created by migration 000050).
 *
 * Usage:
 *   require 'includes/login_throttle.php';
 *   $ip = wallosClientIp();
 *   if (wallosLoginThrottleBlocked($db, $ip)) { /* show throttled message * / }
 *   // ... attempt login ...
 *   if ($loginFailed) { wallosLoginThrottleRecordFailure($db, $ip, $username); }
 *   if ($loginSucceeded) { wallosLoginThrottleClear($db, $ip); }
 */

if (!function_exists('wallosClientIp')) {
    function wallosClientIp(): string
    {
        // Optional for deployments where a trusted reverse proxy strips and
        // rewrites forwarded headers before the request reaches PHP.
        $trustProxyHeaders = filter_var(getenv('WALLOS_TRUST_PROXY_HEADERS'), FILTER_VALIDATE_BOOLEAN);
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($trustProxyHeaders && $forwardedFor !== '') {
            $first = trim(explode(',', $forwardedFor)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }

        return '0.0.0.0';
    }
}

if (!function_exists('wallosLoginThrottleConfig')) {
    function wallosLoginThrottleConfig(): array
    {
        return [
            'maxAttempts'  => 5,
            'windowSeconds' => 15 * 60,
        ];
    }
}

if (!function_exists('wallosLoginThrottleBlocked')) {
    function wallosLoginThrottleBlocked($db, string $ip): bool
    {
        $cfg = wallosLoginThrottleConfig();
        $stmt = $db->prepare(
            "SELECT attempts, strftime('%s', last_attempt) AS ts
             FROM login_attempts
             WHERE ip = :ip
             ORDER BY last_attempt DESC
             LIMIT 1"
        );
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            return false;
        }
        $age = time() - (int) $row['ts'];
        if ($age > $cfg['windowSeconds']) {
            return false; // window expired
        }
        return ((int) $row['attempts']) >= $cfg['maxAttempts'];
    }
}

if (!function_exists('wallosLoginThrottleRecordFailure')) {
    function wallosLoginThrottleRecordFailure($db, string $ip, string $username = ''): void
    {
        $cfg = wallosLoginThrottleConfig();
        // Find an active row for this ip within the window
        $stmt = $db->prepare(
            "SELECT id, attempts, strftime('%s', last_attempt) AS ts
             FROM login_attempts
             WHERE ip = :ip
             ORDER BY last_attempt DESC
             LIMIT 1"
        );
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row && (time() - (int) $row['ts']) <= $cfg['windowSeconds']) {
            $upd = $db->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt = datetime('now'), username = :username WHERE id = :id");
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->bindValue(':username', $username, SQLITE3_TEXT);
            $upd->execute();
        } else {
            $ins = $db->prepare("INSERT INTO login_attempts (ip, username, attempts, last_attempt) VALUES (:ip, :username, 1, datetime('now'))");
            $ins->bindValue(':ip', $ip, SQLITE3_TEXT);
            $ins->bindValue(':username', $username, SQLITE3_TEXT);
            $ins->execute();
        }
    }
}

if (!function_exists('wallosLoginThrottleClear')) {
    function wallosLoginThrottleClear($db, string $ip): void
    {
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = :ip");
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->execute();
    }
}

if (!function_exists('wallosLoginThrottleRetryAfterSeconds')) {
    function wallosLoginThrottleRetryAfterSeconds($db, string $ip): int
    {
        $cfg = wallosLoginThrottleConfig();
        $stmt = $db->prepare(
            "SELECT strftime('%s', last_attempt) AS ts FROM login_attempts WHERE ip = :ip ORDER BY last_attempt DESC LIMIT 1"
        );
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            return 0;
        }
        $remaining = $cfg['windowSeconds'] - (time() - (int) $row['ts']);
        return max(0, $remaining);
    }
}
