<?php
// ============================================================
// PMTS – POST /schedule/update_schedule.php
// Update one procurement schedule task dates/status/remarks.
// Body: { "task_id": 1, "planned_date": "2026-06-01", ... }
// Roles: procurement_officer, it_admin
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/schedule_schema_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

function cleanDateValue($value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : substr($value, 0, 10);
}

try {
    $authUser = requireRole(['procurement_officer', 'it_admin']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $taskId = (int) ($input['task_id'] ?? 0);

    if (!$taskId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'task_id is required.']);
        exit;
    }

    $validStatuses = ['pending', 'in_progress', 'completed', 'delayed', 'skipped'];
    $status = $input['status'] ?? 'pending';
    if (!in_array($status, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid schedule status.']);
        exit;
    }

    $pdo = getPDO();
    pmtsEnsureAllowedDelayDaysColumn($pdo);

    $taskStmt = $pdo->prepare(
        "SELECT s.id, s.procurement_id, s.allowed_delay_days, p.procurement_id AS public_id, p.procurement_type
         FROM procurement_time_schedule s
         INNER JOIN procurements p ON p.id = s.procurement_id
         WHERE s.id = ? LIMIT 1"
    );
    $taskStmt->execute([$taskId]);
    $task = $taskStmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Schedule task not found.']);
        exit;
    }

    $plannedDate = cleanDateValue($input['planned_date'] ?? null);
    $actualDate = cleanDateValue($input['actual_date'] ?? null);
    // Allowed delay days are configured globally in IT Admin > System Settings.
    // Editing an individual schedule row must not change that global/default delay rule.
    $allowedDelayDays = (int) ($task['allowed_delay_days'] ?? 0);

    if ($status !== 'skipped' && $plannedDate && $actualDate && pmtsScheduleDaysLateBeyondAllowed($plannedDate, $actualDate, $allowedDelayDays) > 0) {
        $status = 'delayed';
    }

    $stmt = $pdo->prepare(
        "UPDATE procurement_time_schedule
         SET planned_date = ?, allowed_delay_days = ?, actual_date = ?, status = ?, remarks = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $plannedDate,
        $allowedDelayDays,
        $actualDate,
        $status,
        trim($input['remarks'] ?? ''),
        $taskId,
    ]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_SCHEDULE_TASK',
        'procurement_time_schedule',
        "Updated schedule task {$taskId} for {$task['public_id']}"
    );

    echo json_encode(['success' => true, 'message' => 'Schedule task updated successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update schedule task.']);
    error_log('PMTS UpdateSchedule Error: ' . $e->getMessage());
}
