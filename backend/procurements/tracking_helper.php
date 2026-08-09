<?php
// ============================================================
// PMTS procurement tracking helper
// Converts workflow status and NCB schedule task progress into
// human-readable current procurement location.
// ============================================================

function pmtsStringContains(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

function pmtsWorkflowStages(): array
{
    return [
        [
            'key' => 'procurement_officer',
            'label' => 'Procurement Officer',
            'status' => 'draft',
            'statuses' => ['draft', 'submitted', 'under_review'],
            'icon' => 'procurement',
            'description' => 'Procurement created and basic details are being prepared by the Procurement Officer.',
        ],
        [
            'key' => 'specification_committee',
            'label' => 'Specification Committee',
            'status' => 'specification_approval',
            'statuses' => ['specification_approval'],
            'icon' => 'document',
            'description' => 'Specification Committee is preparing or reviewing the procurement specification.',
        ],
        [
            'key' => 'tender_preparation',
            'label' => 'Tender Preparation / Calling',
            'status' => 'tender_preparation',
            'statuses' => ['tender_preparation', 'advertised', 'bid_received'],
            'icon' => 'announcement',
            'description' => 'Tender documents, calling, advertising, bid closing, and bid opening activities are in progress.',
        ],
        [
            'key' => 'bec',
            'label' => 'BEC',
            'status' => 'bid_evaluation',
            'statuses' => ['bid_evaluation'],
            'icon' => 'document',
            'description' => 'Bid Evaluation Committee is handling bid evaluation and recommendation.',
        ],
        [
            'key' => 'accountant',
            'label' => 'Accountant / Financial Review',
            'status' => 'financial_evaluation',
            'statuses' => ['financial_evaluation'],
            'icon' => 'finance',
            'description' => 'Accountant is checking financial approval/payment related details.',
        ],
        [
            'key' => 'purchase_order',
            'label' => 'Purchase Order / Award',
            'status' => 'purchase_order_issued',
            'statuses' => ['awarded', 'purchase_order_issued', 'contract_signed'],
            'icon' => 'purchase',
            'description' => 'Award, purchase order, or contract related activities are in progress.',
        ],
        [
            'key' => 'completed',
            'label' => 'Completed',
            'status' => 'completed',
            'statuses' => ['completed'],
            'icon' => 'completed',
            'description' => 'Procurement is completed.',
        ],
    ];
}

function pmtsTrackingStageForStatus(?string $status): array
{
    $status = $status ?: 'draft';

    foreach (pmtsWorkflowStages() as $index => $stage) {
        if (in_array($status, $stage['statuses'], true)) {
            return [
                'key' => $stage['key'],
                'label' => $stage['label'],
                'status' => $status,
                'display_status' => $stage['status'],
                'icon' => $stage['icon'],
                'description' => $stage['description'],
                'step' => $index + 1,
                'total_steps' => count(pmtsWorkflowStages()),
            ];
        }
    }

    if ($status === 'cancelled') {
        return [
            'key' => 'cancelled',
            'label' => 'Cancelled',
            'status' => $status,
            'display_status' => $status,
            'icon' => 'error',
            'description' => 'Procurement has been cancelled.',
            'step' => 0,
            'total_steps' => count(pmtsWorkflowStages()),
        ];
    }

    if ($status === 'on_hold') {
        return [
            'key' => 'on_hold',
            'label' => 'On Hold',
            'status' => $status,
            'display_status' => $status,
            'icon' => 'pending',
            'description' => 'Procurement is currently on hold.',
            'step' => 0,
            'total_steps' => count(pmtsWorkflowStages()),
        ];
    }

    return [
        'key' => 'unknown',
        'label' => 'Unknown Stage',
        'status' => $status,
        'display_status' => $status,
        'icon' => 'help',
        'description' => 'Current tracking stage is not mapped yet.',
        'step' => 0,
        'total_steps' => count(pmtsWorkflowStages()),
    ];
}

function pmtsScheduleTaskLocationForTask(array $task): array
{
    $name = strtolower((string) ($task['task_name'] ?? ''));
    $role = strtolower((string) ($task['responsible_role'] ?? ''));
    $sortOrder = (int) ($task['sort_order'] ?? 0);

    $location = [
        'key' => 'procurement_officer',
        'label' => 'Procurement Officer',
        'status' => 'tender_preparation',
        'icon' => 'procurement',
        'description' => 'Procurement Officer is handling this schedule task.',
    ];

    if ($sortOrder === 1 || $sortOrder === 2 || pmtsStringContains($name, 'specification')) {
        $location = [
            'key' => 'specification_committee',
            'label' => 'Specification Committee',
            'status' => 'specification_approval',
            'icon' => 'document',
            'description' => 'Specification Committee is handling this schedule task.',
        ];
    } elseif ($sortOrder === 3 || $sortOrder === 5 || $sortOrder === 9 || $sortOrder === 10 || pmtsStringContains($name, 'bid evaluation committee') || pmtsStringContains($name, '(bec)') || pmtsStringContains($role, 'bec') || pmtsStringContains($name, 'evaluation committee') || pmtsStringContains($name, 'evaluation report')) {
        $location = [
            'key' => 'bec',
            'label' => 'BEC',
            'status' => 'bid_evaluation',
            'icon' => 'document',
            'description' => 'Bid Evaluation Committee is handling this schedule task.',
        ];
    } elseif ($sortOrder === 8 || pmtsStringContains($name, 'bid / quotation opening') || pmtsStringContains($name, 'bid opening') || pmtsStringContains($role, 'bid opening')) {
        $location = [
            'key' => 'bid_opening_committee',
            'label' => 'Bid Opening Committee',
            'status' => 'bid_received',
            'icon' => 'document',
            'description' => 'Bid Opening Committee is handling this schedule task.',
        ];
    } elseif ($sortOrder === 11 || $sortOrder === 12 || $sortOrder === 14 || pmtsStringContains($name, 'tender decision') || pmtsStringContains($name, 'appeal committee') || pmtsStringContains($name, 'stock received') || pmtsStringContains($role, 'procurement committee')) {
        $location = [
            'key' => 'procurement_committee',
            'label' => 'Procurement Committee',
            'status' => 'under_review',
            'icon' => 'document',
            'description' => 'Procurement Committee is handling this schedule task.',
        ];
    } elseif (pmtsStringContains($name, 'payment') || pmtsStringContains($name, 'invoice') || pmtsStringContains($role, 'accountant') || pmtsStringContains($role, 'financial')) {
        $location = [
            'key' => 'accountant',
            'label' => 'Accountant / Financial Review',
            'status' => 'financial_evaluation',
            'icon' => 'finance',
            'description' => 'Accountant is handling this schedule task.',
        ];
    } elseif (pmtsStringContains($name, 'purchase order') || pmtsStringContains($name, 'letter of award') || pmtsStringContains($name, 'acceptance letter')) {
        $location = [
            'key' => 'purchase_order',
            'label' => 'Purchase Order / Award',
            'status' => 'purchase_order_issued',
            'icon' => 'purchase',
            'description' => 'Purchase order, award, or supplier acceptance activity is in progress.',
        ];
    }

    $sortLabel = $sortOrder > 0 ? "Task {$sortOrder}" : 'Schedule Task';
    $taskName = (string) ($task['task_name'] ?? 'Current schedule task');

    return array_merge($location, [
        'task_id' => isset($task['task_id']) ? (int) $task['task_id'] : (isset($task['id']) ? (int) $task['id'] : null),
        'task_name' => $taskName,
        'task_label' => "{$sortLabel}: {$taskName}",
        'sort_order' => $sortOrder,
        'task_status' => $task['status'] ?? 'pending',
        'planned_date' => $task['planned_date'] ?? null,
        'actual_date' => $task['actual_date'] ?? null,
        'responsible_role' => $task['responsible_role'] ?? '',
    ]);
}

function pmtsSortScheduleTasks(array $tasks): array
{
    usort($tasks, function ($a, $b) {
        $aOrder = (int) ($a['sort_order'] ?? $a['id'] ?? $a['task_id'] ?? 0);
        $bOrder = (int) ($b['sort_order'] ?? $b['id'] ?? $b['task_id'] ?? 0);
        if ($aOrder === $bOrder) {
            return (int) ($a['id'] ?? $a['task_id'] ?? 0) <=> (int) ($b['id'] ?? $b['task_id'] ?? 0);
        }
        return $aOrder <=> $bOrder;
    });
    return $tasks;
}

function pmtsCurrentScheduleTask(array $tasks): ?array
{
    if (!$tasks) {
        return null;
    }

    $tasks = pmtsSortScheduleTasks($tasks);

    foreach ($tasks as $task) {
        if (($task['status'] ?? '') === 'in_progress') {
            return $task;
        }
    }

    foreach ($tasks as $task) {
        if (($task['status'] ?? '') === 'delayed' && empty($task['actual_date'])) {
            return $task;
        }
    }

    foreach ($tasks as $task) {
        if (($task['status'] ?? 'pending') === 'pending') {
            return $task;
        }
    }

    $lastClosed = null;
    foreach ($tasks as $task) {
        if (in_array(($task['status'] ?? ''), ['completed', 'skipped'], true) || !empty($task['actual_date'])) {
            $lastClosed = $task;
        }
    }

    return $lastClosed;
}

function pmtsTrackingStageForScheduleTasks(array $tasks, ?string $fallbackStatus = 'draft'): array
{
    $task = pmtsCurrentScheduleTask($tasks);
    if (!$task) {
        return pmtsTrackingStageForStatus($fallbackStatus);
    }

    $stage = pmtsScheduleTaskLocationForTask($task);
    $stage['display_status'] = $stage['status'];
    $stage['step'] = max(1, (int) ($stage['sort_order'] ?? 1));
    $stage['total_steps'] = max(count($tasks), $stage['step']);
    return $stage;
}

function pmtsWorkflowStepsForStatus(?string $status): array
{
    $current = pmtsTrackingStageForStatus($status);
    $currentStep = (int) ($current['step'] ?? 0);

    $steps = [];
    foreach (pmtsWorkflowStages() as $index => $stage) {
        $stepNumber = $index + 1;
        $state = 'pending';
        if ($currentStep > 0 && $stepNumber < $currentStep) {
            $state = 'completed';
        } elseif ($currentStep > 0 && $stepNumber === $currentStep) {
            $state = 'current';
        }

        $steps[] = [
            'key' => $stage['key'],
            'label' => $stage['label'],
            'status' => $stage['status'],
            'icon' => $stage['icon'],
            'description' => $stage['description'],
            'state' => $state,
            'step' => $stepNumber,
        ];
    }

    return $steps;
}

function pmtsScheduleStepsForTasks(array $tasks): array
{
    $tasks = pmtsSortScheduleTasks($tasks);
    $current = pmtsCurrentScheduleTask($tasks);
    $currentId = $current ? (int) ($current['id'] ?? $current['task_id'] ?? 0) : 0;

    $steps = [];
    foreach ($tasks as $index => $task) {
        $taskId = (int) ($task['id'] ?? $task['task_id'] ?? 0);
        $status = $task['status'] ?? 'pending';
        $location = pmtsScheduleTaskLocationForTask($task);
        $state = 'pending';
        if ($status === 'completed') {
            $state = 'completed';
        } elseif ($status === 'skipped') {
            $state = 'skipped';
        } elseif ($taskId && $taskId === $currentId) {
            $state = 'current';
        }

        $steps[] = [
            'key' => 'schedule_task_' . ($taskId ?: ($index + 1)),
            'label' => $task['task_name'] ?? ('Task ' . ($index + 1)),
            'location_label' => $location['label'],
            'status' => $status,
            'icon' => $location['icon'],
            'description' => $location['description'],
            'state' => $state,
            'step' => $index + 1,
            'sort_order' => (int) ($task['sort_order'] ?? ($index + 1)),
        ];
    }

    return $steps;
}

function pmtsEnrichProcurementTracking(array $procurement, array $scheduleTasks = []): array
{
    $status = $procurement['current_status'] ?? $procurement['status'] ?? 'draft';
    $stage = $scheduleTasks
        ? pmtsTrackingStageForScheduleTasks($scheduleTasks, $status)
        : pmtsTrackingStageForStatus($status);

    $procurement['status'] = $status;
    $procurement['current_stage_key'] = $stage['key'];
    $procurement['current_stage_label'] = $stage['label'];
    $procurement['current_location'] = $stage['label'];
    $procurement['current_stage_description'] = $stage['description'];
    $procurement['tracking_stage'] = $stage;

    if (!empty($stage['task_id']) || !empty($stage['task_name'])) {
        $procurement['current_task'] = [
            'id' => $stage['task_id'] ?? null,
            'task_name' => $stage['task_name'] ?? null,
            'task_label' => $stage['task_label'] ?? null,
            'sort_order' => $stage['sort_order'] ?? null,
            'status' => $stage['task_status'] ?? null,
            'planned_date' => $stage['planned_date'] ?? null,
            'actual_date' => $stage['actual_date'] ?? null,
            'responsible_role' => $stage['responsible_role'] ?? '',
            'location_label' => $stage['label'],
        ];
        $procurement['current_task_label'] = $stage['task_label'] ?? $stage['task_name'] ?? null;
        $procurement['current_task_sort_order'] = $stage['sort_order'] ?? null;
        $procurement['current_task_status'] = $stage['task_status'] ?? null;
    }

    return $procurement;
}

function pmtsLoadScheduleTasksForProcurements(PDO $pdo, array $procurementIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $procurementIds))));
    if (!$ids) {
        return [];
    }

    $columnStmt = $pdo->query("SHOW COLUMNS FROM procurement_time_schedule LIKE 'sort_order'");
    $hasSortOrder = (bool) $columnStmt->fetch();
    $sortExpr = $hasSortOrder ? 'COALESCE(sort_order, id)' : 'id';

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, procurement_id, task_name, planned_date, actual_date, status, remarks, {$sortExpr} AS sort_order
         FROM procurement_time_schedule
         WHERE procurement_id IN ({$placeholders})
         ORDER BY procurement_id ASC, {$sortExpr} ASC, id ASC"
    );
    $stmt->execute($ids);

    $tasksByProcurement = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $task) {
        $pid = (int) $task['procurement_id'];
        if (!isset($tasksByProcurement[$pid])) {
            $tasksByProcurement[$pid] = [];
        }
        $tasksByProcurement[$pid][] = $task;
    }

    return $tasksByProcurement;
}
