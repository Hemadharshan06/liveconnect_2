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

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" =>
            "POST request required."
    ]);

    exit;
}


/*
 * Participant name.
 */

$name =
    trim(
        $_POST["name"]
        ?? ""
    );


/*
 * Webinar join code.
 */

$joinCode =
    strtoupper(
        trim(
            $_POST["join_code"]
            ?? ""
        )
    );


if ($name === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Name is required."
    ]);

    exit;
}


if ($joinCode === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "Join code is required."
    ]);

    exit;
}


try {

    $connection =
        get_db_connection();


    /*
     * Find the webinar using the
     * participant's join code.
     *
     * The participant does NOT
     * provide webinar_id.
     */

    $stmt =
        $connection->prepare(
            "SELECT
                id,
                title,
                host_name,
                join_code,
                status
             FROM webinars
             WHERE join_code = ?
             LIMIT 1"
        );


    $stmt->bind_param(
        "s",
        $joinCode
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $webinar =
        $result->fetch_assoc();


    $stmt->close();


    if (!$webinar) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" =>
                "Invalid webinar code."
        ]);

        exit;
    }


    /*
     * Do not allow participants to
     * join an ended webinar.
     */

    if (
        ($webinar["status"] ?? "")
        !==
        "live"
    ) {

        echo json_encode([
            "success" => false,
            "message" =>
                "This webinar has ended."
        ]);

        exit;
    }


    $webinarId =
        (int)$webinar["id"];


    /*
     * Create participant.
     */

    $stmt =
        $connection->prepare(
            "INSERT INTO participants
            (
                name,
                webinar_id
            )
            VALUES (?, ?)"
        );


    $stmt->bind_param(
        "si",
        $name,
        $webinarId
    );


    $stmt->execute();


    $participantId =
        $stmt->insert_id;


    $stmt->close();


    /*
     * Get current participant count.
     */

    $stmt =
        $connection->prepare(
            "SELECT COUNT(*) AS participant_count
             FROM participants
             WHERE webinar_id = ?"
        );


    $stmt->bind_param(
        "i",
        $webinarId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $row =
        $result->fetch_assoc();


    $participantCount =
        (int)$row["participant_count"];


    $stmt->close();


    /*
     * Return everything required
     * by participant.html.
     */

    echo json_encode([

        "success" =>
            true,

        "message" =>
            "Successfully joined the webinar!",

        "participant_id" =>
            (int)$participantId,

        "participant_name" =>
            $name,

        "webinar_id" =>
            $webinarId,

        "webinar_title" =>
            $webinar["title"],

        "host_name" =>
            $webinar["host_name"],

        "join_code" =>
            $webinar["join_code"],

        "participant_count" =>
            $participantCount

    ]);

} catch (Throwable $error) {

    http_response_code(500);

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "Failed to join webinar.",

        "error" =>
            $error->getMessage()

    ]);
}