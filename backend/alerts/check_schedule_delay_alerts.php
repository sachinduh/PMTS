<?php
// ============================================================
// PMTS – Daily Schedule Delay Alert Check
// GET/POST /alerts/check_schedule_delay_alerts.php
// Web use: Director / IT Admin / Procurement Officer can run it.
// Automatic use: Windows Task Scheduler can call this file by CLI.
// Optional web cron key: ?cron_key=PMTS_DELAY_CHECK_2026
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/delay_alert_helper.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET or POST.']);
    exit;
}

try {
    $pdo = getPDO();
    $actorUserId = null;

    $isCli = php_sapi_name() === 'cli';
    $cronKey = $_GET['cron_key'] ?? '';
    $validCronKey = $cronKey === 'PMTS_DELAY_CHECK_2026';

    if (!$isCli && !$validCronKey) {
        $authUser = requireRole(['director', 'it_admin', 'procurement_officer']);
        $actorUserId = (int) ($authUser['sub'] ?? 0);
    }

    $result = pmtsRunScheduleDelayCheck($pdo, $actorUserId, true);

    if ($actorUserId) {
        createAuditLog(
            $pdo,
            $actorUserId,
            'DAILY_DELAY_CHECK',
            'alerts',
            "Daily schedule delay check: {$result['alerts_created']} alert(s), {$result['notifications_created']} notification(s)."
        );
    }

    echo json_encode($result);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to run schedule delay check.']);
    error_log('PMTS DailyDelayCheck Error: ' . $e->getMessage());
}
