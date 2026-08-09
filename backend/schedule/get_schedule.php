<?php
// ============================================================
//  PMTS – GET /schedule/get_schedule.php
//  Get procurement time schedule tasks for any procurement type.
//  Query: ?procurement_id=5
//  If a procurement has no schedule rows, this file creates
//  the default schedule tasks automatically.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/ncb_tasks.php';
require_once __DIR__ . '/../alerts/delay_alert_helper.php';
require_once __DIR__ . '/task_file_tracking_helper.php';
require_once __DIR__ . '/../procurements/tracking_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    requireAuth();

    $procId = (int) ($_GET['procurement_id'] ?? 0);
    if (!$procId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id is required.']);
        exit;
    }

    $pdo = getPDO();
    pmtsEnsureAllowedDelayDaysColumn($pdo);

    $procStmt = $pdo->prepare("SELECT * FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procId]);
    $proc = $procStmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM procurement_time_schedule WHERE procurement_id = ?");
    $countStmt->execute([$procId]);
    $taskCount = (int) $countStmt->fetchColumn();

    if ($taskCount === 0) {
        insertDefaultProcurementScheduleTasks($pdo, $procId);
    }

    $columnStmt = $pdo->query("SHOW COLUMNS FROM procurement_time_schedule LIKE 'sort_order'");
    $orderBy = $columnStmt->fetch() ? 'COALESCE(sort_order, id) ASC, id ASC' : 'id ASC';

    $stmt = $pdo->prepare(
        "SELECT * FROM procurement_time_schedule
         WHERE procurement_id = ?
         ORDER BY {$orderBy}"
    );
    $stmt->execute([$procId]);
    $tasks = $stmt->fetchAll();
    $trackingSummaries = pmtsGetTaskFileTrackingSummaries($pdo, $procId);

    $today = date('Y-m-d');
    foreach ($tasks as &$task) {
        $task['responsible_role'] = getResponsibleRoleForTask($task['task_name']);
        $task['file_tracking_summary'] = $trackingSummaries[(int) $task['id']] ?? pmtsEmptyTaskFileTrackingSummary();
        $task['delay_info'] = pmtsScheduleDelayInfo(
            $task['planned_date'] ?? null,
            $task['actual_date'] ?? null,
            $task['status'] ?? 'pending',
            $today,
            (int) ($task['allowed_delay_days'] ?? 0)
        );
    }
    unset($task);

    $proc = pmtsEnrichProcurementTracking($proc, $tasks);
    $currentTask = $proc['current_task'] ?? null;

    $total     = count($tasks);
    $completed = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
    $skipped   = count(array_filter($tasks, fn($t) => $t['status'] === 'skipped'));
    $delayed   = count(array_filter($tasks, fn($t) => $t['status'] === 'delayed' || !empty($t['delay_info'])));
    $applicable = max($total - $skipped, 0);
    $progress  = $applicable > 0 ? round(($completed / $applicable) * 100) : 0;
    $trackedFiles = array_reduce($tasks, fn($sum, $t) => $sum + (int) (($t['file_tracking_summary']['total_files'] ?? 0)), 0);
    $completedFiles = array_reduce($tasks, fn($sum, $t) => $sum + (int) (($t['file_tracking_summary']['completed_files'] ?? 0)), 0);
    $pendingFiles = array_reduce($tasks, fn($sum, $t) => $sum + (int) (($t['file_tracking_summary']['pending_files'] ?? 0)), 0);

    echo json_encode([
        'success' => true,
        'procurement' => $proc,
        'data' => $tasks,
        'stats' => [
            'total' => $total,
            'completed' => $completed,
            'delayed' => $delayed,
            'yellow_alerts' => count(array_filter($tasks, fn($t) => !empty($t['delay_info']) && $t['delay_info']['alert_color'] === 'yellow')),
            'red_alerts' => count(array_filter($tasks, fn($t) => !empty($t['delay_info']) && $t['delay_info']['alert_color'] === 'red')),
            'skipped' => $skipped,
            'applicable' => $applicable,
            'progress' => $progress,
            'tracked_files' => $trackedFiles,
            'completed_files' => $completedFiles,
            'pending_files' => $pendingFiles,
            'current_location' => $proc['current_stage_label'] ?? $proc['current_location'] ?? null,
            'current_task' => $currentTask,
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch schedule.']);
    error_log("PMTS GetSchedule Error: " . $e->getMessage());
}
