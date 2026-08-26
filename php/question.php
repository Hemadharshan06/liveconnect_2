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
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "POST request required."
    ]);
    exit;
}

$webinarId =
    $_GET["webinar_id"]
    ?? $_POST["webinar_id"]
    ?? null;

$participantId =
    $_GET["participant_id"]
    ?? $_POST["participant_id"]
    ?? null;

$question =
    trim(
        $_GET["question"]
        ?? $_POST["question"]
        ?? ""
    );

if (!$webinarId) {
    echo json_encode([
        "success" => false,
        "message" => "webinar_id is required."
    ]);
    exit;
}

if (!$participantId) {
    echo json_encode([
        "success" => false,
        "message" => "participant_id is required."
    ]);
    exit;
}

if ($question === "") {
    echo json_encode([
        "success" => false,
        "message" => "question is required."
    ]);
    exit;
}

try {

    $connection =
        get_db_connection();

    /*
     * Find the participant and verify
     * that the participant belongs to
     * this webinar.
     */
    $stmt =
        $connection->prepare(
            "SELECT name
             FROM participants
             WHERE id = ?
               AND webinar_id = ?
             LIMIT 1"
        );

    $stmt->bind_param(
        "ii",
        $participantId,
        $webinarId
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
                "Participant not found for this webinar."
        ]);
        exit;
    }

    $participantName =
        $participant["name"];

    /*
     * Store the question.
     */
    $stmt =
        $connection->prepare(
            "INSERT INTO questions
            (
                webinar_id,
                participant_id,
                participant_name,
                question,
                is_answered
            )
            VALUES (?, ?, ?, ?, 0)"
        );

    $stmt->bind_param(
        "iiss",
        $webinarId,
        $participantId,
        $participantName,
        $question
    );

    $stmt->execute();

    $questionId =
        $stmt->insert_id;

    $stmt->close();

    echo json_encode([
        "success" => true,
        "message" =>
            "Question submitted successfully!",
        "question_id" =>
            $questionId,
        "webinar_id" =>
            (int)$webinarId,
        "participant_id" =>
            (int)$participantId,
        "participant_name" =>
            $participantName,
        "question" =>
            $question
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