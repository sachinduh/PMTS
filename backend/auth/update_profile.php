<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Only POST method allowed"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id = $data["id"] ?? "";
$full_name = trim($data["full_name"] ?? "");
$email = trim($data["email"] ?? "");
$phone = trim($data["phone"] ?? "");
$department = trim($data["department"] ?? "");

if (empty($id) || empty($full_name) || empty($email)) {
    echo json_encode([
        "success" => false,
        "message" => "User ID, full name and email are required"
    ]);
    exit;
}

try {
    $pdo = getPDO();

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

    if ($success) {
        echo json_encode([
            "success" => true,
            "message" => "Profile updated successfully"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to update profile"
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>