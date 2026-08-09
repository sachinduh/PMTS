<?php
// ============================================================
// PMTS – GET /evaluations/get_bec_evaluations.php
// Query optional: ?procurement_id=5
// Returns BEC evaluation queue/results.
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
            "SELECT be.*, u.full_name AS evaluator_name
             FROM bec_evaluations be
             LEFT JOIN users u ON u.id = be.evaluator_id
             WHERE be.procurement_id = ?
             ORDER BY be.id DESC"
        );
        $stmt->execute([$procId]);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $evaluations, 'evaluations' => $evaluations]);
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
                latest.evaluation_status,
                latest.bid_amount,
                latest.compliance,
                latest.remarks,
                latest.evaluated_at,
                latest.evaluator_name
         FROM procurements p
         LEFT JOIN users u ON u.id = p.created_by
         LEFT JOIN (
             SELECT be1.*, ev.full_name AS evaluator_name
             FROM bec_evaluations be1
             LEFT JOIN users ev ON ev.id = be1.evaluator_id
             INNER JOIN (
                 SELECT procurement_id, MAX(id) AS max_id
                 FROM bec_evaluations
                 GROUP BY procurement_id
             ) latest_ids ON latest_ids.max_id = be1.id
         ) latest ON latest.procurement_id = p.id
         WHERE p.current_status NOT IN ('cancelled')
         ORDER BY
           CASE WHEN p.current_status = 'bid_evaluation' THEN 0 ELSE 1 END,
           p.created_at DESC"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tasksByProcurement = pmtsLoadScheduleTasksForProcurements($pdo, array_column($rows, 'id'));
    $evaluations = [];
    foreach ($rows as $row) {
        $row = pmtsEnrichProcurementTracking($row, $tasksByProcurement[(int) $row['id']] ?? []);
        if (($row['current_stage_key'] ?? '') !== 'bec') {
            continue;
        }
        $row['status'] = $row['evaluation_status'] ?: 'pending';
        $evaluations[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $evaluations, 'evaluations' => $evaluations]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch BEC evaluations.']);
    error_log('PMTS GetBECEvaluations Error: ' . $e->getMessage());
}
