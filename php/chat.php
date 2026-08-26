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
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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
    $_POST["participant_id"]
    ?? null;

$message =
    trim(
        $_POST["message"]
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

if ($message === "") {
    echo json_encode([
        "success" => false,
        "message" => "message is required."
    ]);
    exit;
}

try {

    $connection =
        get_db_connection();

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

    $stmt =
        $connection->prepare(
            "INSERT INTO messages
            (
                webinar_id,
                participant_id,
                participant_name,
                message
            )
            VALUES (?, ?, ?, ?)"
        );

    $stmt->bind_param(
        "iiss",
        $webinarId,
        $participantId,
        $participantName,
        $message
    );

    $stmt->execute();

    $messageId =
        $stmt->insert_id;

    $stmt->close();

    echo json_encode([
        "success" => true,
        "message" =>
            "Message sent successfully!",
        "message_id" =>
            $messageId,
        "participant_id" =>
            $participantId,
        "participant_name" =>
            $participantName,
        "webinar_id" =>
            $webinarId,
        "text" =>
            $message
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