<?php

require_once __DIR__ . "/db.php";

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

$allowedOrigins = [
    "http://127.0.0.1:8000",
    "http://localhost:8000",
    "http://localhost"
];

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: " . $origin);
}

header("Vary: Origin");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode([
        "success" => false,
        "message" => "GET request required."
    ]);
    exit;
}

$webinarId = $_GET["webinar_id"] ?? null;

if (!$webinarId || !is_numeric($webinarId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "webinar_id is required."
    ]);
    exit;
}

$webinarId = (int)$webinarId;

try {

    $connection = get_db_connection();

    $stmt = $connection->prepare(
        "SELECT id, title, host_name, join_code, status
         FROM webinars
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $webinarId);
    $stmt->execute();

    $webinar = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$webinar) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Webinar not found."
        ]);
        exit;
    }

    $stmt = $connection->prepare(
        "SELECT COUNT(*) AS total
         FROM participants
         WHERE webinar_id = ?"
    );

    $stmt->bind_param("i", $webinarId);
    $stmt->execute();

    $participants =
        (int)$stmt->get_result()->fetch_assoc()["total"];

    $stmt->close();

    /*
     * There is currently no attendance-history table,
     * so the participant total is the available value.
     */
    $peakParticipants = $participants;

    $stmt = $connection->prepare(
        "SELECT COUNT(*) AS total
         FROM messages
         WHERE webinar_id = ?"
    );

    $stmt->bind_param("i", $webinarId);
    $stmt->execute();

    $messages =
        (int)$stmt->get_result()->fetch_assoc()["total"];

    $stmt->close();

    $stmt = $connection->prepare(
        "SELECT COUNT(*) AS total
         FROM questions
         WHERE webinar_id = ?"
    );

    $stmt->bind_param("i", $webinarId);
    $stmt->execute();

    $questions =
        (int)$stmt->get_result()->fetch_assoc()["total"];

    $stmt->close();

    $stmt = $connection->prepare(
        "SELECT COUNT(*) AS total
         FROM poll_votes pv
         INNER JOIN polls p
             ON p.id = pv.poll_id
         WHERE p.webinar_id = ?"
    );

    $stmt->bind_param("i", $webinarId);
    $stmt->execute();

    $pollResponses =
        (int)$stmt->get_result()->fetch_assoc()["total"];

    $stmt->close();

    $stmt = $connection->prepare(
        "SELECT COUNT(*) AS total
         FROM reactions
         WHERE webinar_id = ?"
    );

    $stmt->bind_param("i", $webinarId);
    $stmt->execute();

    $reactions =
        (int)$stmt->get_result()->fetch_assoc()["total"];

    $stmt->close();

    /*
     * Get every poll and its options.
     */
    $stmt = $connection->prepare(
        "SELECT
            p.id AS poll_id,
            p.question,
            p.is_active,
            po.id AS option_id,
            po.option_text,
            COUNT(pv.id) AS votes
         FROM polls p
         LEFT JOIN poll_options po
            ON po.poll_id = p.id
         LEFT JOIN poll_votes pv
            ON pv.option_id = po.id
         WHERE p.webinar_id = ?
         GROUP BY
            p.id,
            p.question,
            p.is_active,
            po.id,
            po.option_text
         ORDER BY
            p.id ASC,
            po.id ASC"
    );

    $stmt->bind_param("i", $webinarId);
    $stmt->execute();

    $result = $stmt->get_result();

    $pollMap = [];

    while ($row = $result->fetch_assoc()) {

        $pollId =
            (int)$row["poll_id"];

        if (!isset($pollMap[$pollId])) {

            $pollMap[$pollId] = [
                "poll_id" =>
                    $pollId,

                "question" =>
                    $row["question"],

                "is_active" =>
                    (int)$row["is_active"],

                "options" =>
                    []
            ];
        }

        if ($row["option_id"] !== null) {

            $pollMap[$pollId]["options"][] = [
                "id" =>
                    (int)$row["option_id"],

                "option_text" =>
                    $row["option_text"],

                "votes" =>
                    (int)$row["votes"]
            ];
        }
    }

    $stmt->close();

    echo json_encode([
        "success" => true,

        "webinar" => [
            "id" =>
                (int)$webinar["id"],

            "title" =>
                $webinar["title"],

            "host_name" =>
                $webinar["host_name"],

            "join_code" =>
                $webinar["join_code"],

            "status" =>
                $webinar["status"]
        ],

        "participants" =>
            $participants,

        "peak_participants" =>
            $peakParticipants,

        "messages" =>
            $messages,

        "questions" =>
            $questions,

        "poll_responses" =>
            $pollResponses,

        "reactions" =>
            $reactions,

        "polls" =>
            array_values($pollMap)
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
