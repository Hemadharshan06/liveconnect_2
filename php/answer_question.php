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

$questionId =
    $_GET["question_id"]
    ?? $_POST["question_id"]
    ?? null;

if (!$questionId) {
    echo json_encode([
        "success" => false,
        "message" => "question_id is required."
    ]);
    exit;
}

try {

    $connection =
        get_db_connection();

    /*
     * Check that the question exists.
     */
    $stmt =
        $connection->prepare(
            "SELECT id, webinar_id, participant_id,
                    participant_name, question, is_answered
             FROM questions
             WHERE id = ?
             LIMIT 1"
        );

    $stmt->bind_param(
        "i",
        $questionId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $questionRow =
        $result->fetch_assoc();

    $stmt->close();

    if (!$questionRow) {
        echo json_encode([
            "success" => false,
            "message" => "Question not found."
        ]);
        exit;
    }

    /*
     * If already answered, don't update again.
     */
    if ((int)$questionRow["is_answered"] === 1) {
        echo json_encode([
            "success" => true,
            "message" =>
                "Question was already answered.",
            "question_id" =>
                (int)$questionId,
            "webinar_id" =>
                (int)$questionRow["webinar_id"],
            "is_answered" =>
                true
        ]);
        exit;
    }

    /*
     * Mark question as answered.
     */
    $stmt =
        $connection->prepare(
            "UPDATE questions
             SET is_answered = 1
             WHERE id = ?"
        );

    $stmt->bind_param(
        "i",
        $questionId
    );

    $stmt->execute();

    $stmt->close();

    echo json_encode([
        "success" => true,
        "message" =>
            "Question answered successfully!",
        "question_id" =>
            (int)$questionId,
        "webinar_id" =>
            (int)$questionRow["webinar_id"],
        "participant_id" =>
            (int)$questionRow["participant_id"],
        "participant_name" =>
            $questionRow["participant_name"],
        "question" =>
            $questionRow["question"],
        "is_answered" =>
            true
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