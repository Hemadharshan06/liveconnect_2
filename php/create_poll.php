<?php

require_once __DIR__ . "/db.php";


$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

$allowedOrigins = [
    "http://127.0.0.1:8000",
    "http://localhost:8000"
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


/*
 * JSON response helper.
 */
function json_response(
    array $data,
    int $statusCode = 200
) {

    http_response_code(
        $statusCode
    );

    echo json_encode(
        $data
    );

    exit;

}


/*
 * Handle CORS preflight.
 */
if (
    $_SERVER["REQUEST_METHOD"] ===
    "OPTIONS"
) {

    http_response_code(204);

    exit;

}


/*
 * Only POST is allowed.
 */
if (
    $_SERVER["REQUEST_METHOD"] !==
    "POST"
) {

    json_response(
        [
            "success" => false,

            "message" =>
                "POST request required."
        ],
        405
    );

}


/*
 * Read JSON request body.
 */
$raw =
    file_get_contents(
        "php://input"
    );


$data =
    json_decode(
        $raw,
        true
    );


if (!is_array($data)) {

    json_response(
        [
            "success" => false,

            "message" =>
                "Invalid JSON request."
        ],
        400
    );

}


/*
 * Get webinar ID.
 *
 * Supports:
 *   ?webinar_id=10
 *
 * or:
 *   JSON {"webinar_id":10}
 */
$webinarId =
    $_GET["webinar_id"]
    ??
    $data["webinar_id"]
    ??
    null;


/*
 * Get poll question.
 */
$question =
    trim(
        $data["question"]
        ?? ""
    );


/*
 * Get poll options.
 */
$options =
    $data["options"]
    ?? [];


/*
 * Validate webinar ID.
 */
if (!$webinarId) {

    json_response(
        [
            "success" => false,

            "message" =>
                "webinar_id is required."
        ],
        400
    );

}


/*
 * Validate question.
 */
if ($question === "") {

    json_response(
        [
            "success" => false,

            "message" =>
                "question is required."
        ],
        400
    );

}


/*
 * At least two options
 * are required.
 */
if (
    !is_array($options)
    ||
    count($options) < 2
) {

    json_response(
        [
            "success" => false,

            "message" =>
                "At least two options are required."
        ],
        400
    );

}


/*
 * Remove empty options
 * and trim the remaining ones.
 */
$options =
    array_values(
        array_filter(
            array_map(
                "trim",
                $options
            ),
            function ($option) {

                return $option !== "";

            }
        )
    );


/*
 * Validate again after
 * removing empty options.
 */
if (
    count($options) < 2
) {

    json_response(
        [
            "success" => false,

            "message" =>
                "At least two non-empty options are required."
        ],
        400
    );

}


try {

    $connection =
        get_db_connection();


    /*
     * Verify that the webinar exists.
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


    $result =
        $stmt->get_result();


    $webinar =
        $result->fetch_assoc();


    $stmt->close();


    if (!$webinar) {

        json_response(
            [
                "success" => false,

                "message" =>
                    "Webinar not found."
            ],
            404
        );

    }


    /*
     * Create poll.
     */
    $stmt =
        $connection->prepare(
            "INSERT INTO polls
            (
                webinar_id,
                question,
                is_active
            )
            VALUES (?, ?, 1)"
        );


    $stmt->bind_param(
        "is",
        $webinarId,
        $question
    );


    $stmt->execute();


    $pollId =
        $stmt->insert_id;


    $stmt->close();


    /*
     * Create poll options.
     */
    $insertOption =
        $connection->prepare(
            "INSERT INTO poll_options
            (
                poll_id,
                option_text
            )
            VALUES (?, ?)"
        );


    $createdOptions = [];


    foreach (
        $options
        as $optionText
    ) {

        $insertOption->bind_param(
            "is",
            $pollId,
            $optionText
        );


        $insertOption->execute();


        $createdOptions[] = [

            "id" =>
                (int)$insertOption->insert_id,

            "text" =>
                $optionText

        ];

    }


    $insertOption->close();


    /*
     * Return complete poll data.
     *
     * The option IDs are important because
     * vote_poll.php needs the option_id
     * when the participant votes.
     */
    json_response(
        [
            "success" => true,

            "message" =>
                "Poll created successfully!",

            "poll_id" =>
                (int)$pollId,

            "question" =>
                $question,

            "options" =>
                $createdOptions
        ]
    );


} catch (Throwable $error) {

    json_response(
        [
            "success" => false,

            "message" =>
                "Database operation failed.",

            "error" =>
                $error->getMessage()
        ],
        500
    );

}