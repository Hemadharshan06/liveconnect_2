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

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "POST request required."
    ]);

    exit;
}

$webinarId =
    $_POST["webinar_id"]
    ?? null;

if (
    !$webinarId ||
    !is_numeric($webinarId)
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "webinar_id is required."
    ]);

    exit;
}

$webinarId =
    (int)$webinarId;

try {

    $connection =
        get_db_connection();


    /*
     * Verify that the webinar exists.
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
             WHERE id = ?
             LIMIT 1"
        );

    $stmt->bind_param(
        "i",
        $webinarId
    );

    $stmt->execute();

    $webinar =
        $stmt
            ->get_result()
            ->fetch_assoc();

    $stmt->close();


    if (!$webinar) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" =>
                "Webinar not found."
        ]);

        exit;
    }


    /*
     * End the webinar.
     *
     * If it is already ended,
     * keep the operation successful.
     */

    if (
        ($webinar["status"] ?? "")
        !==
        "ended"
    ) {

        $stmt =
            $connection->prepare(
                "UPDATE webinars
                 SET status = 'ended'
                 WHERE id = ?"
            );

        $stmt->bind_param(
            "i",
            $webinarId
        );

        $stmt->execute();

        $stmt->close();
    }


    echo json_encode([

        "success" =>
            true,

        "message" =>
            "Webinar ended successfully.",

        "webinar_id" =>
            $webinarId,

        "status" =>
            "ended",

        "title" =>
            $webinar["title"],

        "host_name" =>
            $webinar["host_name"],

        "join_code" =>
            $webinar["join_code"]

    ]);

} catch (Throwable $error) {

    http_response_code(500);

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "Database operation failed.",

        "error" =>
            $error->getMessage()

    ]);
}