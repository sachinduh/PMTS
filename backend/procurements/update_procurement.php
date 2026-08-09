<?php
// ============================================================
//  PMTS – PUT /procurements/update_procurement.php
//  Update procurement details (procurement_officer, it_admin)
//  Cannot change procurement_type after creation.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use PUT or POST.']);
    exit;
}

try {
    $authUser = requireRole(['procurement_officer', 'it_admin']);
    $input    = json_decode(file_get_contents('php://input'), true);

    $id = (int) ($input['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Procurement id is required.']);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, procurement_id, current_status, created_by FROM procurements WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $proc = $stmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    // Only creator or IT Admin can update
    if ((int) $proc['created_by'] !== (int) $authUser['sub'] && $authUser['role'] !== 'it_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only update procurements you created.']);
        exit;
    }

    // Lock edits on completed/cancelled procurements
    if (in_array($proc['current_status'], ['completed', 'cancelled'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Cannot edit a {$proc['current_status']} procurement."]);
        exit;
    }

    $allowed = ['title', 'tender_number', 'file_name', 'category', 'estimated_amount', 'received_date', 'description', 'priority'];
    $set     = [];
    $params  = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $set[]    = "$f = ?";
            $params[] = $input[$f] !== '' ? $input[$f] : null;
        }
    }

    if (empty($set)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No updatable fields provided.']);
        exit;
    }

    $params[] = $id;
    $pdo->prepare("UPDATE procurements SET " . implode(', ', $set) . " WHERE id = ?")->execute($params);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_PROCUREMENT',
        'procurements',
        "Updated procurement {$proc['procurement_id']} (ID: $id)"
    );

    echo json_encode([
        'success' => true,
        'message' => "Procurement {$proc['procurement_id']} updated successfully.",
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update procurement.']);
    error_log("PMTS UpdateProcurement Error: " . $e->getMessage());
}
