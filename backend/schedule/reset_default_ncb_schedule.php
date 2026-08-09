<?php
// ============================================================
//  PMTS – POST /schedule/reset_default_ncb_schedule.php
//  Replace current rows with the standard procurement schedule tasks.
//  Legacy endpoint name kept.
//  Body: { "procurement_id": 5 }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/ncb_tasks.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['procurement_officer', 'it_admin']);
    $input = json_decode(file_get_contents('php://input'), true);
    $procId = (int) ($input['procurement_id'] ?? 0);

    if (!$procId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id is required.']);
        exit;
    }

    $pdo = getPDO();

    $procStmt = $pdo->prepare("SELECT id, procurement_id, procurement_type FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procId]);
    $proc = $procStmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM procurement_time_schedule WHERE procurement_id = ?")->execute([$procId]);
    insertDefaultProcurementScheduleTasks($pdo, $procId);
    $pdo->commit();

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'RESET_PROCUREMENT_SCHEDULE',
        'schedule',
        "Reset procurement schedule tasks for {$proc['procurement_id']}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Procurement schedule reset successfully.',
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to reset procurement schedule.']);
    error_log("PMTS ResetSchedule Error: " . $e->getMessage());
}
