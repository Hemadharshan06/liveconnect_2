<?php

/*
 * LiveConnect MySQL database connection.
 *
 * Production:
 *     Uses Railway MYSQLURL.
 *
 * Local:
 *     Falls back to XAMPP MySQL.
 */

$MYSQL_URL = getenv("MYSQLURL");

$DB_HOST = getenv("DB_HOST") ?: "127.0.0.1";
$DB_PORT = (int)(getenv("DB_PORT") ?: 3306);
$DB_NAME = getenv("DB_NAME") ?: "liveconnect";
$DB_USER = getenv("DB_USER") ?: "root";
$DB_PASS = getenv("DB_PASS") ?: "";

if ($MYSQL_URL) {

    $parts = parse_url($MYSQL_URL);

    if ($parts !== false) {

        $DB_HOST =
            $parts["host"] ?? $DB_HOST;

        $DB_PORT =
            isset($parts["port"])
                ? (int)$parts["port"]
                : $DB_PORT;

        $DB_USER =
            isset($parts["user"])
                ? urldecode($parts["user"])
                : $DB_USER;

        $DB_PASS =
            isset($parts["pass"])
                ? urldecode($parts["pass"])
                : $DB_PASS;

        $DB_NAME =
            isset($parts["path"])
                ? ltrim($parts["path"], "/")
                : $DB_NAME;
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