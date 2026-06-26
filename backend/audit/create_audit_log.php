<?php
// ============================================================
//  PMTS – POST /audit/create_audit_log.php
//  Manually create an audit log entry from frontend/external
//  Typically called internally; exposed for flexibility.
//  Body: { "action": "VIEW_REPORT", "module": "reports", "description": "..." }
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
    $authUser = requireAuth();
    $input    = json_decode(file_get_contents('php://input'), true);

    $action      = strtoupper(trim($input['action']      ?? ''));
    $module      = trim($input['module']      ?? '');
    $description = trim($input['description'] ?? '');

    if (!$action || !$module) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'action and module are required.']);
        exit;
    }

    $pdo = getPDO();

    createAuditLog($pdo, (int) $authUser['sub'], $action, $module, $description);

    echo json_encode([
        'success' => true,
        'message' => 'Audit log created.',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create audit log.']);
    error_log("PMTS CreateAuditLog Error: " . $e->getMessage());
}
