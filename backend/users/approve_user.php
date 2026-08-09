<?php
// ============================================================
//  PMTS – POST /users/approve_user.php
//  Approve a pending user and assign a FIXED role (IT Admin only)
//  Body: { "user_id": 5, "role": "procurement_officer" }
//  After approval, the role is locked permanently.
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
    $role   = trim($input['role'] ?? '');

    if (!$userId || !$role) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'User ID and role are required.',
            'received' => $input,
        ]);
        exit;
    }

    $allowedRoles = [
        'director',
        'accountant',
        'procurement_officer',
        'bec_member',
        'specification_committee',
    ];

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid approval role selected. IT Admin cannot be assigned through user registration approval.']);
        exit;
    }

    if ($userId === (int) $authUser['sub']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You cannot approve/change your own IT Admin role here.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, full_name, email, status, role, role_locked FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if ((int) ($user['role_locked'] ?? 0) === 1 || ($user['role'] ?? 'pending') !== 'pending') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This user already has a fixed role. Assigned roles cannot be changed or removed.',
        ]);
        exit;
    }

    if (($user['status'] ?? '') === 'rejected') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This user is removed/rejected and cannot be approved from here.']);
        exit;
    }

    if (($user['status'] ?? 'pending') !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only pending users can be approved and given a fixed role.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET role = ?, status = 'active', role_locked = 1, account_locked = 0, failed_login_attempts = 0, last_failed_login_at = NULL, locked_at = NULL, locked_reason = NULL WHERE id = ? AND role = 'pending' AND role_locked = 0");
    $stmt->execute([$role, $userId]);

    $verify = $pdo->prepare("SELECT role, status, role_locked FROM users WHERE id = ? LIMIT 1");
    $verify->execute([$userId]);
    $updated = $verify->fetch(PDO::FETCH_ASSOC);

    if (!$updated || $updated['role'] !== $role || $updated['status'] !== 'active' || (int) $updated['role_locked'] !== 1) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'User was not approved because the role is already locked.']);
        exit;
    }

    $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES (?, 'Account Approved', ?, 'success')"
    )->execute([$userId, "Your PMTS account has been approved. Your fixed role is: $role. You can now login."]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'APPROVE_USER_FIXED_ROLE',
        'users',
        "Approved user ID $userId ({$user['email']}) as fixed role: $role"
    );

    echo json_encode([
        'success' => true,
        'message' => 'User approved successfully. Role assigned, locked permanently, and account activated.',
        'status'  => 'active',
        'role'    => $role,
        'role_locked' => 1,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Approval failed.',
        'error' => $e->getMessage(),
    ]);
    error_log('PMTS ApproveUser Error: ' . $e->getMessage());
}
