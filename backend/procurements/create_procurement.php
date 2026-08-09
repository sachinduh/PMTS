<?php
// ============================================================
//  PMTS – POST /procurements/create_procurement.php
//  Create a new procurement case.
//  All procurement types can save the 15-task time schedule at creation.
//  Roles: procurement_officer, it_admin
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../config/notification_helper.php';
require_once __DIR__ . '/../schedule/ncb_tasks.php';
require_once __DIR__ . '/../schedule/schedule_schema_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

function pmtsCleanDateValue($value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : substr($value, 0, 10);
}

function pmtsColumnExistsLocal(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function pmtsInsertSubmittedScheduleTasks(PDO $pdo, int $procurementId, array $scheduleTasks): bool {
    pmtsEnsureAllowedDelayDaysColumn($pdo);

    $validStatuses = ['pending', 'in_progress', 'completed', 'delayed', 'skipped'];
    $hasSortOrder = pmtsColumnExistsLocal($pdo, 'procurement_time_schedule', 'sort_order');
    $hasAllowedDelayDays = pmtsColumnExistsLocal($pdo, 'procurement_time_schedule', 'allowed_delay_days');
    $inserted = 0;
    $delayMap = pmtsGetTaskDelaySettingsMap($pdo, getDefaultProcurementScheduleTasks());

    $columns = ['procurement_id', 'task_name', 'planned_date', 'actual_date', 'status', 'remarks'];
    if ($hasAllowedDelayDays) {
        $columns[] = 'allowed_delay_days';
    }
    if ($hasSortOrder) {
        $columns[] = 'sort_order';
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $pdo->prepare(
        "INSERT INTO procurement_time_schedule (" . implode(', ', $columns) . ")
         VALUES ({$placeholders})"
    );

    foreach ($scheduleTasks as $index => $task) {
        $taskName = trim((string) ($task['task_name'] ?? ''));
        if ($taskName === '') {
            continue;
        }

        $status = (string) ($task['status'] ?? 'pending');
        if (!in_array($status, $validStatuses, true)) {
            $status = 'pending';
        }

        $plannedDate = pmtsCleanDateValue($task['planned_date'] ?? null);
        $actualDate = pmtsCleanDateValue($task['actual_date'] ?? null);
        $allowedDelayDays = $delayMap[$taskName] ?? 0;

        if ($status !== 'skipped' && $plannedDate && $actualDate && pmtsScheduleDaysLateBeyondAllowed($plannedDate, $actualDate, $allowedDelayDays) > 0) {
            $status = 'delayed';
        }

        $params = [
            $procurementId,
            $taskName,
            $plannedDate,
            $actualDate,
            $status,
            trim((string) ($task['remarks'] ?? '')),
        ];

        if ($hasAllowedDelayDays) {
            $params[] = $allowedDelayDays;
        }

        if ($hasSortOrder) {
            $params[] = (int) ($task['sort_order'] ?? ($index + 1));
        }

        $stmt->execute($params);
        $inserted++;
    }

    return $inserted > 0;
}

try {
    $authUser = requireRole(['procurement_officer', 'it_admin']);
    $input    = json_decode(file_get_contents('php://input'), true) ?: [];

    $required = ['title', 'procurement_type'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '$field' is required."]);
            exit;
        }
    }

    $validTypes = ['NCB', 'Shopping', 'Direct Purchase', 'Direct Limited Purchasing', 'Emergency Procurement'];
    if (!in_array($input['procurement_type'], $validTypes, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid procurement_type.']);
        exit;
    }

    $pdo = getPDO();

    $year      = date('Y');
    $countStmt = $pdo->query("SELECT COUNT(*) FROM procurements WHERE YEAR(created_at) = $year");
    $count     = (int) $countStmt->fetchColumn() + 1;
    $procId    = "PROC-$year-" . str_pad($count, 3, '0', STR_PAD_LEFT);

    $title           = trim($input['title']);
    $tenderNumber    = trim($input['tender_number'] ?? '');
    $procType        = $input['procurement_type'];
    $category        = trim($input['category'] ?? '');
    $estimatedAmount = !empty($input['estimated_amount']) ? (float) $input['estimated_amount'] : null;
    $receivedDate    = !empty($input['received_date']) ? pmtsCleanDateValue($input['received_date']) : date('Y-m-d');
    $description     = trim($input['description'] ?? '');
    $priority        = in_array($input['priority'] ?? '', ['low','medium','high','urgent'], true)
                       ? $input['priority'] : 'medium';
    $fileName        = trim($input['file_name'] ?? '');
    if ($fileName === '') {
        $fileName = $tenderNumber !== '' ? "$tenderNumber - $title" : $title;
    }

    $hasFileName = pmtsColumnExistsLocal($pdo, 'procurements', 'file_name');

    $pdo->beginTransaction();

    if ($hasFileName) {
        $stmt = $pdo->prepare(
            "INSERT INTO procurements
                (procurement_id, title, tender_number, file_name, procurement_type, category,
                 estimated_amount, received_date, description, created_by, current_status, priority)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)"
        );
        $stmt->execute([
            $procId, $title, $tenderNumber, $fileName, $procType, $category,
            $estimatedAmount, $receivedDate, $description,
            $authUser['sub'], $priority,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO procurements
                (procurement_id, title, tender_number, procurement_type, category,
                 estimated_amount, received_date, description, created_by, current_status, priority)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)"
        );
        $stmt->execute([
            $procId, $title, $tenderNumber, $procType, $category,
            $estimatedAmount, $receivedDate, $description,
            $authUser['sub'], $priority,
        ]);
    }
    $newProcDbId = (int) $pdo->lastInsertId();

    $submittedSchedule = is_array($input['schedule_tasks'] ?? null) ? $input['schedule_tasks'] : [];
    if (!pmtsInsertSubmittedScheduleTasks($pdo, $newProcDbId, $submittedSchedule)) {
        insertDefaultProcurementScheduleTasks($pdo, $newProcDbId);
    }

    $pdo->prepare(
        "INSERT INTO status_history (procurement_id, old_status, new_status, changed_by, remarks)
         VALUES (?, NULL, 'draft', ?, 'Procurement created and currently with Procurement Officer.')"
    )->execute([$newProcDbId, $authUser['sub']]);

    $pdo->commit();

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'CREATE_PROCUREMENT',
        'procurements',
        "Created procurement $procId (Type: $procType, Title: $title)"
    );

    pmtsNotifyRole(
        $pdo,
        'director',
        'New Procurement Created',
        "New procurement $procId has been created and is ready for tracking.",
        'status_update'
    );

    pmtsNotifyUser(
        $pdo,
        (int) $authUser['sub'],
        'Procurement Created',
        "Procurement $procId was created successfully. The time schedule is now available.",
        'success'
    );

    http_response_code(201);
    echo json_encode([
        'success'        => true,
        'message'        => "Procurement '$procId' created successfully. Time schedule tasks saved.",
        'procurement_id' => $procId,
        'id'             => $newProcDbId,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create procurement.']);
    error_log("PMTS CreateProcurement Error: " . $e->getMessage());
}
