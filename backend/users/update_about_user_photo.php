<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function input_json(): array {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

try {
    $authUser = requireRole(['it_admin']);
    $pdo = getPDO();
    ensureAccountSecurityColumns($pdo);

    $data = input_json();
    $userId = (int)($data['user_id'] ?? 0);
    $profilePicture = array_key_exists('profile_picture', $data) ? trim((string)$data['profile_picture']) : '';

    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required.']);
        exit;
    }

    if ($profilePicture !== '') {
        if (strlen($profilePicture) > 7000000 || !preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $profilePicture)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image. Use JPG, PNG, GIF, or WEBP. Large photos are compressed by the frontend before upload.']);
            exit;
        }
    }

    $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    $update = $pdo->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
    $update->execute([$profilePicture === '' ? null : $profilePicture, $userId]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_USER_PHOTO',
        'users',
        "Updated about/profile photo for user ID {$userId} ({$user['email']})"
    );

    echo json_encode([
        'success' => true,
        'message' => 'User photo updated successfully.',
        'user_id' => $userId,
        'profile_picture' => $profilePicture === '' ? null : $profilePicture,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update user photo.',
        'error' => $e->getMessage(),
    ]);
}
