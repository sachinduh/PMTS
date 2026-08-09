<?php

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
    $authUser = requireRole(['procurement_officer', 'it_admin']);
    $input    = json_decode(file_get_contents('php://input'), true);

    $procId     = (int)   ($input['procurement_id'] ?? 0);
    $supplierId = (int)   ($input['supplier_id']    ?? 0);
    $poDate     = trim($input['po_date']     ?? date('Y-m-d'));
    $amount     = isset($input['amount'])     ? (float) $input['amount']     : null;
    $remarks    = trim($input['remarks']    ?? '');

    if (!$procId || !$supplierId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id and supplier_id are required.']);
        exit;
    }

    $pdo = getPDO();

    // Verify procurement exists and is awarded
    $procStmt = $pdo->prepare("SELECT id, procurement_id, current_status FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procId]);
    $proc = $procStmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    if (!in_array($proc['current_status'], ['awarded', 'purchase_order_issued'], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Purchase order can only be issued for 'awarded' procurements. Current: {$proc['current_status']}",
        ]);
        exit;
    }

    // Generate PO number: PO-YYYY-NNN
    $year      = date('Y');
    $countStmt = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE YEAR(created_at) = $year");
    $count     = (int) $countStmt->fetchColumn() + 1;
    $poNumber  = "PO-$year-" . str_pad($count, 3, '0', STR_PAD_LEFT);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO purchase_orders (procurement_id, po_number, supplier_id, po_date, amount, status, remarks)
         VALUES (?, ?, ?, ?, ?, 'issued', ?)"
    );
    $stmt->execute([$procId, $poNumber, $supplierId, $poDate, $amount, $remarks ?: null]);
    $newId = (int) $pdo->lastInsertId();

    // Update procurement status
    $pdo->prepare("UPDATE procurements SET current_status = 'purchase_order_issued' WHERE id = ?")->execute([$procId]);
    $pdo->prepare(
        "INSERT INTO status_history (procurement_id, old_status, new_status, changed_by, remarks)
         VALUES (?, 'awarded', 'purchase_order_issued', ?, ?)"
    )->execute([$procId, $authUser['sub'], "PO issued: $poNumber"]);

    $pdo->commit();

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'CREATE_PURCHASE_ORDER',
        'purchase_orders',
        "PO $poNumber issued for procurement {$proc['procurement_id']}"
    );

    http_response_code(201);
    echo json_encode([
        'success'   => true,
        'message'   => "Purchase Order $poNumber issued successfully.",
        'id'        => $newId,
        'po_number' => $poNumber,
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create purchase order.']);
    error_log("PMTS CreatePO Error: " . $e->getMessage());
}
