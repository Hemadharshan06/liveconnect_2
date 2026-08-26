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
    "Access-Control-Allow-Methods: GET, POST, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type"
);

header(
    "Content-Type: application/json; charset=utf-8"
);

if (
    $_SERVER["REQUEST_METHOD"] === "OPTIONS"
) {
    http_response_code(204);
    exit;
}

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);

    exit;
}

$title =
    trim(
        $_POST["title"] ?? ""
    );

$hostName =
    trim(
        $_POST["host_name"] ?? ""
    );

if ($title === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Title is required."
    ]);

    exit;
}

if ($hostName === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Host name is required."
    ]);

    exit;
}

function generate_join_code($db)
{
    $characters =
        "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

    do {

        $joinCode = "";

        for ($i = 0; $i < 6; $i++) {

            $joinCode .=
                $characters[
                    random_int(
                        0,
                        strlen($characters) - 1
                    )
                ];

        }

        $stmt =
            $db->prepare(
                "SELECT id
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

        $exists =
            $result->num_rows > 0;

        $stmt->close();

    } while ($exists);

    return $joinCode;
}

try {

    $db =
        get_db_connection();

    $joinCode =
        generate_join_code($db);

    $status =
        "live";

    $stmt =
        $db->prepare(
            "INSERT INTO webinars
            (
                title,
                host_name,
                join_code,
                status
            )
            VALUES (?, ?, ?, ?)"
        );

    $stmt->bind_param(
        "ssss",
        $title,
        $hostName,
        $joinCode,
        $status
    );

    $stmt->execute();

    $webinarId =
        $stmt->insert_id;

    $stmt->close();

    echo json_encode([
        "success" => true,

        "message" =>
            "Webinar created successfully!",

        "webinar_id" =>
            $webinarId,

        "title" =>
            $title,

        "host_name" =>
            $hostName,

        "join_code" =>
            $joinCode,

        "status" =>
            $status
    ]);

} catch (Throwable $error) {

    http_response_code(500);

    echo json_encode([
        "success" => false,

        "message" =>
            "Failed to create webinar.",

        "error" =>
            $error->getMessage()
    ]);
}