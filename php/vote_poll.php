<?php

require_once __DIR__ . "/db.php";

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

$allowedOrigins = [
    "http://127.0.0.1:8000",
    "http://localhost:8000",
    "http://localhost"
];

if (in_array($origin, $allowedOrigins, true)) {
    header(
        "Access-Control-Allow-Origin: " . $origin
    );
}

header("Vary: Origin");

header(
    "Access-Control-Allow-Methods: POST, OPTIONS"
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

if (
    $_SERVER["REQUEST_METHOD"] !==
    "POST"
) {
    echo json_encode([
        "success" => false,
        "message" =>
            "POST request required."
    ]);

    exit;
}

$pollId =
    $_GET["poll_id"]
    ?? $_POST["poll_id"]
    ?? null;

$optionId =
    $_GET["option_id"]
    ?? $_POST["option_id"]
    ?? null;

$participantId =
    $_GET["participant_id"]
    ?? $_POST["participant_id"]
    ?? null;


if (!$pollId) {
    echo json_encode([
        "success" => false,
        "message" =>
            "poll_id is required."
    ]);

    exit;
}


if (!$optionId) {
    echo json_encode([
        "success" => false,
        "message" =>
            "option_id is required."
    ]);

    exit;
}


if (!$participantId) {
    echo json_encode([
        "success" => false,
        "message" =>
            "participant_id is required."
    ]);

    exit;
}


try {

    $connection =
        get_db_connection();


    /*
     * Verify that the option
     * belongs to this poll.
     */

    $stmt =
        $connection->prepare(
            "SELECT id
             FROM poll_options
             WHERE id = ?
               AND poll_id = ?
             LIMIT 1"
        );


    $stmt->bind_param(
        "ii",
        $optionId,
        $pollId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $option =
        $result->fetch_assoc();


    $stmt->close();


    if (!$option) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid poll option."
        ]);

        exit;
    }


    /*
     * Verify that the participant
     * belongs to the webinar associated
     * with this poll.
     */

    $stmt =
        $connection->prepare(
            "SELECT p.id
             FROM participants p
             INNER JOIN polls po
                 ON po.webinar_id = p.webinar_id
             WHERE p.id = ?
               AND po.id = ?
             LIMIT 1"
        );


    $stmt->bind_param(
        "ii",
        $participantId,
        $pollId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $participant =
        $result->fetch_assoc();


    $stmt->close();


    if (!$participant) {

        echo json_encode([
            "success" => false,
            "message" =>
                "Participant does not belong to this webinar."
        ]);

        exit;
    }


    /*
     * Check whether this participant
     * has already voted in this poll.
     */

    $stmt =
        $connection->prepare(
            "SELECT id
             FROM poll_votes
             WHERE poll_id = ?
               AND participant_id = ?
             LIMIT 1"
        );


    $stmt->bind_param(
        "ii",
        $pollId,
        $participantId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $existingVote =
        $result->fetch_assoc();


    $stmt->close();


    if ($existingVote) {

        echo json_encode([
            "success" => false,
            "message" =>
                "You have already voted in this poll."
        ]);

        exit;
    }


    /*
     * Record the vote.
     */

    $stmt =
        $connection->prepare(
            "INSERT INTO poll_votes
            (
                poll_id,
                option_id,
                participant_id
            )
            VALUES (?, ?, ?)"
        );


    $stmt->bind_param(
        "iii",
        $pollId,
        $optionId,
        $participantId
    );


    $stmt->execute();


    $voteId =
        $stmt->insert_id;


    $stmt->close();


    /*
     * Get the current vote count
     * for the selected option.
     */

    $stmt =
        $connection->prepare(
            "SELECT COUNT(*) AS vote_count
             FROM poll_votes
             WHERE poll_id = ?
               AND option_id = ?"
        );


    $stmt->bind_param(
        "ii",
        $pollId,
        $optionId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $row =
        $result->fetch_assoc();


    $stmt->close();


    $voteCount =
        (int)$row["vote_count"];


    echo json_encode([

        "success" => true,

        "message" =>
            "Vote recorded successfully!",

        "vote_id" =>
            (int)$voteId,

        "poll_id" =>
            (int)$pollId,

        "option_id" =>
            (int)$optionId,

        "participant_id" =>
            (int)$participantId,

        "vote_count" =>
            $voteCount

    ]);


} catch (Throwable $error) {

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" =>
            "Database operation failed.",

        "error" =>
            $error->getMessage()

    ]);
}
