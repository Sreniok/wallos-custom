<?php

function wallosGenerateIcalToken(): string
{
    return bin2hex(random_bytes(32));
}

function wallosEnsureIcalToken(SQLite3 $db, int $userId): string
{
    $stmt = $db->prepare('SELECT ical_token FROM user WHERE id = :userId');
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if (!empty($user['ical_token'])) {
        return $user['ical_token'];
    }

    $token = wallosGenerateIcalToken();
    $stmt = $db->prepare('UPDATE user SET ical_token = :token WHERE id = :userId');
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    return $token;
}

function wallosGetIcalUrls(string $token): array
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $basePath = preg_replace('#/endpoints/calendar$#', '', $scriptDir);
    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    $httpUrl = $scheme . '://' . $host . $basePath . '/endpoints/calendar/ical.php?token=' . rawurlencode($token);

    return [
        'http' => $httpUrl,
        'webcal' => preg_replace('/^https?:/', 'webcal:', $httpUrl),
    ];
}

