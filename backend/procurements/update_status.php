<?php
// ============================================================
//  PMTS – POST /procurements/update_status.php
//  Advance or change procurement status with history log
//  Body: { "id": 5, "new_status": "technical_evaluation", "remarks": "..." }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Status → allowed roles mapping
const STATUS_ROLE_MAP = [
    'submitted'            => ['procurement_officer', 'it_admin'],
    'under_review'         => ['procurement_officer', 'it_admin'],
    'specification_approval'=> ['specification_committee', 'procurement_officer', 'it_admin'],
    'tender_preparation'   => ['procurement_officer', 'it_admin'],
    'advertised'           => ['procurement_officer', 'it_admin'],
    'bid_received'         => ['procurement_officer', 'it_admin'],
    'technical_evaluation' => ['procurement_officer', 'tec_member', 'it_admin'],
    'financial_evaluation' => ['accountant', 'it_admin'],
    'director_approval'    => ['director', 'it_admin'],
    'awarded'              => ['director', 'it_admin'],
    'purchase_order_issued'=> ['procurement_officer', 'it_admin'],
    'contract_signed'      => ['procurement_officer', 'it_admin'],
    'completed'            => ['procurement_officer', 'director', 'it_admin'],
    'cancelled'            => ['director', 'it_admin'],
    'on_hold'              => ['director', 'procurement_officer', 'it_admin'],
];

try {
    $authUser = requireAuth();
    $input    = json_decode(file_get_contents('php://input'), true);

    $id        = (int)   ($input['id']         ?? 0);
    $newStatus = trim($input['new_status'] ?? '');
    $remarks   = trim($input['remarks']    ?? '');

    if (!$id || !$newStatus) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'id and new_status are required.']);
        exit;
    }

    if (!array_key_exists($newStatus, STATUS_ROLE_MAP)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid new_status value.']);
        exit;
    }

    // Check role permission for this status transition
    $allowedRoles = STATUS_ROLE_MAP[$newStatus];
    if (!in_array($authUser['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => "Your role '{$authUser['role']}' cannot set status to '$newStatus'.",
        ]);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, procurement_id, current_status FROM procurements WHERE id = ? LIMIT 1");
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
        echo json_encode(['success' => false, 'message' => "Procurement is already in '$newStatus' status."]);
        exit;
    }

    $pdo->beginTransaction();

    // Update status
    $pdo->prepare("UPDATE procurements SET current_status = ? WHERE id = ?")->execute([$newStatus, $id]);

    // Log status history
    $pdo->prepare(
        "INSERT INTO status_history (procurement_id, old_status, new_status, changed_by, remarks)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$id, $oldStatus, $newStatus, $authUser['sub'], $remarks ?: null]);

    $pdo->commit();

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_STATUS',
        'procurements',
        "Status changed for {$proc['procurement_id']}: $oldStatus → $newStatus"
    );

    echo json_encode([
        'success'    => true,
        'message'    => "Procurement {$proc['procurement_id']} status updated: $oldStatus → $newStatus",
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update procurement status.']);
    error_log("PMTS UpdateStatus Error: " . $e->getMessage());
}
