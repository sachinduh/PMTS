<?php
// ============================================================
//  PMTS – POST /evaluations/create_financial_approval.php
//  Accountant submits financial budget verification
//  Body: { "procurement_id": 5, "budget_available": "yes", "approved_amount": 4500000, "approval_status": "approved", "comments": "..." }
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

try {
    $authUser = requireRole(['accountant', 'it_admin']);
    $input    = json_decode(file_get_contents('php://input'), true);

    $procId          = (int)   ($input['procurement_id']  ?? 0);
    $action = trim($input['action'] ?? '');
    $budgetAvailable = trim($input['budget_available'] ?? ($action === 'reject' ? 'no' : 'yes'));
    $approvedAmount  = isset($input['approved_amount'])  ? (float) $input['approved_amount']  : null;
    $approvalStatus  = trim($input['approval_status']  ?? 'pending');

    if ($action === 'approve') {
        $approvalStatus = 'approved';
    } elseif ($action === 'reject') {
        $approvalStatus = 'rejected';
    }
    $comments        = trim($input['comments']         ?? '');

    if (!$procId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id is required.']);
        exit;
    }

    if (!in_array($budgetAvailable, ['yes', 'no', 'partial'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'budget_available must be yes, no, or partial.']);
        exit;
    }

    if (!in_array($approvalStatus, ['pending', 'approved', 'rejected', 'on_hold'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid approval_status.']);
        exit;
    }

    $pdo = getPDO();

    $procStmt = $pdo->prepare("SELECT id, procurement_id, estimated_amount FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procId]);
    $procRow = $procStmt->fetch();
    if (!$procRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    if ($approvedAmount === null && $approvalStatus === 'approved') {
        $approvedAmount = $procRow['estimated_amount'] ?? null;
    }

    $approvedAt = ($approvalStatus === 'approved' || $approvalStatus === 'rejected') ? date('Y-m-d H:i:s') : null;

    // Upsert per accountant
    $checkStmt = $pdo->prepare("SELECT id FROM financial_approvals WHERE procurement_id = ? AND accountant_id = ? LIMIT 1");
    $checkStmt->execute([$procId, $authUser['sub']]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $pdo->prepare(
            "UPDATE financial_approvals
             SET budget_available = ?, approved_amount = ?, approval_status = ?, comments = ?, approved_at = ?
             WHERE id = ?"
        )->execute([$budgetAvailable, $approvedAmount, $approvalStatus, $comments, $approvedAt, $existing['id']]);
        $newId = $existing['id'];
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO financial_approvals (procurement_id, accountant_id, budget_available, approved_amount, approval_status, comments, approved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$procId, $authUser['sub'], $budgetAvailable, $approvedAmount, $approvalStatus, $comments, $approvedAt]);
        $newId = (int) $pdo->lastInsertId();
    }

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'FINANCIAL_APPROVAL',
        'evaluations',
        "Financial approval for procurement ID $procId: $approvalStatus (Budget: $budgetAvailable)"
    );

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Financial approval submitted successfully.',
        'id'      => $newId,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit financial approval.']);
    error_log("PMTS FinancialApproval Error: " . $e->getMessage());
}
