<?php
// ============================================================
//  PMTS – POST /schedule/delete_task_file_tracking.php
//  Deletes one file tracking row from a schedule task.
//  Body: { id, procurement_id, task_id }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/task_file_tracking_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['procurement_officer', 'it_admin']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $id = (int) ($input['id'] ?? 0);
    $procurementId = (int) ($input['procurement_id'] ?? 0);
    $taskId = (int) ($input['task_id'] ?? 0);

    if (!$id || !$procurementId || !$taskId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'id, procurement_id and task_id are required.']);
        exit;
    }

    $pdo = getPDO();
    pmtsEnsureTaskFileTrackingTable($pdo);

    $task = pmtsValidateTaskBelongsToProcurement($pdo, $procurementId, $taskId);
    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Schedule task not found for this procurement.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM ncb_task_file_tracking
         WHERE id = ? AND procurement_id = ? AND schedule_task_id = ?"
    );
    $stmt->execute([$id, $procurementId, $taskId]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'DELETE_TASK_FILE_TRACKING',
        'ncb_task_file_tracking',
        "Deleted file tracking row {$id} for {$task['public_id']} task {$taskId}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Task file tracking removed successfully.',
        'data' => pmtsGetTaskFileTrackingRows($pdo, $procurementId, $taskId),
        'summary' => pmtsGetTaskFileTrackingSummary($pdo, $taskId),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to remove task file tracking.']);
    error_log('PMTS DeleteTaskFileTracking Error: ' . $e->getMessage());
}
