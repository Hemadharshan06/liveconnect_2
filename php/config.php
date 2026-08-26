<?php

/*
 * LiveConnect PHP signaling configuration.
 *
 * IMPORTANT:
 * This file must contain absolutely nothing before <?php
 * and must not contain a closing ?> tag.
 *
 * CORS headers are handled by signal.php and poll.php.
 */

$SIGNALS_FILE =
    __DIR__ . "/signals.json";

$MAX_SIGNAL_AGE =
    60 * 60;


function current_timestamp()
{
    return microtime(true);
}


function generate_signal_id()
{
    return
        "signal_" .
        uniqid("", true);
}


function get_json_body()
{
    $raw =
        file_get_contents("php://input");

    if (
        $raw === false ||
        trim($raw) === ""
    ) {
        return [];
    }

    $data =
        json_decode(
            $raw,
            true
        );

    if (
        !is_array($data)
    ) {
        return [];
    }

    return $data;
}


function json_response(
    $data,
    $statusCode = 200
) {
    http_response_code(
        $statusCode
    );

    header(
        "Content-Type: application/json; charset=utf-8"
    );

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


function read_signals()
{
    global $SIGNALS_FILE;

    if (
        !file_exists(
            $SIGNALS_FILE
        )
    ) {
        return [];
    }

    $json =
        file_get_contents(
            $SIGNALS_FILE
        );

    if (
        $json === false ||
        trim($json) === ""
    ) {
        return [];
    }

    $data =
        json_decode(
            $json,
            true
        );

    if (
        !is_array($data)
    ) {
        return [];
    }

    return $data;
}


function write_signals(
    $signals
) {
    global $SIGNALS_FILE;

    file_put_contents(
        $SIGNALS_FILE,
        json_encode(
            $signals,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ),
        LOCK_EX
    );
}


function cleanup_old_signals(
    &$signals
) {
    global $MAX_SIGNAL_AGE;

    $now =
        current_timestamp();

    foreach (
        $signals
        as $id => $signal
    ) {

        if (
            !isset(
                $signal["created_at"]
            )
        ) {
            unset(
                $signals[$id]
            );

            continue;
        }

        $age =
            $now -
            floatval(
                $signal["created_at"]
            );

        if (
            $age >
            $MAX_SIGNAL_AGE
        ) {
            unset(
                $signals[$id]
            );
        }
    }
}