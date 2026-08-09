<?php
// ============================================================
// PMTS – POST /schedule/update_current_task.php
// Moves a procurement to one schedule task at a time.
// Body: { procurement_id: 5, schedule_task_id: 12 } OR
//       { procurement_id: 5, sort_order: 9 }
// Previous non-skipped tasks become completed, the selected task
// becomes in_progress, and later non-skipped tasks become pending.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../config/notification_helper.php';
require_once __DIR__ . '/ncb_tasks.php';
require_once __DIR__ . '/../procurements/tracking_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

function pmtsCanMoveCurrentTask(array $authUser, array $stage): bool
{
    $role = $authUser['role'] ?? '';
    if (in_array($role, ['procurement_officer', 'it_admin'], true)) {
        return true;
    }

    $stageRoles = [
        'specification_committee' => ['specification_committee'],
        'bec' => ['bec_member'],
        'accountant' => ['accountant'],
        'procurement_officer' => ['procurement_officer'],
        'bid_opening_committee' => ['procurement_officer'],
        'procurement_committee' => ['procurement_officer'],
        'purchase_order' => ['procurement_officer', 'accountant'],
    ];

    return in_array($role, $stageRoles[$stage['key'] ?? ''] ?? [], true);
}

try {
    $authUser = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $procurementId = (int) ($input['procurement_id'] ?? 0);
    $scheduleTaskId = (int) ($input['schedule_task_id'] ?? $input['task_id'] ?? 0);
    $sortOrder = (int) ($input['sort_order'] ?? 0);
    $remarks = trim((string) ($input['remarks'] ?? ''));

    if (!$procurementId || (!$scheduleTaskId && !$sortOrder)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id and schedule_task_id or sort_order are required.']);
        exit;
    }

    $pdo = getPDO();

    $procStmt = $pdo->prepare("SELECT id, procurement_id, title, current_status FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procurementId]);
    $procurement = $procStmt->fetch(PDO::FETCH_ASSOC);

    if (!$procurement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM procurement_time_schedule WHERE procurement_id = ?");
    $countStmt->execute([$procurementId]);
    if ((int) $countStmt->fetchColumn() === 0) {
        insertDefaultProcurementScheduleTasks($pdo, $procurementId);
    }

    $columnStmt = $pdo->query("SHOW COLUMNS FROM procurement_time_schedule LIKE 'sort_order'");
    $hasSortOrder = (bool) $columnStmt->fetch();
    $sortExpr = $hasSortOrder ? 'COALESCE(sort_order, id)' : 'id';

    $targetSql = $scheduleTaskId
        ? "SELECT *, {$sortExpr} AS effective_sort_order FROM procurement_time_schedule WHERE id = ? AND procurement_id = ? LIMIT 1"
        : "SELECT *, {$sortExpr} AS effective_sort_order FROM procurement_time_schedule WHERE procurement_id = ? AND {$sortExpr} = ? ORDER BY id ASC LIMIT 1";
    $targetStmt = $pdo->prepare($targetSql);
    $targetStmt->execute($scheduleTaskId ? [$scheduleTaskId, $procurementId] : [$procurementId, $sortOrder]);
    $targetTask = $targetStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetTask) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Schedule task not found for this procurement.']);
        exit;
    }

    $targetTask['sort_order'] = (int) ($targetTask['effective_sort_order'] ?? $targetTask['sort_order'] ?? $targetTask['id']);
    $targetTask['responsible_role'] = getResponsibleRoleForTask($targetTask['task_name']);
    $targetStage = pmtsScheduleTaskLocationForTask($targetTask);

    if (!pmtsCanMoveCurrentTask($authUser, $targetStage)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Your role cannot move this procurement to the selected task.']);
        exit;
    }

    $pdo->beginTransaction();

    // Mark the schedule position without deleting any planned/actual dates.
    $lowerStmt = $pdo->prepare(
        "UPDATE procurement_time_schedule
         SET status = 'completed'
         WHERE procurement_id = ? AND {$sortExpr} < ? AND status <> 'skipped'"
    );
    $lowerStmt->execute([$procurementId, $targetTask['sort_order']]);

    $currentStmt = $pdo->prepare(
        "UPDATE procurement_time_schedule
         SET status = 'in_progress', remarks = CASE WHEN ? = '' THEN remarks ELSE ? END
         WHERE id = ? AND procurement_id = ?"
    );
    $currentStmt->execute([$remarks, $remarks, (int) $targetTask['id'], $procurementId]);

    $higherStmt = $pdo->prepare(
        "UPDATE procurement_time_schedule
         SET status = 'pending'
         WHERE procurement_id = ? AND {$sortExpr} > ? AND status <> 'skipped'"
    );
    $higherStmt->execute([$procurementId, $targetTask['sort_order']]);

    $newStatus = $targetStage['status'] ?? $procurement['current_status'];
    $oldStatus = $procurement['current_status'] ?? 'draft';
    if ($newStatus && $newStatus !== $oldStatus) {
        $pdo->prepare("UPDATE procurements SET current_status = ? WHERE id = ?")->execute([$newStatus, $procurementId]);
        $pdo->prepare(
            "INSERT INTO status_history (procurement_id, old_status, new_status, changed_by, remarks)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([
            $procurementId,
            $oldStatus,
            $newStatus,
            $authUser['sub'],
            $remarks ?: "Current task moved to {$targetStage['task_label']} ({$targetStage['label']}).",
        ]);
    }

    $pdo->commit();

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_CURRENT_SCHEDULE_TASK',
        'procurement_time_schedule',
        "Current task for {$procurement['procurement_id']} moved to {$targetStage['task_label']}"
    );

    $title = 'Procurement Current Task Updated';
    $message = "{$procurement['procurement_id']} - {$procurement['title']} is now at {$targetStage['label']} ({$targetStage['task_label']}).";
    pmtsNotifyRole($pdo, 'director', $title, $message, 'status_update');
    pmtsNotifyRoles($pdo, pmtsResponsibleRolesForStatus($newStatus), $title, $message, 'status_update');

    $tasksByProcurement = pmtsLoadScheduleTasksForProcurements($pdo, [$procurementId]);
    $procStmt->execute([$procurementId]);
    $updatedProcurement = pmtsEnrichProcurementTracking(
        $procStmt->fetch(PDO::FETCH_ASSOC),
        $tasksByProcurement[$procurementId] ?? []
    );

    echo json_encode([
        'success' => true,
        'message' => "Current task updated. File is now under {$targetStage['label']}.",
        'current_stage' => $targetStage,
        'procurement' => $updatedProcurement,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update current schedule task.']);
    error_log('PMTS UpdateCurrentTask Error: ' . $e->getMessage());
}
