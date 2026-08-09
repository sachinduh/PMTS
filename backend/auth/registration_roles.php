<?php
// ============================================================
// PMTS – Public registration role options
// Returns IT Admin only when no active role-locked IT Admin exists.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../queries/user_queries.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $pdo = getPDO();
    pmtsEnsureRegistrationRoleColumns($pdo);
    pmtsEnsureAccountSecurityColumns($pdo);

    $activeItAdminCount = pmtsActiveLockedItAdminCount($pdo);

    echo json_encode([
        'success' => true,
        'roles' => pmtsRegistrationRoles($pdo),
        'it_admin_available' => $activeItAdminCount === 0,
        'active_it_admin_count' => $activeItAdminCount,
        'database' => DB_NAME,
        'message' => $activeItAdminCount === 0
            ? 'IT Admin registration is available because no IT Admin exists yet.'
            : 'IT Admin registration is hidden because an IT Admin already exists.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load registration roles.',
        'error' => $e->getMessage(),
    ]);
}
