<?php

/*
 * LiveConnect MySQL database connection.
 *
 * Production:
 *     Uses Railway public MySQL URL from Render.
 *
 * Local:
 *     Falls back to local XAMPP MySQL.
 */

$DB_PUBLIC_URL =
    getenv("DB_PUBLIC_URL");

$DB_HOST =
    getenv("DB_HOST") ?: "127.0.0.1";

$DB_PORT =
    (int)(getenv("DB_PORT") ?: 3306);

$DB_NAME =
    getenv("DB_NAME") ?: "liveconnect";

$DB_USER =
    getenv("DB_USER") ?: "root";

$DB_PASS =
    getenv("DB_PASS") ?: "";


/*
 * If DB_PUBLIC_URL exists,
 * use the Railway public connection.
 */
if ($DB_PUBLIC_URL) {

    $parts =
        parse_url($DB_PUBLIC_URL);

    if ($parts !== false) {

        if (isset($parts["host"])) {
            $DB_HOST =
                $parts["host"];
        }

        if (isset($parts["port"])) {
            $DB_PORT =
                (int)$parts["port"];
        }

        if (isset($parts["user"])) {
            $DB_USER =
                urldecode(
                    $parts["user"]
                );
        }

        if (isset($parts["pass"])) {
            $DB_PASS =
                urldecode(
                    $parts["pass"]
                );
        }

        if (isset($parts["path"])) {
            $DB_NAME =
                ltrim(
                    $parts["path"],
                    "/"
                );
        }
    }
}


function get_db_connection()
{
    global
        $DB_HOST,
        $DB_PORT,
        $DB_NAME,
        $DB_USER,
        $DB_PASS;

    static $connection = null;

    if ($connection !== null) {
        return $connection;
    }

    mysqli_report(
        MYSQLI_REPORT_ERROR |
        MYSQLI_REPORT_STRICT
    );

    try {

        $connection =
            new mysqli(
                $DB_HOST,
                $DB_USER,
                $DB_PASS,
                $DB_NAME,
                $DB_PORT
            );

        $connection->set_charset(
            "utf8mb4"
        );

        return $connection;

    } catch (
        mysqli_sql_exception $error
    ) {

        http_response_code(500);

        header(
            "Content-Type: application/json; charset=utf-8"
        );

        echo json_encode([
            "success" => false,
            "message" =>
                "Database connection failed.",
            "error" =>
                $error->getMessage()
        ]);

        exit;
    }
}