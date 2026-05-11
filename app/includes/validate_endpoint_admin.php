<?php
require_once __DIR__ . '/validate_endpoint.php';
// Check that user is an admin
if ($userId !== 1) {
    apiError(translate('error', $i18n), 403);
}
