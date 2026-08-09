<?php
// ============================================================
// PMTS – POST /alerts/generate_delay_alerts.php
// Compatibility wrapper for the new daily schedule delay check.
// Roles: director, it_admin, procurement_officer
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/delay_alert_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['director', 'it_admin', 'procurement_officer']);
    $pdo = getPDO();

    $result = pmtsRunScheduleDelayCheck($pdo, (int) $authUser['sub'], true);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'GENERATE_ALERTS',
        'alerts',
        "Generated {$result['alerts_created']} schedule delay alert(s)."
    );

    echo json_encode($result);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to generate delay alerts.']);
    error_log('PMTS GenerateAlerts Error: ' . $e->getMessage());
}
