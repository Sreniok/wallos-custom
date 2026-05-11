<?php
require_once __DIR__ . '/api_response.php';

/**
 * Checks if an IP falls in the RFC 6598 Carrier-Grade NAT range (100.64.0.0/10).
 * PHP's FILTER_FLAG_NO_PRIV_RANGE does not cover this range.
 * Used by Tailscale and corporate CGNAT environments.
 */
function is_cgnat_ip($ip) {
    $long = ip2long($ip);
    return $long !== false
        && $long >= ip2long('100.64.0.0')
        && $long <= ip2long('100.127.255.255');
}

/**
 * Validates a webhook URL against SSRF attacks and checks the admin allowlist.
 * If validation fails, it kills the script and outputs a JSON error response.
 * * @param string $url The destination URL to check
 * @param SQLite3 $db The database connection
 * @param array $i18n The translation array
 * @return array Returns an array with ['host', 'ip', 'port'] for cURL hardening
 */
function wallos_ssrf_error($message, $http = 400) {
    if (function_exists('apiError')) {
        apiError($message, $http);
    }

    http_response_code($http);
    die(json_encode([
        "success" => false,
        "message" => $message
    ]));
}

function wallos_validate_url_for_ssrf($url, $db) {
    $parsedUrl = parse_url($url);

    if (!$parsedUrl || !isset($parsedUrl['host'])) {
        return ['ok' => false, 'error' => 'invalid_url'];
    }

    $scheme = strtolower($parsedUrl['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'error' => 'invalid_url'];
    }

    $urlHost = $parsedUrl['host'];
    $port = $parsedUrl['port'] ?? '';
    $ip = gethostbyname($urlHost);

    if ($ip === $urlHost && filter_var($urlHost, FILTER_VALIDATE_IP) === false) {
        return ['ok' => false, 'error' => 'dns'];
    }

    $hostWithPort = $port ? $urlHost . ':' . $port : $urlHost;
    $ipWithPort = $port ? $ip . ':' . $port : $ip;

    $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false || is_cgnat_ip($ip);

    if ($is_private) {
        $stmt = $db->prepare("SELECT local_webhook_notifications_allowlist FROM admin LIMIT 1");
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        $allowlist_str = $row ? $row['local_webhook_notifications_allowlist'] : '';
        $allowlist = array_filter(array_map('trim', explode(',', $allowlist_str)));
        
        if (!in_array($urlHost, $allowlist) &&
            !in_array($ip, $allowlist) &&
            !in_array($hostWithPort, $allowlist) &&
            !in_array($ipWithPort, $allowlist)) {
            return ['ok' => false, 'error' => 'private'];
        }
    }

    $targetPort = $port ?: ($scheme === 'https' ? 443 : 80);

    return [
        'ok'   => true,
        'host' => $urlHost,
        'ip'   => $ip,
        'port' => $targetPort
    ];
}

function validate_webhook_url_for_ssrf($url, $db, $i18n) {
    $result = wallos_validate_url_for_ssrf($url, $db);
    if ($result['ok']) {
        unset($result['ok']);
        return $result;
    }

    $message = translate("error", $i18n);
    if ($result['error'] === 'dns') {
        $message = "Error: Could not resolve the hostname. Please check the URL or your server's DNS.";
    } elseif ($result['error'] === 'private') {
        $message = "Security Block: The target IP/Port is private and not present in the Webhook Allowlist.";
    }

    wallos_ssrf_error($message, 400);
}

/**
 * Non-fatal variant for use in cron jobs (sendnotifications.php).
 * Returns the same ['host', 'ip', 'port'] array on success, or false on failure.
 * Never calls die() — caller should use continue/skip on false.
 * Respects the admin allowlist for private IPs, just like the main function.
 *
 * @param string $url The destination URL to check
 * @param SQLite3 $db The database connection
 * @return array|false
 */
function is_url_safe_for_ssrf($url, $db) {
    $result = wallos_validate_url_for_ssrf($url, $db);
    if (!$result['ok']) return false;

    unset($result['ok']);
    return [
        'host' => $result['host'],
        'ip'   => $result['ip'],
        'port' => $result['port']
    ];
}
