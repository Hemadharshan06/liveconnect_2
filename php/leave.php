<?php

require_once __DIR__ . "/config.php";


$data =
    get_json_body();

if (empty($data)) {
    $data = $_POST;
}


$webinarId =
    $data["webinar_id"] ?? null;

$participantId =
    $data["participant_id"] ?? null;

$participantName =
    $data["participant_name"]
    ?? "Participant";


if (!$webinarId) {

    json_response([
        "success" => false,
        "message" => "webinar_id is required."
    ], 400);

}


$signals =
    read_signals();

cleanup_old_signals(
    $signals
);


$signalId =
    generate_signal_id();


$signals[$signalId] = [

    "id" =>
        $signalId,

    "webinar_id" =>
        (string)$webinarId,

    "sender_id" =>
        $participantId !== null
            ? (string)$participantId
            : null,

    "type" =>
        "participant_left",

    "created_at" =>
        current_timestamp(),

    "data" => [

        "type" =>
            "participant_left",

        "participant_id" =>
            $participantId,

        "participant_name" =>
            $participantName

    ]

];


write_signals(
    $signals
);


json_response([

    "success" =>
        true,

    "message" =>
        "Leave event recorded."

]);
