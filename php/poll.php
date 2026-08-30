<?php

/*
 * LiveConnect PHP polling endpoint.
 *
 * Supports:
 *
 * 1. Broadcast signals
 * 2. Sender exclusion
 * 3. Targeted participant signals
 */

$origin =
    $_SERVER["HTTP_ORIGIN"] ?? "";

$allowedOrigins = [
    "http://127.0.0.1:8000",
    "http://localhost:8000",
    "http://localhost",
    "https://liveconnect-2-1.onrender.com"
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
    $data["webinar_id"]
    ?? null;


$receiverId =
    $data["receiver_id"]
    ?? null;


$after =
    isset(
        $data["after"]
    )
        ? floatval(
            $data["after"]
        )
        : 0;


if (
    !$webinarId
) {

    json_response(
        [
            "success" =>
                false,

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

    /*
     * Signal must belong to
     * the requested webinar.
     */

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


    /*
     * Ignore signals already
     * processed by this receiver.
     */

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


    /*
     * Get the actual message data.
     */

    $message =
        isset(
            $signal["data"]
        ) &&
        is_array(
            $signal["data"]
        )
            ? $signal["data"]
            : [];


    /*
     * Sender exclusion.
     *
     * A client should not receive
     * its own signal.
     */

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

        if (
            $createdAt >
            $latestTimestamp
        ) {

            $latestTimestamp =
                $createdAt;

        }

        continue;

    }


    /*
     * TARGETED SIGNAL
     *
     * If participant_id exists,
     * treat it as a targeted participant
     * signal for moderation commands
     * such as participant_removed.
     *
     * Only the matching participant
     * receives the signal.
     *
     * Host receiver_id is usually "host",
     * so Host does not match a numeric
     * participant_id.
     */

    if (
        isset(
            $message["participant_id"]
        ) &&
        $message["participant_id"] !== null &&
        $message["participant_id"] !== ""
    ) {

        $targetParticipantId =
            (string)$message["participant_id"];


        /*
         * Host polling:
         * receiver_id = "host"
         *
         * Host should not receive a
         * participant-targeted signal.
         */

        if (
            $receiverId === null ||
            strtolower(
                (string)$receiverId
            ) ===
            "host"
        ) {

            if (
                $createdAt >
                $latestTimestamp
            ) {

                $latestTimestamp =
                    $createdAt;

            }

            continue;

        }


        /*
         * Participant polling:
         * only the targeted participant
         * receives the signal.
         */

        if (
            (string)$receiverId
            !==
            $targetParticipantId
        ) {

            if (
                $createdAt >
                $latestTimestamp
            ) {

                $latestTimestamp =
                    $createdAt;

            }

            continue;

        }

    }


    /*
     * Add internal metadata so the
     * frontend can identify the signal.
     */

    $message["_signal_id"] =
        $signal["id"];


    $message["_created_at"] =
        $createdAt;


    $message["_sender_id"] =
        $signal["sender_id"]
        ?? null;


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