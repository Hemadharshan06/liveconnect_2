<?php

require_once __DIR__ . "/db.php";

/*
 * LiveConnect file deletion API
 *
 * Host only.
 *
 * Flow:
 *     Host
 *       ↓
 *     delete_file.php
 *       ↓
 *     Cloudinary DELETE
 *       ↓
 *     Railway MySQL DELETE
 */


/*
 * CORS
 */

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
    "Access-Control-Allow-Methods: POST, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type"
);

header(
    "Content-Type: application/json; charset=utf-8"
);


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

    http_response_code(405);

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "POST request required."

    ]);

    exit;

}


/*
 * Cloudinary credentials.
 */

$cloudName =
    getenv(
        "CLOUDINARY_CLOUD_NAME"
    );

$apiKey =
    getenv(
        "CLOUDINARY_API_KEY"
    );

$apiSecret =
    getenv(
        "CLOUDINARY_API_SECRET"
    );


if (
    !$cloudName ||
    !$apiKey ||
    !$apiSecret
) {

    http_response_code(500);

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "Cloudinary configuration is missing."

    ]);

    exit;

}


/*
 * Required file ID.
 */

$fileId =
    $_POST["file_id"]
    ?? null;


/*
 * Required webinar ID.
 */

$webinarId =
    $_POST["webinar_id"]
    ?? null;


if (
    !$fileId ||
    !is_numeric($fileId)
) {

    http_response_code(400);

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "file_id is required."

    ]);

    exit;

}


if (
    !$webinarId ||
    !is_numeric($webinarId)
) {

    http_response_code(400);

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "webinar_id is required."

    ]);

    exit;

}


$fileId =
    (int)$fileId;

$webinarId =
    (int)$webinarId;


try {

    $connection =
        get_db_connection();


    /*
     * Get the file and verify that it
     * belongs to this webinar.
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
                file_url
             FROM shared_files
             WHERE id = ?
               AND webinar_id = ?
             LIMIT 1"
        );


    $stmt->bind_param(
        "ii",
        $fileId,
        $webinarId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $file =
        $result->fetch_assoc();


    $stmt->close();


    if (
        !$file
    ) {

        http_response_code(404);

        echo json_encode([

            "success" =>
                false,

            "message" =>
                "File not found for this webinar."

        ]);

        exit;

    }


    /*
     * Only Host-uploaded files may be
     * deleted by this endpoint.
     *
     * Host files have participant_id = NULL.
     */

    if (
        $file["participant_id"] !== null
    ) {

        http_response_code(403);

        echo json_encode([

            "success" =>
                false,

            "message" =>
                "Only Host-uploaded files can be deleted."

        ]);

        exit;

    }


    /*
     * The value stored in file_name is
     * the Cloudinary public_id.
     */

    $publicId =
        $file["file_name"];


    if (
        !$publicId
    ) {

        http_response_code(500);

        echo json_encode([

            "success" =>
                false,

            "message" =>
                "Cloudinary public ID is missing."

        ]);

        exit;

    }


    /*
     * Cloudinary destroy signature.
     *
     * The destroy API requires:
     *
     * public_id
     * timestamp
     */

    $timestamp =
        time();


    $signatureBase =
        "public_id=" .
        $publicId .
        "&timestamp=" .
        $timestamp;


    $signature =
        sha1(
            $signatureBase .
            $apiSecret
        );


    /*
     * Cloudinary destroy endpoint.
     *
     * resource_type=raw because PDFs and
     * documents are uploaded through the
     * auto/raw side of Cloudinary.
     */

    $destroyUrl =
        "https://api.cloudinary.com/v1_1/" .
        rawurlencode(
            $cloudName
        ) .
        "/raw/destroy";


    $postFields = [

        "public_id" =>
            $publicId,

        "timestamp" =>
            $timestamp,

        "api_key" =>
            $apiKey,

        "signature" =>
            $signature

    ];


    /*
     * Delete the Cloudinary asset.
     */

    $curl =
        curl_init(
            $destroyUrl
        );


    curl_setopt_array(
        $curl,
        [

            CURLOPT_POST =>
                true,

            CURLOPT_POSTFIELDS =>
                $postFields,

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_TIMEOUT =>
                60,

            CURLOPT_CONNECTTIMEOUT =>
                20

        ]
    );


    $cloudinaryResponse =
        curl_exec(
            $curl
        );


    $curlError =
        curl_error(
            $curl
        );


    $httpCode =
        curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );


    curl_close(
        $curl
    );


    if (
        $cloudinaryResponse ===
        false
    ) {

        http_response_code(500);

        echo json_encode([

            "success" =>
                false,

            "message" =>
                "Unable to connect to Cloudinary.",

            "error" =>
                $curlError

        ]);

        exit;

    }


    $cloudinaryData =
        json_decode(
            $cloudinaryResponse,
            true
        );


    /*
     * Cloudinary may return:
     *
     * result = ok
     * result = not found
     *
     * A "not found" asset is treated as
     * already deleted so the database
     * can still be cleaned up.
     */

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {

        http_response_code(500);

        echo json_encode([

            "success" =>
                false,

            "message" =>
                "Cloudinary deletion failed.",

            "error" =>
                $cloudinaryData["error"]["message"]
                ??
                "Unknown Cloudinary error."

        ]);

        exit;

    }


    $destroyResult =
        $cloudinaryData["result"]
        ?? null;


    if (
        $destroyResult !==
        "ok" &&
        $destroyResult !==
        "not found"
    ) {

        http_response_code(500);

        echo json_encode([

            "success" =>
                false,

            "message" =>
                "Cloudinary did not delete the file.",

            "result" =>
                $destroyResult

        ]);

        exit;

    }


    /*
     * Cloudinary deletion succeeded.
     *
     * Now delete the database record.
     */

    $stmt =
        $connection->prepare(
            "DELETE FROM shared_files
             WHERE id = ?
               AND webinar_id = ?"
        );


    $stmt->bind_param(
        "ii",
        $fileId,
        $webinarId
    );


    $stmt->execute();


    $deletedRows =
        $stmt->affected_rows;


    $stmt->close();


    if (
        $deletedRows !==
        1
    ) {

        http_response_code(500);

        echo json_encode([

            "success" =>
                false,

            "message" =>
                "Cloudinary file was deleted, but the database record could not be removed."

        ]);

        exit;

    }


    /*
     * Success.
     */

    echo json_encode([

        "success" =>
            true,

        "message" =>
            "File deleted successfully.",

        "file_id" =>
            $fileId,

        "webinar_id" =>
            $webinarId,

        "original_name" =>
            $file["original_name"]

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
            "File deletion operation failed.",

        "error" =>
            $error->getMessage()

    ]);

}