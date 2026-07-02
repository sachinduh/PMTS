<?php
// ============================================================
//  PMTS – PUT /users/update_user.php
//  Update user profile details (IT Admin or own profile)
//  Body: { "user_id": 5, "full_name": "...", "phone": "...", ... }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use PUT or POST.']);
    exit;
}

try {
    $authUser = requireAuth();
    $input    = json_decode(file_get_contents('php://input'), true);

    $targetUserId = (int) ($input['user_id'] ?? $authUser['sub']);

    // Only IT Admin can update other users; regular users can only update themselves
    if ($targetUserId !== (int) $authUser['sub'] && $authUser['role'] !== 'it_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only update your own profile.']);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetUserId]);
    $existingUser = $stmt->fetch();

    if (!$existingUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // Build dynamic UPDATE - only update fields that are provided
    $allowedFields = ['full_name', 'phone', 'nic', 'department', 'organization'];
    $setClauses    = [];
    $params        = [];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $setClauses[] = "$field = ?";
            $params[]     = trim($input[$field]);
        }
    }

    // Allow password change if provided
    if (!empty($input['new_password'])) {
        if (strlen($input['new_password']) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
            exit;
        }
        $setClauses[] = "password = ?";
        $params[]     = password_hash($input['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (empty($setClauses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        exit;
    }

    $params[] = $targetUserId;
    $sql      = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $pdo->prepare($sql)->execute($params);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_USER',
        'users',
        "Updated profile for user ID $targetUserId ({$existingUser['email']})"
    );

    echo json_encode([
        'success' => true,
        'message' => 'User profile updated successfully.',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update user.']);
    error_log("PMTS UpdateUser Error: " . $e->getMessage());
}
