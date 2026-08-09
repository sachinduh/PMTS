<?php
// ============================================================
//  PMTS – POST /procurements/update_status.php
//  Update procurement tracking location / workflow status.
//  Body: { "id": 5, "new_status": "bid_evaluation", "remarks": "Moved to BEC" }
//  Director approval is NOT part of this workflow.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../config/notification_helper.php';
require_once __DIR__ . '/tracking_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Status → allowed roles mapping. Procurement Officer manages tracking.
const STATUS_ROLE_MAP = [
    'draft'                  => ['procurement_officer', 'it_admin'],
    'submitted'              => ['procurement_officer', 'it_admin'],
    'under_review'           => ['procurement_officer', 'it_admin'],
    'specification_approval' => ['procurement_officer', 'specification_committee', 'it_admin'],
    'tender_preparation'     => ['procurement_officer', 'it_admin'],
    'advertised'             => ['procurement_officer', 'it_admin'],
    'bid_received'           => ['procurement_officer', 'it_admin'],
    'bid_evaluation'         => ['procurement_officer', 'bec_member', 'it_admin'],
    'financial_evaluation'   => ['procurement_officer', 'accountant', 'it_admin'],
    'awarded'                => ['procurement_officer', 'it_admin'],
    'purchase_order_issued'  => ['procurement_officer', 'it_admin'],
    'contract_signed'        => ['procurement_officer', 'it_admin'],
    'completed'              => ['procurement_officer', 'it_admin'],
    'cancelled'              => ['procurement_officer', 'it_admin'],
    'on_hold'                => ['procurement_officer', 'it_admin'],
];

try {
    $authUser = requireAuth();
    $input    = json_decode(file_get_contents('php://input'), true);

    $id        = (int)   ($input['id'] ?? 0);
    $newStatus = trim($input['new_status'] ?? '');
    $remarks   = trim($input['remarks'] ?? '');

    if (!$id || !$newStatus) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'id and new_status are required.']);
        exit;
    }

    if (!array_key_exists($newStatus, STATUS_ROLE_MAP)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid tracking stage/status value.']);
        exit;
    }

    $allowedRoles = STATUS_ROLE_MAP[$newStatus];
    if (!in_array($authUser['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => "Your role '{$authUser['role']}' cannot set tracking stage to '$newStatus'.",
        ]);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, procurement_id, title, current_status FROM procurements WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $proc = $stmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $oldStatus = $proc['current_status'];
    if ($oldStatus === $newStatus) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "Procurement is already in '$newStatus' stage."]);
        exit;
    }

    $oldStage = pmtsTrackingStageForStatus($oldStatus);
    $newStage = pmtsTrackingStageForStatus($newStatus);
    $defaultRemarks = "Tracking moved from {$oldStage['label']} to {$newStage['label']}.";

    $pdo->beginTransaction();

    $pdo->prepare("UPDATE procurements SET current_status = ? WHERE id = ?")->execute([$newStatus, $id]);

    $pdo->prepare(
        "INSERT INTO status_history (procurement_id, old_status, new_status, changed_by, remarks)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$id, $oldStatus, $newStatus, $authUser['sub'], $remarks ?: $defaultRemarks]);

    $pdo->commit();

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_TRACKING_STAGE',
        'procurements',
        "Tracking changed for {$proc['procurement_id']}: {$oldStage['label']} -> {$newStage['label']}"
    );

    $title = 'Procurement Tracking Updated';
    $message = "{$proc['procurement_id']} - {$proc['title']} is now at {$newStage['label']}.";

    // Notify Director for tracking visibility and notify the role responsible for the new stage.
    pmtsNotifyRole($pdo, 'director', $title, $message, 'status_update');
    pmtsNotifyRoles($pdo, pmtsResponsibleRolesForStatus($newStatus), $title, $message, 'status_update');

    echo json_encode([
        'success' => true,
        'message' => "Procurement {$proc['procurement_id']} tracking updated: {$oldStage['label']} -> {$newStage['label']}",
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'old_stage' => $oldStage,
        'new_stage' => $newStage,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update procurement tracking stage.']);
    error_log("PMTS UpdateTrackingStage Error: " . $e->getMessage());
}
