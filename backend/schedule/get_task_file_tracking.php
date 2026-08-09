<?php
// ============================================================
//  PMTS – GET /schedule/get_task_file_tracking.php
//  Returns file tracking rows for one NCB/procurement schedule task.
//  Query: ?procurement_id=5&task_id=10
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/task_file_tracking_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    requireAuth();

    $procurementId = (int) ($_GET['procurement_id'] ?? 0);
    $taskId = (int) ($_GET['task_id'] ?? 0);

    if (!$procurementId || !$taskId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id and task_id are required.']);
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

    $rows = pmtsGetTaskFileTrackingRows($pdo, $procurementId, $taskId);

    echo json_encode([
        'success' => true,
        'task' => $task,
        'file_types' => pmtsTaskFileTypeOptions(),
        'data' => $rows,
        'summary' => pmtsGetTaskFileTrackingSummary($pdo, $taskId),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load task file tracking.']);
    error_log('PMTS GetTaskFileTracking Error: ' . $e->getMessage());
}
