<?php
// ============================================================
// PMTS – GET /specifications/get_specifications.php
// Returns only procurements/files currently under Specification
// Committee according to the active schedule task.
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

function pmtsSpecTableExists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'specification_reviews'"
    );
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

try {
    requireAuth();
    $pdo = getPDO();

    $stmt = $pdo->query(
        "SELECT p.id,
                p.procurement_id,
                p.title,
                p.tender_number,
                p.file_name,
                p.procurement_type,
                p.category,
                p.estimated_amount,
                p.current_status,
                p.priority,
                p.created_at,
                p.updated_at,
                u.full_name AS requested_by
         FROM procurements p
         LEFT JOIN users u ON u.id = p.created_by
         WHERE p.current_status NOT IN ('cancelled')
         ORDER BY p.created_at DESC"
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tasksByProcurement = pmtsLoadScheduleTasksForProcurements($pdo, array_column($rows, 'id'));

    $latestReviews = [];
    if (pmtsSpecTableExists($pdo)) {
        $reviewStmt = $pdo->query(
            "SELECT sr.*, u.full_name AS reviewer_name
             FROM specification_reviews sr
             LEFT JOIN users u ON u.id = sr.reviewer_id
             INNER JOIN (
               SELECT procurement_id, MAX(id) AS max_id
               FROM specification_reviews
               GROUP BY procurement_id
             ) latest ON latest.max_id = sr.id"
        );
        foreach ($reviewStmt->fetchAll(PDO::FETCH_ASSOC) as $review) {
            $latestReviews[(int) $review['procurement_id']] = $review;
        }
    }

    $specifications = [];
    foreach ($rows as $row) {
        $row = pmtsEnrichProcurementTracking($row, $tasksByProcurement[(int) $row['id']] ?? []);
        if (($row['current_stage_key'] ?? '') !== 'specification_committee') {
            continue;
        }

        $review = $latestReviews[(int) $row['id']] ?? null;
        $row['spec_id'] = $row['id'];
        $row['status'] = $review['status'] ?? 'pending';
        $row['review_notes'] = $review['review_notes'] ?? null;
        $row['reviewer_name'] = $review['reviewer_name'] ?? null;
        $row['reviewed_at'] = $review['reviewed_at'] ?? null;
        $specifications[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $specifications,
        'specifications' => $specifications,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch specification queue.']);
    error_log('PMTS GetSpecifications Error: ' . $e->getMessage());
}
