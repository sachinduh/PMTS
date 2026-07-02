<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

header("Content-Type: application/json");

try {
    $pdo = getPDO();

    $fullName = "Sachindu Himsara";
    $email = "sachinduhimsara06@gmail.com";
    $plainPassword = "Admin@123";
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    // Check table columns
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

    $has = function ($col) use ($columns) {
        return in_array($col, $columns);
    };

    // Find existing IT Admin by email or role
    $check = $pdo->prepare("
        SELECT id 
        FROM users 
        WHERE email = ? OR role = 'it_admin'
        LIMIT 1
    ");
    $check->execute([$email]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $fields = [];
        $values = [];

        if ($has("full_name")) {
            $fields[] = "full_name = ?";
            $values[] = $fullName;
        }

        if ($has("email")) {
            $fields[] = "email = ?";
            $values[] = $email;
        }

        if ($has("password")) {
            $fields[] = "password = ?";
            $values[] = $hashedPassword;
        }

        if ($has("user_type")) {
            $fields[] = "user_type = ?";
            $values[] = "Hospital Staff";
        }

        if ($has("role")) {
            $fields[] = "role = ?";
            $values[] = "it_admin";
        }

        if ($has("status")) {
            $fields[] = "status = ?";
            $values[] = "active";
        }

        if ($has("role_locked")) {
            $fields[] = "role_locked = ?";
            $values[] = 1;
        }

        $values[] = $existing["id"];

        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        echo json_encode([
            "success" => true,
            "message" => "Existing IT Admin fixed successfully.",
            "login_email" => $email,
            "login_password" => $plainPassword
        ]);
        exit;
    }

    // Create new IT Admin
    $insertFields = [];
    $placeholders = [];
    $values = [];

    if ($has("full_name")) {
        $insertFields[] = "full_name";
        $placeholders[] = "?";
        $values[] = $fullName;
    }

    if ($has("email")) {
        $insertFields[] = "email";
        $placeholders[] = "?";
        $values[] = $email;
    }

    if ($has("password")) {
        $insertFields[] = "password";
        $placeholders[] = "?";
        $values[] = $hashedPassword;
    }

    if ($has("user_type")) {
        $insertFields[] = "user_type";
        $placeholders[] = "?";
        $values[] = "Hospital Staff";
    }

    if ($has("role")) {
        $insertFields[] = "role";
        $placeholders[] = "?";
        $values[] = "it_admin";
    }

    if ($has("status")) {
        $insertFields[] = "status";
        $placeholders[] = "?";
        $values[] = "active";
    }

    if ($has("role_locked")) {
        $insertFields[] = "role_locked";
        $placeholders[] = "?";
        $values[] = 1;
    }

    $sql = "
        INSERT INTO users (" . implode(", ", $insertFields) . ")
        VALUES (" . implode(", ", $placeholders) . ")
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    echo json_encode([
        "success" => true,
        "message" => "New IT Admin created successfully.",
        "login_email" => $email,
        "login_password" => $plainPassword
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}