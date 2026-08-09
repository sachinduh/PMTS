<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../config/db.php";

try {
    $pdo = getPDO();

    echo json_encode([
        "success" => true,
        "message" => "Database connected successfully"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "error" => $e->getMessage()
    ]);
}