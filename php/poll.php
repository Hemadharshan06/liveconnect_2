<?php

/*
 * LiveConnect PHP polling endpoint.
 *
 * Supports:
 *
 * 1. Broadcast signals
 * 2. Sender exclusion
 * 3. Targeted moderation signals
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


/*
 * Handle CORS preflight.
 */

if (
    $_SERVER["REQUEST_METHOD"] ===
    "OPTIONS"
) {

    http_response_code(204);

    exit;

}


/*
 * Load signaling configuration.
 */

require_once __DIR__ . "/config.php";


/*
 * Read request data.
 *
 * GET is used by the frontend for polling.
 */

$data =
    get_json_body();


if (
    empty($data)
) {

    $data =
        $_GET;

}


/*
 * Required webinar ID.
 */

$webinarId =
    $data["webinar_id"]
    ?? null;


/*
 * Receiver ID.
 *
 * Host:
 *     host
 *
 * Participant:
 *     participant database ID
 */

$receiverId =
    $data["receiver_id"]
    ?? null;


/*
 * Only return signals newer than
 * this timestamp.
 */

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


/*
 * Read all stored signals.
 */

$signals =
    read_signals();


/*
 * Remove expired signals.
 */

cleanup_old_signals(
    $signals
);


$result = [];


/*
 * Keep track of the newest signal
 * timestamp examined.
 */

$latestTimestamp =
    $after;


/*
 * Examine every stored signal.
 */

foreach (
    $signals
    as $signal
) {


    /*
     * Signal must belong to the
     * requested webinar.
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
     * Get signal creation time.
     */

    $createdAt =
        isset(
            $signal["created_at"]
        )
            ? floatval(
                $signal["created_at"]
            )
            : 0;


    /*
     * Ignore signals that the client
     * has already processed.
     */

    if (
        $createdAt <=
        $after
    ) {

        continue;

    }


    /*
     * Extract the actual frontend
     * message stored inside the signal.
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
     * -------------------------------------------------
     * SENDER EXCLUSION
     * -------------------------------------------------
     *
     * Do not send a client's own
     * signal back to that client.
     *
     * Example:
     *
     * Participant 12 sends chat.
     * Participant 12 should not receive
     * that same signal from polling.
     *
     * The sender already displays their
     * own local message.
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
     * -------------------------------------------------
     * TARGETED MODERATION SIGNAL
     * -------------------------------------------------
     *
     * ONLY moderation commands are
     * treated as directly targeted.
     *
     * At present:
     *
     *     participant_removed
     *
     * has a participant_id telling us
     * exactly which participant should
     * receive the signal.
     *
     * IMPORTANT:
     *
     * We do NOT use the presence of
     * participant_id alone to determine
     * targeting because normal WebRTC
     * and participant signals also contain
     * participant_id.
     */

    if (
        isset(
            $message["type"]
        ) &&
        $message["type"] ===
            "participant_removed"
    ) {


        /*
         * ID of the participant selected
         * by the Host.
         */

        $targetParticipantId =
            $message["participant_id"]
            ?? null;


        /*
         * A Host should never receive
         * another participant's
         * moderation command.
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
         * If there is no valid target,
         * do not deliver the command.
         */

        if (
            $targetParticipantId === null ||
            $targetParticipantId === ""
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
         * Only the selected participant
         * receives participant_removed.
         */

        if (
            (string)$receiverId
            !==
            (string)$targetParticipantId
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
     * -------------------------------------------------
     * ATTACH INTERNAL SIGNAL METADATA
     * -------------------------------------------------
     *
     * These values are useful to the
     * frontend for identifying the signal.
     */

    $message["_signal_id"] =
        $signal["id"];


    $message["_created_at"] =
        $createdAt;


    $message["_sender_id"] =
        $signal["sender_id"]
        ?? null;


    /*
     * Deliver the signal.
     */

    $result[] =
        $message;


    /*
     * Update latest timestamp.
     */

    if (
        $createdAt >
        $latestTimestamp
    ) {

        $latestTimestamp =
            $createdAt;

    }

}


/*
 * Save the cleaned signal collection.
 */

write_signals(
    $signals
);


/*
 * Return all eligible signals.
 */

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