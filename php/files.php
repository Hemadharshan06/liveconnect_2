<?php

require_once __DIR__ . "/db.php";

$origin =
    $_SERVER["HTTP_ORIGIN"] ?? "";

$allowedOrigins = [
    "http://127.0.0.1:8000",
    "http://localhost:8000",
    "http://localhost",
    "https://liveconnect-2-1.onrender.com"
];

if (
    in_array(
        $origin,
        $allowedOrigins,
        true
    )
) {

    header(
        "Access-Control-Allow-Origin: " .
        $origin
    );

}

header("Vary: Origin");

header(
    "Access-Control-Allow-Methods: GET, OPTIONS"
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
    "GET"
) {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" =>
            "GET request required."
    ]);

    exit;

}


$webinarId =
    $_GET["webinar_id"]
    ?? null;


if (
    !$webinarId ||
    !is_numeric($webinarId)
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "webinar_id is required."
    ]);

    exit;

}


$webinarId =
    (int)$webinarId;


try {

    $connection =
        get_db_connection();


    /*
     * Make sure the webinar exists.
     */

    $stmt =
        $connection->prepare(
            "SELECT id
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
     * Get all files shared in
     * this webinar.
     */

    $stmt =
        $connection->prepare(
            "SELECT
                id,
                webinar_id,
                participant_id,
                sender_name,
                original_name,
                file_name,
                file_type,
                file_size,
                file_url,
                created_at
             FROM shared_files
             WHERE webinar_id = ?
             ORDER BY id ASC"
        );


    $stmt->bind_param(
        "i",
        $webinarId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $files = [];


    while (
        $row =
            $result->fetch_assoc()
    ) {

        $files[] = [

            "file_id" =>
                (int)$row["id"],

            "webinar_id" =>
                (int)$row["webinar_id"],

            "participant_id" =>
                $row["participant_id"] !== null
                    ? (int)$row["participant_id"]
                    : null,

            "sender_name" =>
                $row["sender_name"],

            "original_name" =>
                $row["original_name"],

            "file_name" =>
                $row["file_name"],

            "file_type" =>
                $row["file_type"],

            "file_size" =>
                (int)$row["file_size"],

            "file_url" =>
                $row["file_url"],

            "created_at" =>
                $row["created_at"]

        ];

    }


    $stmt->close();


    echo json_encode([

        "success" =>
            true,

        "webinar_id" =>
            $webinarId,

        "files" =>
            $files

    ]);


}
catch (
    Throwable $error
) {

    http_response_code(500);

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "Unable to load shared files.",

        "error" =>
            $error->getMessage()

    ]);

}