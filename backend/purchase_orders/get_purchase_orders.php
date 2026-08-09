<?php
// ============================================================
//  PMTS – GET /purchase_orders/get_purchase_orders.php
//  Query: ?procurement_id=5 or ?supplier_id=2 (optional filters)
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $authUser = requireAuth();
    $pdo      = getPDO();

    $procId     = (int) ($_GET['procurement_id'] ?? 0);
    $supplierId = (int) ($_GET['supplier_id']    ?? 0);

    $where  = [];
    $params = [];

    if ($procId) {
        $where[]  = "po.procurement_id = ?";
        $params[] = $procId;
    }
    if ($supplierId) {
        $where[]  = "po.supplier_id = ?";
        $params[] = $supplierId;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare(
        "SELECT po.*,
                p.procurement_id AS proc_ref_id,
                p.title          AS procurement_title,
                s.supplier_name,
                s.email          AS supplier_email
         FROM purchase_orders po
         LEFT JOIN procurements p ON p.id = po.procurement_id
         LEFT JOIN suppliers    s ON s.id = po.supplier_id
         $whereClause
         ORDER BY po.created_at DESC"
    );
    $stmt->execute($params);
    $pos = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $pos]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch purchase orders.']);
    error_log("PMTS GetPOs Error: " . $e->getMessage());
}
