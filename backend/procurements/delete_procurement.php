<?php
// ============================================================
//  PMTS – DELETE /procurements/delete_procurement.php
//  Delete a procurement (it_admin only, only draft status)
//  Body: { "id": 5 }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use DELETE or POST.']);
    exit;
}

try {
    $authUser = requireRole(['it_admin']);
    $input    = json_decode(file_get_contents('php://input'), true);
    $id       = (int) ($input['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Procurement id is required.']);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, procurement_id, current_status, title FROM procurements WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $proc = $stmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    // Safety: only allow deletion of draft procurements
    if ($proc['current_status'] !== 'draft') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Only 'draft' procurements can be deleted. Current status: {$proc['current_status']}.",
        ]);
        exit;
    }

    // ON DELETE CASCADE will remove related schedule, history, etc.
    $pdo->prepare("DELETE FROM procurements WHERE id = ?")->execute([$id]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'DELETE_PROCUREMENT',
        'procurements',
        "Deleted procurement {$proc['procurement_id']}: {$proc['title']}"
    );

    echo json_encode([
        'success' => true,
        'message' => "Procurement '{$proc['procurement_id']}' deleted permanently.",
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete procurement.']);
    error_log("PMTS DeleteProcurement Error: " . $e->getMessage());
}
