<?php
// ============================================================
// PMTS – GET /evaluations/get_financial_approvals.php
// Query optional: ?procurement_id=5
// Without procurement_id: returns accountant financial review queue.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../procurements/tracking_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    requireAuth();
    $procId = (int) ($_GET['procurement_id'] ?? 0);
    $pdo = getPDO();

    if ($procId) {
        $stmt = $pdo->prepare(
            "SELECT fa.*, u.full_name AS accountant_name
             FROM financial_approvals fa
             LEFT JOIN users u ON u.id = fa.accountant_id
             WHERE fa.procurement_id = ?
             ORDER BY fa.id DESC"
        );
        $stmt->execute([$procId]);
        $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $approvals, 'approvals' => $approvals]);
        exit;
    }

    $stmt = $pdo->query(
        "SELECT p.id,
                p.procurement_id,
                p.title,
                p.tender_number,
                p.procurement_type,
                p.category,
                p.estimated_amount,
                p.current_status,
                p.priority,
                p.created_at,
                p.updated_at,
                u.full_name AS requested_by,
                latest.approval_status,
                latest.budget_available,
                latest.approved_amount,
                latest.comments,
                latest.approved_at,
                latest.accountant_name
         FROM procurements p
         LEFT JOIN users u ON u.id = p.created_by
         LEFT JOIN (
             SELECT fa1.*, acc.full_name AS accountant_name
             FROM financial_approvals fa1
             LEFT JOIN users acc ON acc.id = fa1.accountant_id
             INNER JOIN (
                 SELECT procurement_id, MAX(id) AS max_id
                 FROM financial_approvals
                 GROUP BY procurement_id
             ) latest_ids ON latest_ids.max_id = fa1.id
         ) latest ON latest.procurement_id = p.id
         WHERE p.current_status NOT IN ('cancelled')
         ORDER BY
           CASE WHEN p.current_status = 'financial_evaluation' THEN 0 ELSE 1 END,
           p.created_at DESC"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tasksByProcurement = pmtsLoadScheduleTasksForProcurements($pdo, array_column($rows, 'id'));
    $approvals = [];
    foreach ($rows as $row) {
        $row = pmtsEnrichProcurementTracking($row, $tasksByProcurement[(int) $row['id']] ?? []);
        if (($row['current_stage_key'] ?? '') !== 'accountant' && ($row['current_status'] ?? '') !== 'financial_evaluation') {
            continue;
        }
        $row['status'] = $row['approval_status'] ?: 'pending';
        $row['financial_status'] = $row['approval_status'] ?: 'pending';
        $approvals[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $approvals, 'approvals' => $approvals]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch financial approvals.']);
    error_log('PMTS GetFinancialApprovals Error: ' . $e->getMessage());
}
