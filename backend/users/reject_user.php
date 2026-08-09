<?php
// ============================================================
//  PMTS – POST /users/reject_user.php
//  Reject a pending user (IT Admin only)
//  Body: { "user_id": 5, "reason": "Optional reason" }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['it_admin']);
    $input    = json_decode(file_get_contents('php://input'), true);

    $userId = (int) ($input['user_id'] ?? 0);
    $reason = trim($input['reason'] ?? 'No reason provided.');

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'user_id is required.']);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, full_name, email, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    if ($user['status'] === 'rejected') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'User is already rejected.']);
        exit;
    }

    $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")
        ->execute([$userId]);

    // Notify the user
    $msg = "Your account registration has been rejected. Reason: $reason. Please contact IT Admin for assistance.";
    $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Account Rejected', ?, 'error')"
    )->execute([$userId, $msg]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'REJECT_USER',
        'users',
        "Rejected user ID $userId ({$user['email']}). Reason: $reason"
    );

    echo json_encode([
        'success' => true,
        'message' => "User '{$user['full_name']}' has been rejected.",
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to reject user.']);
    error_log("PMTS RejectUser Error: " . $e->getMessage());
}
