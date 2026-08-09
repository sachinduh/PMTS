<?php
// ============================================================
//  PMTS – POST /users/unlock_user.php
//  Unlock an account after 3 failed password attempts (IT Admin only)
//  Body: { "user_id": 5 }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['it_admin']);
    $pdo = getPDO();
    ensureAccountSecurityColumns($pdo);

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $userId = (int) ($input['user_id'] ?? 0);

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id, full_name, email, role, status, account_locked, failed_login_attempts
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if (($user['status'] ?? '') === 'rejected') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Removed/rejected users cannot be unblocked. Approve or recreate the account instead.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE users
         SET account_locked = 0,
             failed_login_attempts = 0,
             last_failed_login_at = NULL,
             locked_at = NULL,
             locked_reason = NULL,
             unlocked_by = ?,
             unlocked_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([(int) $authUser['sub'], $userId]);

    try {
        $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES (?, 'Account Unblocked', 'Your PMTS account has been unblocked by IT Admin. You can login again.', 'success')"
        )->execute([$userId]);
    } catch (Throwable $ignored) {
        // Notifications should not prevent unlocking.
    }

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UNLOCK_USER',
        'users',
        "Unblocked user ID $userId ({$user['email']}) after failed login lock"
    );

    echo json_encode([
        'success' => true,
        'message' => 'User account unblocked successfully. Failed login attempts were reset.',
        'user_id' => $userId,
        'account_locked' => 0,
        'failed_login_attempts' => 0,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to unblock user.',
        'error' => $e->getMessage(),
    ]);
    error_log('PMTS UnlockUser Error: ' . $e->getMessage());
}
