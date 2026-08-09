<?php
// ============================================================
//  PMTS – DELETE/POST /users/delete_user.php
//  Remove/deactivate a user (IT Admin only)
//  Body: { "user_id": 5, "hard_delete": false }
//  Default is soft remove: status = rejected. The assigned role remains fixed and is not removed.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

header('Content-Type: application/json');

if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use DELETE or POST.']);
    exit;
}

try {
    $authUser = requireRole(['it_admin']);
    $input    = json_decode(file_get_contents('php://input'), true) ?: [];

    $userId     = (int) ($input['user_id'] ?? 0);
    $hardDelete = (bool) ($input['hard_delete'] ?? false);

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'user_id is required.']);
        exit;
    }

    if ($userId === (int) $authUser['sub']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You cannot remove your own IT Admin account.']);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, full_name, email, role, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if ($hardDelete) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Permanent user deletion is disabled because assigned roles must remain fixed for audit/history. Use Remove to deactivate the account.']);
        exit;
    } else {
        $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")->execute([$userId]);
        $action = 'REMOVE_USER';
        $msg    = "Removed/deactivated user ID $userId ({$user['email']}). Fixed role kept as: {$user['role']}";
        $responseMessage = "User '{$user['full_name']}' removed. Account is deactivated and cannot login. Fixed role was kept unchanged.";

        $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES (?, 'Account Removed', ?, 'error')"
        )->execute([$userId, 'Your PMTS account has been removed/deactivated. Your assigned role remains fixed in the system. Please contact IT Admin if this is a mistake.']);
    }

    createAuditLog($pdo, $authUser['sub'], $action, 'users', $msg);

    echo json_encode([
        'success' => true,
        'message' => $responseMessage,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to remove user.', 'error' => $e->getMessage()]);
    error_log("PMTS DeleteUser Error: " . $e->getMessage());
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to remove user.', 'error' => $e->getMessage()]);
    error_log("PMTS DeleteUser Throwable: " . $e->getMessage());
}
