<?php
// ============================================================
//  PMTS – POST /users/assign_role.php
//  Assign the FIRST role for a pending user only (IT Admin only)
//  Body: { "user_id": 5, "role": "accountant" }
//  Important: after the role is assigned, it is locked permanently.
//  No IT Admin or other user can change/remove it later.
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
    $input    = json_decode(file_get_contents('php://input'), true) ?: [];

    $userId  = (int) ($input['user_id'] ?? 0);
    $newRole = trim($input['role'] ?? '');

    $validRoles = [
        'director',
        'accountant',
        'procurement_officer',
        'tec_member',
        'bec_member',
        'specification_committee',
        'it_admin',
    ];

    if (!$userId || !$newRole) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'user_id and role are required.']);
        exit;
    }

    if (!in_array($newRole, $validRoles, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid role.']);
        exit;
    }

    if ($userId === (int) $authUser['sub']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You cannot change your own IT Admin role.']);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, full_name, email, role, status, role_locked FROM users WHERE id = ? LIMIT 1");
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
        echo json_encode(['success' => false, 'message' => 'This user is removed/rejected. A fixed role cannot be assigned from here.']);
        exit;
    }

    if (($user['status'] ?? 'pending') !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only pending users can receive their first fixed role.']);
        exit;
    }

    $oldRole = $user['role'] ?: 'pending';

    $pdo->prepare("UPDATE users SET role = ?, status = 'active', role_locked = 1 WHERE id = ? AND role_locked = 0 AND role = 'pending'")
        ->execute([$newRole, $userId]);

    if ($pdo->lastInsertId() === '0') {
        // lastInsertId is not reliable for UPDATE; verify the final user state.
    }

    $verify = $pdo->prepare("SELECT role, status, role_locked FROM users WHERE id = ? LIMIT 1");
    $verify->execute([$userId]);
    $updated = $verify->fetch(PDO::FETCH_ASSOC);

    if (!$updated || $updated['role'] !== $newRole || $updated['status'] !== 'active' || (int) $updated['role_locked'] !== 1) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Role was not assigned because the user role is already locked.']);
        exit;
    }

    $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES (?, 'Role Assigned', ?, 'success')"
    )->execute([$userId, "Your PMTS role has been assigned as: $newRole. This role is now fixed and your account is active."]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'ASSIGN_FIXED_ROLE',
        'users',
        "Assigned fixed role for user ID $userId ({$user['email']}): $oldRole → $newRole; role locked permanently"
    );

    echo json_encode([
        'success' => true,
        'message' => "Role assigned as '$newRole' and locked permanently. User account is active.",
        'status'  => 'active',
        'role'    => $newRole,
        'role_locked' => 1,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to assign role.', 'error' => $e->getMessage()]);
    error_log("PMTS AssignRole Error: " . $e->getMessage());
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to assign role.', 'error' => $e->getMessage()]);
    error_log("PMTS AssignRole Throwable: " . $e->getMessage());
}
