<?php
// ============================================================
// PMTS – POST /schedule/update_ncb_payment_date.php
// Save the payment date shown at the bottom of the procurement schedule.
// Body: { "procurement_id": 5, "payment_date": "2026-07-15" }
// Roles: procurement_officer, it_admin
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

function cleanDateValue($value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : substr($value, 0, 10);
}

try {
    $authUser = requireRole(['procurement_officer', 'it_admin']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $procId = (int) ($input['procurement_id'] ?? 0);

    if (!$procId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id is required.']);
        exit;
    }

    $paymentDate = cleanDateValue($input['payment_date'] ?? null);
    $pdo = getPDO();

    $procStmt = $pdo->prepare("SELECT id, procurement_id, procurement_type FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procId]);
    $proc = $procStmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE procurements SET payment_date = ? WHERE id = ?");
    $stmt->execute([$paymentDate, $procId]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_PAYMENT_DATE',
        'procurements',
        "Updated payment date for {$proc['procurement_id']}"
    );

    echo json_encode(['success' => true, 'message' => 'Payment date updated successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update payment date.']);
    error_log('PMTS UpdateNcbPaymentDate Error: ' . $e->getMessage());
}
