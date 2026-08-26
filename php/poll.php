<?php

/*
 * LiveConnect PHP polling endpoint.
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
        $_GET;
}

$webinarId =
    $data["webinar_id"] ?? null;

$receiverId =
    $data["receiver_id"] ?? null;

$after =
    isset($data["after"])
        ? floatval($data["after"])
        : 0;

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

$signals =
    read_signals();

cleanup_old_signals(
    $signals
);

$result = [];

$latestTimestamp =
    $after;

foreach (
    $signals
    as $signal
) {

    if (
        !isset(
            $signal["webinar_id"]
        )
    ) {
        continue;
    }

    if (
        (string)$signal["webinar_id"]
        !==
        (string)$webinarId
    ) {
        continue;
    }

    $createdAt =
        isset(
            $signal["created_at"]
        )
            ? floatval(
                $signal["created_at"]
            )
            : 0;

    if (
        $createdAt <=
        $after
    ) {
        continue;
    }

    if (
        $receiverId !== null &&
        isset(
            $signal["sender_id"]
        ) &&
        $signal["sender_id"] !== null &&
        (string)$signal["sender_id"]
        ===
        (string)$receiverId
    ) {
        continue;
    }

    $message =
        isset(
            $signal["data"]
        ) &&
        is_array(
            $signal["data"]
        )
            ? $signal["data"]
            : [];

    $message["_signal_id"] =
        $signal["id"];

    $message["_created_at"] =
        $createdAt;

    $message["_sender_id"] =
        $signal["sender_id"] ?? null;

    $result[] =
        $message;

    if (
        $createdAt >
        $latestTimestamp
    ) {
        $latestTimestamp =
            $createdAt;
    }
}

write_signals(
    $signals
);

json_response(
    [
        "success" =>
            true,

        "signals" =>
            $result,

        "timestamp" =>
            $latestTimestamp
    ]
);