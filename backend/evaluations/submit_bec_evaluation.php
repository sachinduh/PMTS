<?php
// ============================================================
// PMTS – POST /evaluations/submit_bec_evaluation.php
// BEC member submits BEC evaluation for a procurement.
// Body: { "procurement_id": 5, "bid_amount": 4500000, "compliance": "compliant", "remarks": "..." }
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
    $authUser = requireRole(['bec_member', 'it_admin']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $procId = (int) ($input['procurement_id'] ?? 0);
    $bidAmount = isset($input['bid_amount']) ? (float) $input['bid_amount'] : null;
    $compliance = trim($input['compliance'] ?? 'compliant');
    $remarks = trim($input['remarks'] ?? '');

    if (!$procId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id is required.']);
        exit;
    }

    if ($bidAmount !== null && $bidAmount < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'bid_amount cannot be negative.']);
        exit;
    }

    if (!in_array($compliance, ['compliant', 'non_compliant', 'conditional'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid compliance value.']);
        exit;
    }

    $pdo = getPDO();

    $procStmt = $pdo->prepare('SELECT id FROM procurements WHERE id = ? LIMIT 1');
    $procStmt->execute([$procId]);
    if (!$procStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $checkStmt = $pdo->prepare('SELECT id FROM bec_evaluations WHERE procurement_id = ? AND evaluator_id = ? LIMIT 1');
    $checkStmt->execute([$procId, $authUser['sub']]);
    $existing = $checkStmt->fetch();

    $evaluatedAt = date('Y-m-d H:i:s');
    if ($existing) {
        $pdo->prepare(
            'UPDATE bec_evaluations
             SET evaluation_status = ?, bid_amount = ?, compliance = ?, remarks = ?, evaluated_at = ?
             WHERE id = ?'
        )->execute(['completed', $bidAmount, $compliance, $remarks, $evaluatedAt, $existing['id']]);
        $newId = (int) $existing['id'];
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO bec_evaluations (procurement_id, evaluator_id, evaluation_status, bid_amount, compliance, remarks, evaluated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$procId, $authUser['sub'], 'completed', $bidAmount, $compliance, $remarks, $evaluatedAt]);
        $newId = (int) $pdo->lastInsertId();
    }

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'BEC_EVALUATION',
        'evaluations',
        "BEC evaluation submitted for procurement ID $procId. Compliance: $compliance"
    );

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'BEC evaluation submitted successfully.',
        'id' => $newId,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit BEC evaluation.']);
    error_log('PMTS SubmitBECEvaluation Error: ' . $e->getMessage());
}
