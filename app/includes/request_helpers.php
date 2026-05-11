<?php

require_once __DIR__ . '/api_response.php';

function requirePostJsonOrForm(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiError('Invalid request method', 405);
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        return $_POST;
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '', true);
    if (!is_array($payload)) {
        apiError('Invalid JSON body', 400);
    }

    return $payload;
}

function currentUserId(): int
{
    return isset($_SESSION['loggedin'], $_SESSION['userId']) && $_SESSION['loggedin'] === true
        ? (int) $_SESSION['userId']
        : 0;
}

function requireAdmin(): void
{
    global $i18n;

    if (currentUserId() !== 1) {
        apiError(translate('error', $i18n), 403);
    }
}

function requestBearerToken(?array $server = null): ?string
{
    $server = $server ?? $_SERVER;
    $authorization = $server['HTTP_AUTHORIZATION']
        ?? $server['REDIRECT_HTTP_AUTHORIZATION']
        ?? null;

    if ($authorization === null && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $authorization = $value;
                break;
            }
        }
    }

    if (!is_string($authorization) || !preg_match('/^\s*Bearer\s+(.+?)\s*$/i', $authorization, $matches)) {
        return null;
    }

    return $matches[1];
}

function requestApiKey(?array $request = null, ?array $server = null): ?string
{
    $bearerToken = requestBearerToken($server);
    if ($bearerToken !== null && $bearerToken !== '') {
        return $bearerToken;
    }

    $request = $request ?? $_REQUEST;
    $apiKey = $request['api_key'] ?? $request['apiKey'] ?? null;
    if (!is_string($apiKey) || trim($apiKey) === '') {
        return null;
    }

    return trim($apiKey);
}

function parseIntegerListParam($value, string $name, ?string &$error = null): ?array
{
    $error = null;
    if ($value === null || $value === '') {
        return null;
    }

    if (is_array($value)) {
        $items = $value;
    } else {
        $items = explode(',', (string) $value);
    }

    $ids = [];
    foreach ($items as $item) {
        $item = trim((string) $item);
        if ($item === '') {
            continue;
        }

        if (!ctype_digit($item) || (int) $item <= 0) {
            $error = "$name must be a comma-separated list of positive integers";
            return null;
        }

        $ids[] = (int) $item;
    }

    return array_values(array_unique($ids));
}

function parseBoolParam($value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function bindBool(SQLite3Stmt $stmt, string $name, $value): void
{
    $stmt->bindValue($name, parseBoolParam($value) ? 1 : 0, SQLITE3_INTEGER);
}

function bindNullableDate(SQLite3Stmt $stmt, string $name, ?string $value): void
{
    $stmt->bindValue($name, $value, $value === null ? SQLITE3_NULL : SQLITE3_TEXT);
}
