<?php

require_once __DIR__ . "/db.php";

/*
 * LiveConnect file upload API
 *
 * Flow:
 * Host / Participant
 *      ↓
 * upload_file.php
 *      ↓
 * Cloudinary
 *      ↓
 * Railway MySQL (shared_files)
 *
 * Cloudinary credentials are read from environment variables.
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

header(
    "Vary: Origin"
);

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
 * Handle browser CORS preflight.
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

    echo json_encode([
        "success" => false,
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
        "success" => false,
        "message" =>
            "Cloudinary configuration is missing."
    ]);

    exit;
}


/*
 * Maximum file size:
 * 10 MB.
 */
$maxFileSize =
    10 * 1024 * 1024;


/*
 * Required webinar ID.
 */
$webinarId =
    $_POST["webinar_id"]
    ?? null;


/*
 * Participant ID is optional.
 *
 * Host uploads:
 *     participant_id = empty
 *
 * Participant uploads:
 *     participant_id = participant ID
 */
$participantId =
    $_POST["participant_id"]
    ?? null;


/*
 * Sender name.
 *
 * Host:
 *     Host
 *
 * Participant:
 *     Participant name
 */
$senderName =
    trim(
        $_POST["sender_name"]
        ?? ""
    );


/*
 * Basic validation.
 */
if (!$webinarId) {

    echo json_encode([
        "success" => false,
        "message" =>
            "webinar_id is required."
    ]);

    exit;
}


if ($senderName === "") {

    echo json_encode([
        "success" => false,
        "message" =>
            "sender_name is required."
    ]);

    exit;
}


/*
 * Check uploaded file.
 */
if (
    !isset(
        $_FILES["file"]
    )
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "No file was uploaded."
    ]);

    exit;
}


$file =
    $_FILES["file"];


/*
 * Check PHP upload error.
 */
if (
    $file["error"] !==
    UPLOAD_ERR_OK
) {

    $uploadMessages = [

        UPLOAD_ERR_INI_SIZE =>
            "The uploaded file is too large.",

        UPLOAD_ERR_FORM_SIZE =>
            "The uploaded file is too large.",

        UPLOAD_ERR_PARTIAL =>
            "The file upload was incomplete.",

        UPLOAD_ERR_NO_FILE =>
            "No file was uploaded.",

        UPLOAD_ERR_NO_TMP_DIR =>
            "Temporary upload folder is missing.",

        UPLOAD_ERR_CANT_WRITE =>
            "Unable to write uploaded file.",

        UPLOAD_ERR_EXTENSION =>
            "File upload was stopped by a PHP extension."

    ];

    echo json_encode([
        "success" => false,
        "message" =>
            $uploadMessages[
                $file["error"]
            ]
            ??
            "File upload failed."
    ]);

    exit;
}


/*
 * File size validation.
 */
$fileSize =
    (int)$file["size"];


if (
    $fileSize <= 0
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "The uploaded file is empty."
    ]);

    exit;
}


if (
    $fileSize >
    $maxFileSize
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "File size must be 10 MB or less."
    ]);

    exit;
}


/*
 * Original filename.
 */
$originalName =
    basename(
        $file["name"]
    );


if (
    $originalName === ""
) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid file name."
    ]);

    exit;
}


/*
 * Detect MIME type.
 */
$fileType =
    $file["type"]
    ?? "application/octet-stream";


if (
    function_exists("finfo_open")
) {

    $finfo =
        finfo_open(
            FILEINFO_MIME_TYPE
        );

    if ($finfo) {

        $detectedType =
            finfo_file(
                $finfo,
                $file["tmp_name"]
            );

        if (
            $detectedType
        ) {

            $fileType =
                $detectedType;

        }

        finfo_close(
            $finfo
        );
    }
}


/*
 * Verify webinar exists.
 */
