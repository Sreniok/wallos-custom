<?php

function apiSuccess($data = null, $msg = null)
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $response = ['success' => true];

    if ($msg !== null) {
        $response['message'] = $msg;
    }

    if (is_array($data)) {
        $response = array_merge($response, $data);
    } elseif ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

function apiError($msg, $http = 400, $details = null)
{
    http_response_code($http);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $response = [
        'success' => false,
        'message' => $msg,
    ];

    if ($details !== null) {
        $response['details'] = $details;
    }

    echo json_encode($response);
    exit;
}

function apiPublicSuccess(array $data = [], string $version = 'v1')
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    echo json_encode(array_merge([
        'success' => true,
        'api_version' => $version,
    ], $data));
    exit;
}

function apiPublicError(string $title, int $http = 400, ?array $details = null, string $version = 'v1')
{
    http_response_code($http);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $response = [
        'success' => false,
        'api_version' => $version,
        'title' => $title,
        'message' => $title,
    ];

    if ($details !== null) {
        $response['details'] = $details;
    }

    echo json_encode($response);
    exit;
}
