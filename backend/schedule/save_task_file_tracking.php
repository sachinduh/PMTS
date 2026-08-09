<?php
// ============================================================
//  PMTS – POST /schedule/save_task_file_tracking.php
//  Creates/updates file tracking counts for one schedule task + type.
//  Body: { procurement_id, task_id, file_type, total_files, completed_files, remarks, id? }
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
    $fileType = pmtsNormalizeTaskFileType($input['file_type'] ?? '');
    $totalFiles = max(0, (int) ($input['total_files'] ?? 0));
    $completedFiles = max(0, (int) ($input['completed_files'] ?? 0));
    $remarks = trim((string) ($input['remarks'] ?? ''));

    if (!$procurementId || !$taskId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id and task_id are required.']);
        exit;
    }

    if (!$fileType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select a valid file type.']);
        exit;
    }

    if ($completedFiles > $totalFiles) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Completed files cannot be greater than total files.']);
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

    if ($id > 0) {
        $stmt = $pdo->prepare(
            "UPDATE ncb_task_file_tracking
             SET file_type = ?, total_files = ?, completed_files = ?, remarks = ?
             WHERE id = ? AND procurement_id = ? AND schedule_task_id = ?"
        );
        $stmt->execute([$fileType, $totalFiles, $completedFiles, $remarks, $id, $procurementId, $taskId]);
        $trackingId = $id;
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO ncb_task_file_tracking
                (procurement_id, schedule_task_id, file_type, total_files, completed_files, remarks)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                total_files = VALUES(total_files),
                completed_files = VALUES(completed_files),
                remarks = VALUES(remarks),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$procurementId, $taskId, $fileType, $totalFiles, $completedFiles, $remarks]);
        $trackingId = (int) ($pdo->lastInsertId() ?: 0);
    }

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'SAVE_TASK_FILE_TRACKING',
        'ncb_task_file_tracking',
        "Saved file tracking for {$task['public_id']} task {$taskId}: {$fileType} ({$completedFiles}/{$totalFiles})"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Task file tracking saved successfully.',
        'id' => $trackingId,
        'data' => pmtsGetTaskFileTrackingRows($pdo, $procurementId, $taskId),
        'summary' => pmtsGetTaskFileTrackingSummary($pdo, $taskId),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save task file tracking.']);
    error_log('PMTS SaveTaskFileTracking Error: ' . $e->getMessage());
}