try {

    $connection =
        get_db_connection();


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

        echo json_encode([
            "success" => false,
            "message" =>
                "Webinar not found."
        ]);

        exit;
    }


    /*
     * If participant_id was supplied,
     * verify that the participant belongs
     * to this webinar.
     */
    if (
        $participantId !== null &&
        $participantId !== ""
    ) {

        $stmt =
            $connection->prepare(
                "SELECT id
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

    } else {

        /*
         * Host upload.
         */
        $participantId =
            null;

    }


    /*
     * Create a unique Cloudinary public ID.
     */
    $uniqueId =
        "liveconnect_" .
        $webinarId .
        "_" .
        bin2hex(
            random_bytes(8)
        );


    /*
     * Cloudinary upload parameters.
     *
     * resource_type=auto allows
     * images and raw documents.
     */
    $timestamp =
        time();


    $folder =
        "liveconnect/webinar_" .
        $webinarId;


    /*
     * Cloudinary signature.
     *
     * Signature is generated from the
     * upload parameters + API secret.
     */
    $signatureBase =
        "folder=" .
        $folder .
        "&public_id=" .
        $uniqueId .
        "&timestamp=" .
        $timestamp;


    $signature =
        sha1(
            $signatureBase .
            $apiSecret
        );


    /*
     * Cloudinary upload URL.
     */
    $uploadUrl =
        "https://api.cloudinary.com/v1_1/" .
        rawurlencode(
            $cloudName
        ) .
        "/auto/upload";


    /*
     * Prepare multipart upload.
     */
    $postFields = [

        "file" =>
            curl_file_create(
                $file["tmp_name"],
                $fileType,
                $originalName
            ),

        "api_key" =>
            $apiKey,

        "timestamp" =>
            $timestamp,

        "signature" =>
            $signature,

        "folder" =>
            $folder,

        "public_id" =>
            $uniqueId

    ];


    /*
     * Send file to Cloudinary.
     */
    $curl =
        curl_init(
            $uploadUrl
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
                120,

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
            "success" => false,
            "message" =>
                "Unable to connect to Cloudinary.",
            "error" =>
                $curlError
        ]);

        exit;
    }


    /*
     * Decode Cloudinary response.
     */
    $cloudinaryData =
        json_decode(
            $cloudinaryResponse,
            true
        );


    if (
        $httpCode < 200 ||
        $httpCode >= 300 ||
        !is_array(
            $cloudinaryData
        ) ||
        empty(
            $cloudinaryData["secure_url"]
        )
    ) {

        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" =>
                "Cloudinary upload failed.",
            "error" =>
                $cloudinaryData["error"]["message"]
                ??
                "Unknown Cloudinary error."
        ]);

        exit;
    }


    /*
     * Cloudinary secure URL.
     */
    $fileUrl =
        $cloudinaryData[
            "secure_url"
        ];


    /*
     * Save file information
     * in Railway MySQL.
     */
    $stmt =
        $connection->prepare(
            "INSERT INTO shared_files
            (
                webinar_id,
                participant_id,
                sender_name,
                original_name,
                file_name,
                file_type,
                file_size,
                file_url
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );


    $fileName =
        $cloudinaryData[
            "public_id"
        ]
        ??
        $uniqueId;


    /*
     * bind_param cannot directly use
     * a nullable integer reliably when
     * passing null with i, so use 0
     * temporarily and convert it to NULL.
     */
    if (
        $participantId === null
    ) {

        $nullParticipant =
            null;

        $stmt->bind_param(
            "iissssis",
            $webinarId,
            $nullParticipant,
            $senderName,
            $originalName,
            $fileName,
            $fileType,
            $fileSize,
            $fileUrl
        );

    } else {

        $stmt->bind_param(
            "iissssis",
            $webinarId,
            $participantId,
            $senderName,
            $originalName,
            $fileName,
            $fileType,
            $fileSize,
            $fileUrl
        );

    }


    /*
     * The nullable participant_id needs
     * special handling because MySQLi's
     * "i" type does not bind SQL NULL
     * automatically in every configuration.
     *
     * Therefore use a direct NULL-safe
     * query when this is a host upload.
     */
    if (
        $participantId === null
    ) {

        $stmt->close();


        $stmt =
            $connection->prepare(
                "INSERT INTO shared_files
                (
                    webinar_id,
                    participant_id,
                    sender_name,
                    original_name,
                    file_name,
                    file_type,
                    file_size,
                    file_url
                )
                VALUES (?, NULL, ?, ?, ?, ?, ?, ?)"
            );


        $stmt->bind_param(
            "issssis",
            $webinarId,
            $senderName,
            $originalName,
            $fileName,
            $fileType,
            $fileSize,
            $fileUrl
        );

    }


    $stmt->execute();


    $sharedFileId =
        $stmt->insert_id;


    $stmt->close();


    /*
     * Success response.
     */
    echo json_encode([

        "success" =>
            true,

        "message" =>
            "File uploaded successfully.",

        "file_id" =>
            $sharedFileId,

        "webinar_id" =>
            (int)$webinarId,

        "participant_id" =>
            $participantId !== null
                ? (int)$participantId
                : null,

        "sender_name" =>
            $senderName,

        "original_name" =>
            $originalName,

        "file_name" =>
            $fileName,

        "file_type" =>
            $fileType,

        "file_size" =>
            $fileSize,

        "file_url" =>
            $fileUrl

    ]);


} catch (
    Throwable $error
) {

    http_response_code(500);

    echo json_encode([

        "success" =>
            false,

        "message" =>
            "File upload operation failed.",

        "error" =>
            $error->getMessage()

    ]);

}