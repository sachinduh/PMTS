<?php
// ============================================================
//  PMTS – GET /procurements/get_procurement_by_id.php
//  Returns full procurement details with related data
//  Query: ?id=5 or ?procurement_id=PROC-2026-001
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../schedule/ncb_tasks.php';
require_once __DIR__ . '/tracking_helper.php';
require_once __DIR__ . '/../alerts/delay_alert_helper.php';
require_once __DIR__ . '/../schedule/task_file_tracking_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $authUser = requireAuth();
    $pdo      = getPDO();
    pmtsEnsureAllowedDelayDaysColumn($pdo);

    $id           = (int)   ($_GET['id']             ?? 0);
    $procurementId = trim($_GET['procurement_id'] ?? '');

    if (!$id && !$procurementId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Provide id or procurement_id parameter.']);
        exit;
    }

    // Fetch main procurement row
    if ($id) {
        $stmt = $pdo->prepare("SELECT p.*, p.current_status AS status, u.full_name AS created_by_name FROM procurements p LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ? LIMIT 1");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT p.*, p.current_status AS status, u.full_name AS created_by_name FROM procurements p LEFT JOIN users u ON u.id = p.created_by WHERE p.procurement_id = ? LIMIT 1");
        $stmt->execute([$procurementId]);
    }
    $procurement = $stmt->fetch();

    if (!$procurement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $dbId = (int) $procurement['id'];

    // Procurement schedule tasks
    $columnStmt = $pdo->query("SHOW COLUMNS FROM procurement_time_schedule LIKE 'sort_order'");
    $orderBy = $columnStmt->fetch() ? 'COALESCE(sort_order, id) ASC, id ASC' : 'id ASC';
    $scheduleStmt = $pdo->prepare("SELECT * FROM procurement_time_schedule WHERE procurement_id = ? ORDER BY {$orderBy}");
    $scheduleStmt->execute([$dbId]);
    $schedule = $scheduleStmt->fetchAll();
    $trackingSummaries = pmtsGetTaskFileTrackingSummaries($pdo, $dbId);
    $today = date('Y-m-d');
    foreach ($schedule as &$task) {
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

    $procurement = pmtsEnrichProcurementTracking($procurement, $schedule);
    $workflowSteps = $schedule ? pmtsScheduleStepsForTasks($schedule) : pmtsWorkflowStepsForStatus($procurement['current_status'] ?? $procurement['status'] ?? 'draft');

    // Suppliers / bids
    $supplierStmt = $pdo->prepare(
        "SELECT ps.*, s.supplier_name, s.contact_person, s.email AS supplier_email, s.phone AS supplier_phone
         FROM procurement_suppliers ps
         JOIN suppliers s ON s.id = ps.supplier_id
         WHERE ps.procurement_id = ?"
    );
    $supplierStmt->execute([$dbId]);
    $suppliers = $supplierStmt->fetchAll();

    // Status history
    $histStmt = $pdo->prepare(
        "SELECT sh.*, u.full_name AS changed_by_name
         FROM status_history sh
         LEFT JOIN users u ON u.id = sh.changed_by
         WHERE sh.procurement_id = ?
         ORDER BY sh.changed_at ASC"
    );
    $histStmt->execute([$dbId]);
    $history = $histStmt->fetchAll();

    // BEC evaluations
    $becStmt = $pdo->prepare(
        "SELECT be.*, u.full_name AS evaluator_name
         FROM bec_evaluations be
         LEFT JOIN users u ON u.id = be.evaluator_id
         WHERE be.procurement_id = ?
         ORDER BY be.id DESC"
    );
    $becStmt->execute([$dbId]);
    $becEvals = $becStmt->fetchAll();

    // Financial approval
    $finStmt = $pdo->prepare(
        "SELECT fa.*, u.full_name AS accountant_name
         FROM financial_approvals fa
         LEFT JOIN users u ON u.id = fa.accountant_id
         WHERE fa.procurement_id = ?
         ORDER BY fa.id DESC LIMIT 1"
    );
    $finStmt->execute([$dbId]);
    $financialApproval = $finStmt->fetch() ?: null;

    // Purchase order
    $poStmt = $pdo->prepare(
        "SELECT po.*, s.supplier_name
         FROM purchase_orders po
         LEFT JOIN suppliers s ON s.id = po.supplier_id
         WHERE po.procurement_id = ?
         ORDER BY po.id DESC LIMIT 1"
    );
    $poStmt->execute([$dbId]);
    $purchaseOrder = $poStmt->fetch() ?: null;

    echo json_encode([
        'success'           => true,
        'procurement'       => $procurement,
        'schedule'          => $schedule,
        'status_history'    => $history,
        'bid_evals'         => $becEvals,
        'financial_approval'=> $financialApproval,
        'purchase_order'    => $purchaseOrder,
        'tracking_stage'    => $procurement['tracking_stage'],
        'workflow_steps'    => $workflowSteps,
        'data'              => [
            'procurement'       => $procurement,
            'schedule'          => $schedule,
            'suppliers'         => $suppliers,
            'status_history'    => $history,
            'bid_evals'         => $becEvals,
            'financial_approval'=> $financialApproval,
            'purchase_order'    => $purchaseOrder,
            'tracking_stage'    => $procurement['tracking_stage'],
            'workflow_steps'    => $workflowSteps,
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch procurement details.']);
    error_log("PMTS GetProcurementById Error: " . $e->getMessage());
}
