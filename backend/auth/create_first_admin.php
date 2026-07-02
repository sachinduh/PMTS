<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

header("Content-Type: application/json");

try {
    $pdo = getPDO();

    $full_name = "Sachindu Himsara";
    $email = "sachinduhimsara06@gmail.com";
    $plain_password = "Admin@123";
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    $user_type = "Hospital Staff";
    $role = "it_admin";
    $status = "active";

    // Check admin by email or role
    $check = $pdo->prepare("
        SELECT id 
        FROM users 
        WHERE email = ? OR role = 'it_admin'
        LIMIT 1
    ");
    $check->execute([$email]);
    $existingAdmin = $check->fetch(PDO::FETCH_ASSOC);

    if ($existingAdmin) {
        // Update existing admin
        $stmt = $pdo->prepare("
            UPDATE users
            SET 
                full_name = ?,
                email = ?,
                password = ?,
                user_type = ?,
                role = ?,
                status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $full_name,
            $email,
            $hashed_password,
            $user_type,
            $role,
            $status,
            $existingAdmin['id']
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Existing IT Admin updated successfully.",
            "login_email" => $email,
            "login_password" => $plain_password
        ]);
        exit;
    }

    // Create new admin
    $stmt = $pdo->prepare("
        INSERT INTO users 
        (full_name, email, password, user_type, role, status)
        VALUES 
        (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $full_name,
        $email,
        $hashed_password,
        $user_type,
        $role,
        $status
    ]);

    echo json_encode([
        "success" => true,
        "message" => "First IT Admin created successfully.",
        "login_email" => $email,
        "login_password" => $plain_password
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}