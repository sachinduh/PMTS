<?php
// ============================================================
// PMTS – POST /schedule/create_ncb_schedule.php
// Add one extra procurement schedule task. Legacy endpoint name kept.
// Body: { "procurement_id": 5, "task_name": "Extra task" }
// Roles: procurement_officer, it_admin
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

function cleanDateValue($value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : substr($value, 0, 10);
}

try {
    $authUser = requireRole(['procurement_officer', 'it_admin']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $procId = (int) ($input['procurement_id'] ?? 0);
    $taskName = trim($input['task_name'] ?? '');

    if (!$procId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id is required.']);
        exit;
    }

    if ($taskName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Task name is required.']);
        exit;
    }

    $validStatuses = ['pending', 'in_progress', 'completed', 'delayed', 'skipped'];
    $status = $input['status'] ?? 'pending';
    if (!in_array($status, $validStatuses, true)) {
        $status = 'pending';
    }

    $pdo = getPDO();
    pmtsEnsureAllowedDelayDaysColumn($pdo);
    $procStmt = $pdo->prepare("SELECT id, procurement_id, procurement_type FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procId]);
    $proc = $procStmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $columnStmt = $pdo->query("SHOW COLUMNS FROM procurement_time_schedule LIKE 'sort_order'");
    $hasSortOrder = (bool) $columnStmt->fetch();
    $delayMap = pmtsGetTaskDelaySettingsMap($pdo, getDefaultProcurementScheduleTasks());
    $allowedDelayDays = $delayMap[$taskName] ?? 0;

    if ($hasSortOrder) {
        $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM procurement_time_schedule WHERE procurement_id = ?");
        $orderStmt->execute([$procId]);
        $sortOrder = (int) $orderStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "INSERT INTO procurement_time_schedule (procurement_id, task_name, planned_date, allowed_delay_days, actual_date, status, remarks, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $procId,
            $taskName,
            cleanDateValue($input['planned_date'] ?? null),
            $allowedDelayDays,
            cleanDateValue($input['actual_date'] ?? null),
            $status,
            trim($input['remarks'] ?? ''),
            $sortOrder,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO procurement_time_schedule (procurement_id, task_name, planned_date, allowed_delay_days, actual_date, status, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $procId,
            $taskName,
            cleanDateValue($input['planned_date'] ?? null),
            $allowedDelayDays,
            cleanDateValue($input['actual_date'] ?? null),
            $status,
            trim($input['remarks'] ?? ''),
        ]);
    }

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'CREATE_SCHEDULE_TASK',
        'procurement_time_schedule',
        "Added extra schedule task for {$proc['procurement_id']}: {$taskName}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Schedule task added successfully.',
        'id' => (int) $pdo->lastInsertId(),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add schedule task.']);
    error_log('PMTS CreateNcbSchedule Error: ' . $e->getMessage());
}
