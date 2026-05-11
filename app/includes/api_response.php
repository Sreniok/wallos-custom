<?php

function apiSuccess($data = null, $msg = null)
{
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

