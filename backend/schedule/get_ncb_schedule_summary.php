<?php
// ============================================================
// PMTS – GET /schedule/get_ncb_schedule_summary.php
// Returns dashboard summary for schedule tasks of all procurement types.
// Legacy filename kept for frontend compatibility.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../alerts/delay_alert_helper.php';
require_once __DIR__ . '/../procurements/tracking_helper.php';

function pmtsScheduleProcurementColumnExists(PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'procurements' AND COLUMN_NAME = ?"
    );
    $stmt->execute([$column]);
    return (int) $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    requireAuth();
    $pdo = getPDO();
    pmtsEnsureAllowedDelayDaysColumn($pdo);

    $today = date('Y-m-d');
    $upcomingLimit = date('Y-m-d', strtotime('+14 days'));
    $hasFileName = pmtsScheduleProcurementColumnExists($pdo, 'file_name');
    $fileNameSelect = $hasFileName ? 'file_name,' : 'NULL AS file_name,';

    $procStmt = $pdo->query(
        "SELECT id, procurement_id, title, {$fileNameSelect} procurement_type, current_status, category, created_at
         FROM procurements
         ORDER BY created_at DESC"
    );
    $procurements = $procStmt->fetchAll(PDO::FETCH_ASSOC);

    $columnStmt = $pdo->query("SHOW COLUMNS FROM procurement_time_schedule LIKE 'sort_order'");
    $hasSortOrder = (bool) $columnStmt->fetch();
    $sortExpr = $hasSortOrder ? 'COALESCE(t.sort_order, t.id)' : 't.id';

    $taskStmt = $pdo->query(
        "SELECT t.id AS task_id,
                t.procurement_id,
                t.task_name,
                t.planned_date,
                t.allowed_delay_days,
                DATE_ADD(t.planned_date, INTERVAL COALESCE(t.allowed_delay_days, 0) DAY) AS allowed_deadline_date,
                t.actual_date,
                t.status,
                t.remarks,
                {$sortExpr} AS sort_order
         FROM procurement_time_schedule t
         INNER JOIN procurements p ON p.id = t.procurement_id
         ORDER BY t.procurement_id ASC, {$sortExpr} ASC, t.id ASC"
    );
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

    $tasksByProcurement = [];
    foreach ($tasks as $task) {
        $pid = (int) $task['procurement_id'];
        if (!isset($tasksByProcurement[$pid])) {
            $tasksByProcurement[$pid] = [];
        }
        $tasksByProcurement[$pid][] = $task;
    }

    $summary = [];
    $totalTaskCount = 0;
    $overdueTaskCount = 0;
    $upcomingTaskCount = 0;

    foreach ($procurements as $proc) {
        $pid = (int) $proc['id'];
        $procTasks = $tasksByProcurement[$pid] ?? [];
        $taskCount = count($procTasks);
        $completedCount = 0;
        $skippedCount = 0;
        $overdueCount = 0;
        $upcomingCount = 0;
        $yellowCount = 0;
        $redCount = 0;
        $delayedCount = 0;
        $nextTask = null;

        foreach ($procTasks as $task) {
            $status = $task['status'] ?? 'pending';
            $plannedDate = $task['planned_date'] ?? null;
            $actualDate = $task['actual_date'] ?? null;
$delayInfoAny = pmtsScheduleDelayInfo($plannedDate, $actualDate, $status, $today, (int) ($task['allowed_delay_days'] ?? 0));
            if ($delayInfoAny) {
                $delayedCount++;
                if ($delayInfoAny['alert_color'] === 'red') {
                    $redCount++;
                } elseif ($delayInfoAny['alert_color'] === 'yellow') {
                    $yellowCount++;
                }
            }

            $isClosed = in_array($status, ['completed', 'skipped'], true) || !empty($actualDate);

            if ($status === 'completed') {
                $completedCount++;
            }

            if ($status === 'skipped') {
                $skippedCount++;
            }

            if (!$isClosed && $plannedDate) {
                $deadlineDate = $task['allowed_deadline_date'] ?? $plannedDate;
                if ($deadlineDate < $today) {
                    $overdueCount++;
                }

                if ($deadlineDate >= $today && $deadlineDate <= $upcomingLimit) {
                    $upcomingCount++;
                    if ($nextTask === null || $plannedDate < $nextTask['planned_date']) {
                        $nextTask = [
                            'task_id' => (int) $task['task_id'],
                            'task_name' => $task['task_name'],
                            'planned_date' => $plannedDate,
                            'status' => $status,
                        ];
                    }
                }
            }
        }

        $applicableTaskCount = max($taskCount - $skippedCount, 0);

        $totalTaskCount += $taskCount;
        $overdueTaskCount += $overdueCount;
        $upcomingTaskCount += $upcomingCount;

        $tracking = pmtsEnrichProcurementTracking($proc, $procTasks);

        $summary[] = [
            'id' => $pid,
            'procurement_id' => $proc['procurement_id'],
            'title' => $proc['title'],
            'file_name' => $proc['file_name'] ?? $proc['title'],
            'category' => $proc['category'],
            'status' => $proc['current_status'],
            'current_stage_key' => $tracking['current_stage_key'] ?? null,
            'current_stage_label' => $tracking['current_stage_label'] ?? null,
            'current_location' => $tracking['current_location'] ?? null,
            'current_task' => $tracking['current_task'] ?? null,
            'current_task_label' => $tracking['current_task_label'] ?? null,
            'created_at' => $proc['created_at'],
            'task_count' => $taskCount,
            'completed_count' => $completedCount,
            'skipped_count' => $skippedCount,
            'applicable_task_count' => $applicableTaskCount,
            'overdue_count' => $overdueCount,
            'upcoming_count' => $upcomingCount,
            'delayed_count' => $delayedCount,
            'yellow_alert_count' => $yellowCount,
            'red_alert_count' => $redCount,
            'progress' => $applicableTaskCount > 0 ? round(($completedCount / $applicableTaskCount) * 100) : 0,
            'next_task' => $nextTask,
        ];
    }

    echo json_encode([
        'success' => true,
        'today' => $today,
        'upcoming_until' => $upcomingLimit,
        'counts' => [
            'scheduled_procurements' => count($procurements),
            'schedule_tasks' => $totalTaskCount,
            'overdue_tasks' => $overdueTaskCount,
            'upcoming_tasks' => $upcomingTaskCount,
            'yellow_alerts' => array_sum(array_column($summary, 'yellow_alert_count')),
            'red_alerts' => array_sum(array_column($summary, 'red_alert_count')),
        ],
        'summary' => $summary,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch schedule summary.']);
    error_log('PMTS ScheduleSummary Error: ' . $e->getMessage());
}
