<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

try {
    requireRole(['it_admin']);

    $pdo = getPDO();
    ensureAccountSecurityColumns($pdo);

    $columnStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'requested_role'");
    $columnStmt->execute();
    $hasRequestedRole = (int) $columnStmt->fetchColumn() > 0;
    $requestedRoleSelect = $hasRequestedRole ? "requested_role," : "NULL AS requested_role,";

    $status = $_GET['status'] ?? '';

    $selectSql = "
        SELECT 
            id,
            full_name,
            email,
            phone,
            nic,
            user_type,
            department,
            organization,
            role,
            $requestedRoleSelect
            role_locked,
            status,
            failed_login_attempts,
            last_failed_login_at,
            account_locked,
            locked_at,
            locked_reason,
            unlocked_by,
            unlocked_at,
            created_at
        FROM users
    ";

    if ($status === 'locked') {
        $stmt = $pdo->prepare($selectSql . " WHERE account_locked = 1 ORDER BY id DESC");
        $stmt->execute();
    } elseif ($status === 'active') {
        $stmt = $pdo->prepare($selectSql . " WHERE status = 'active' AND account_locked = 0 ORDER BY id DESC");
        $stmt->execute();
    } elseif (!empty($status)) {
        $stmt = $pdo->prepare($selectSql . " WHERE status = ? ORDER BY id DESC");
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->prepare($selectSql . " ORDER BY id DESC");
        $stmt->execute();
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$user) {
        $user['id'] = (int) $user['id'];
        $user['role_locked'] = (int) ($user['role_locked'] ?? 0);
        $user['failed_login_attempts'] = (int) ($user['failed_login_attempts'] ?? 0);
        $user['account_locked'] = (int) ($user['account_locked'] ?? 0);
    }
    unset($user);

    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to load users.',
        'error' => $e->getMessage()
    ]);
}
