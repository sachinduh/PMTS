<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../schedule/ncb_tasks.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['it_admin']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $taskName = trim((string) ($input['task_name'] ?? ''));
    if ($taskName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select a schedule task.']);
        exit;
    }

    $pdo = getPDO();
    $updated = pmtsSaveTaskDelaySetting(
        $pdo,
        getDefaultProcurementScheduleTasks(),
        $taskName,
        $input['allowed_delay_days'] ?? 0
    );

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_TASK_DELAY_SETTING',
        'schedule_task_delay_settings',
        "Set allowed delay for {$updated['task_name']} to {$updated['allowed_delay_days']} day(s)."
    );

    echo json_encode([
        'success' => true,
        'message' => 'Task allowed-delay setting saved successfully.',
        'task' => $updated,
    ]);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode([
        'success' => false,
        'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Failed to save schedule task delay setting.',
    ]);
}
