<?php

/*
 * LiveConnect PHP signaling endpoint.
 *
 * Frontend:
 *   http://127.0.0.1:8000
 *
 * PHP:
 *   http://127.0.0.1:9000
 */

$origin =
    $_SERVER["HTTP_ORIGIN"] ?? "";

$allowedOrigins = [
    "http://127.0.0.1:8000",
    "http://localhost:8000",
    "http://localhost"
];

if (
    in_array(
        $origin,
        $allowedOrigins,
        true
    )
) {
    header(
        "Access-Control-Allow-Origin: " .
        $origin
    );
}

header(
    "Vary: Origin"
);

header(
    "Access-Control-Allow-Methods: GET, POST, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type"
);

header(
    "Content-Type: application/json; charset=utf-8"
);

if (
    $_SERVER["REQUEST_METHOD"] ===
    "OPTIONS"
) {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/config.php";

$data =
    get_json_body();

if (
    empty($data)
) {
    $data =
        $_POST;
}

$webinarId =
    $data["webinar_id"] ?? null;

$messageType =
    $data["type"] ?? null;

$senderId =
    $data["sender_id"] ?? null;

if (
    !$webinarId
) {
    json_response(
        [
            "success" => false,
            "message" =>
                "webinar_id is required."
        ],
        400
    );
}

if (
    !$messageType
) {
    json_response(
        [
            "success" => false,
            "message" =>
                "type is required."
        ],
        400
    );
}

$signals =
    read_signals();

cleanup_old_signals(
    $signals
);

$signalId =
    generate_signal_id();

$createdAt =
    current_timestamp();

$signal = [
    "id" =>
        $signalId,

    "webinar_id" =>
        (string)$webinarId,

    "sender_id" =>
        $senderId !== null
            ? (string)$senderId
            : null,

    "type" =>
        $messageType,

    "created_at" =>
        $createdAt,

    "data" =>
        $data
];

$signals[$signalId] =
    $signal;

write_signals(
    $signals
);

json_response(
    [
        "success" => true,

        "signal_id" =>
            $signalId,

        "message" =>
            "Signal stored.",

        "timestamp" =>
            (float)$createdAt
    ]
);