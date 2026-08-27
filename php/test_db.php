<?php

require_once __DIR__ . "/db.php";

try {

    $db =
        get_db_connection();

    $result =
        $db->query(
            "SELECT DATABASE() AS db_name"
        );

    $row =
        $result->fetch_assoc();

    header(
        "Content-Type: application/json; charset=utf-8"
    );

    echo json_encode([
        "success" => true,
        "message" =>
            "MySQL connection successful.",
        "database" =>
            $row["db_name"]
    ]);

} catch (
    Throwable $error
) {

    http_response_code(500);

    header(
        "Content-Type: application/json; charset=utf-8"
    );

    echo json_encode([
        "success" => false,
        "message" =>
            $error->getMessage()
    ]);
}