<?php
// All requests should be POST requests
// CSRF Token must be included and match the token stored on the session
// User must be logged in

require_once __DIR__ . '/../libs/csrf.php';
require_once __DIR__ . '/api_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError("Invalid request method", 405);
}

$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf)) {
    apiError("Invalid CSRF token", 403);
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    apiError(translate('session_expired', $i18n), 401);
}
