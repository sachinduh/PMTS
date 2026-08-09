<?php
require_once __DIR__ . '/schedule_schema_helper.php';
require_once __DIR__ . '/task_delay_settings_helper.php';
// ============================================================
// PMTS – Default Procurement Time Schedule Tasks
// Same standard schedule is used for all procurement types.
// Legacy function names are kept so older files still work.
// ============================================================

function getDefaultProcurementScheduleTasks(): array {
    return [
        ['task_name' => 'Appoint Specification Preparation Committee', 'responsible_role' => 'Director / Procurement Branch'],
        ['task_name' => 'Submit finalized specification to Procurement Branch', 'responsible_role' => 'Specification Preparation Committee'],
        ['task_name' => 'Appoint Bid Evaluation Committee (BEC)', 'responsible_role' => 'Director / Procurement Branch'],
        ['task_name' => 'Prepare bidding / quotation document', 'responsible_role' => 'Procurement Officer'],
        ['task_name' => 'Send procurement document to relevant committee for review', 'responsible_role' => 'Procurement Officer / BEC'],
        ['task_name' => 'Publish / call bids or quotations', 'responsible_role' => 'Procurement Officer'],
        ['task_name' => 'Conduct pre-bid meeting, if required', 'responsible_role' => 'Procurement Officer / Committee'],
        ['task_name' => 'Bid / quotation opening', 'responsible_role' => 'Bid Opening Committee / Procurement Officer'],
        ['task_name' => 'Send bid / quotation documents to Evaluation Committee', 'responsible_role' => 'Procurement Officer'],
        ['task_name' => 'Submit evaluation report and award recommendation', 'responsible_role' => 'BEC'],
        ['task_name' => 'Tender Decision', 'responsible_role' => 'Procurement Committee'],
        ['task_name' => 'Appeal Committee', 'responsible_role' => 'Procurement Committee'],
        ['task_name' => 'Issue purchase order / letter of award', 'responsible_role' => 'Procurement Officer'],
        ['task_name' => 'Stock Received', 'responsible_role' => 'Procurement Committee'],
        ['task_name' => 'Receive acceptance letter from supplier', 'responsible_role' => 'Supplier / Procurement Officer'],

    ];
}

function getDefaultNcbTasks(): array {
    return getDefaultProcurementScheduleTasks();
}

function getResponsibleRoleForTask(string $taskName): string {
    $legacyNames = [
        'Select procurement type as NCB and open NCB time schedule' => 'Procurement Officer',
        'Prepare bidding document for NCB' => 'Procurement Officer',
        'Bid closing and opening' => 'Bid Opening Committee',
        'Close bids on scheduled date and time' => 'Bid Opening Committee',
        'Open bids and prepare bid opening attendance/minutes' => 'Bid Opening Committee',
        'Bid Opening' => 'Bid Opening Committee',
        'Bid calling / advertisement' => 'Procurement Officer',
        'Send documents to Evaluation Committee' => 'Procurement Officer / BEC',
        'Evaluation report preparation' => 'BEC',
        'Evaluation report submission' => 'BEC',
        'Award recommendation' => 'BEC',
        'Approval of award' => 'Procurement Committee',
        'Purchase order / award letter' => 'Procurement Officer',
        'Contract signing / completion' => 'Procurement Officer / Supplier',
    ];

    if (isset($legacyNames[$taskName])) {
        return $legacyNames[$taskName];
    }

    foreach (getDefaultProcurementScheduleTasks() as $task) {
        if ($task['task_name'] === $taskName) {
            return $task['responsible_role'];
        }
    }
    return '';
}

function insertDefaultProcurementScheduleTasks(PDO $pdo, int $procurementId): void {
    pmtsEnsureAllowedDelayDaysColumn($pdo);
    $defaultTasks = getDefaultProcurementScheduleTasks();
    $delayMap = pmtsGetTaskDelaySettingsMap($pdo, $defaultTasks);
    $columnStmt = $pdo->query("SHOW COLUMNS FROM procurement_time_schedule LIKE 'sort_order'");
    $hasSortOrder = (bool) $columnStmt->fetch();

    if ($hasSortOrder) {
        $stmt = $pdo->prepare(
            "INSERT INTO procurement_time_schedule (procurement_id, task_name, allowed_delay_days, sort_order, status)
             VALUES (?, ?, ?, ?, 'pending')"
        );

        foreach ($defaultTasks as $index => $task) {
            $allowedDelayDays = $delayMap[$task['task_name']] ?? 0;
            $stmt->execute([$procurementId, $task['task_name'], $allowedDelayDays, $index + 1]);
        }
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO procurement_time_schedule (procurement_id, task_name, allowed_delay_days, status)
         VALUES (?, ?, ?, 'pending')"
    );

    foreach ($defaultTasks as $task) {
        $allowedDelayDays = $delayMap[$task['task_name']] ?? 0;
        $stmt->execute([$procurementId, $task['task_name'], $allowedDelayDays]);
    }
}

function insertDefaultNcbTasks(PDO $pdo, int $procurementId): void {
    insertDefaultProcurementScheduleTasks($pdo, $procurementId);
}
