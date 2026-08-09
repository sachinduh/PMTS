<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/validation_helper.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST method allowed"
    ]);
    exit;
}

try {
    $authUser = requireAuth();
    $data = json_decode(file_get_contents("php://input"), true) ?: [];

    $id = (int) ($data["id"] ?? $authUser['sub']);
    $full_name = trim($data["full_name"] ?? "");
    $email = trim($data["email"] ?? "");
    $phone = trim($data["phone"] ?? "");
    $department = trim($data["department"] ?? "");
    $hasProfilePicture = array_key_exists("profile_picture", $data);
    $profile_picture = $hasProfilePicture ? trim((string) $data["profile_picture"]) : null;

    if ($id !== (int) $authUser['sub'] && $authUser['role'] !== 'it_admin') {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "You can update only your own profile."
        ]);
        exit;
    }

    if (empty($id) || empty($full_name) || empty($email)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "User ID, full name and email are required"
        ]);
        exit;
    }

    $nameError = pmtsValidateFullName($full_name);
    if ($nameError !== null) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => $nameError
        ]);
        exit;
    }

    $pdo = getPDO();
    ensureAccountSecurityColumns($pdo);

    if ($profile_picture !== null && $profile_picture !== '') {
        if (strlen($profile_picture) > 3500000 || !preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $profile_picture)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Invalid profile image. Please choose a JPG, PNG, GIF, or WebP image smaller than 2MB."
            ]);
            exit;
        }
    }


    $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    $emailCheck->execute([$email, $id]);
    if ($emailCheck->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "This email is already used by another account."
        ]);
        exit;
    }

    if ($hasProfilePicture) {
        $stmt = $pdo->prepare("
            UPDATE users
            SET full_name = ?, email = ?, phone = ?, department = ?, profile_picture = ?
            WHERE id = ?
        ");

        $success = $stmt->execute([
            $full_name,
            $email,
            $phone,
            $department,
            $profile_picture === '' ? null : $profile_picture,
            $id
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE users
            SET full_name = ?, email = ?, phone = ?, department = ?
            WHERE id = ?
        ");

        $success = $stmt->execute([
            $full_name,
            $email,
            $phone,
            $department,
            $id
        ]);
    }

    echo json_encode([
        "success" => (bool) $success,
        "message" => $success ? "Profile updated successfully" : "Failed to update profile"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
