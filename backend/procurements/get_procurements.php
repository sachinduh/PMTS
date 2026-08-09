<?php
// ============================================================
//  PMTS – GET /procurements/get_procurements.php
//  Returns paginated list of procurements
//  Query params: ?page=1&limit=20&status=&type=&priority=&search=
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/tracking_helper.php';

function pmtsProcurementColumnExists(PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'procurements' AND COLUMN_NAME = ?"
    );
    $stmt->execute([$column]);
    return (int) $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $authUser = requireAuth();
    $pdo      = getPDO();
    $hasFileName = pmtsProcurementColumnExists($pdo, 'file_name');
    $fileNameSelect = $hasFileName ? 'p.file_name,' : 'NULL AS file_name,';

    $page     = max(1, (int) ($_GET['page']   ?? 1));
    $limit    = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset   = ($page - 1) * $limit;
    $status   = trim($_GET['status']   ?? '');
    $type     = trim($_GET['type']     ?? '');
    $priority = trim($_GET['priority'] ?? '');
    $search   = trim($_GET['search']   ?? '');

    $where  = [];
    $params = [];

    // Non-admin roles: procurement_officer sees all; others see only relevant procurements
    // Full access: it_admin, procurement_officer, director, accountant
    // Restricted: bec_member see only assigned ones (simplified: show all for now)

    if ($status !== '') {
        $where[]  = "p.current_status = ?";
        $params[] = $status;
    }
    if ($type !== '') {
        $where[]  = "p.procurement_type = ?";
        $params[] = $type;
    }
    if ($priority !== '') {
        $where[]  = "p.priority = ?";
        $params[] = $priority;
    }
    if ($search !== '') {
        $where[]  = $hasFileName
            ? "(p.title LIKE ? OR p.procurement_id LIKE ? OR p.tender_number LIKE ? OR p.file_name LIKE ?)"
            : "(p.title LIKE ? OR p.procurement_id LIKE ? OR p.tender_number LIKE ?)";
        $like     = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        if ($hasFileName) {
            $params[] = $like;
        }
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM procurements p $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $dataParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare(
        "SELECT p.*,
                {$fileNameSelect}
                p.current_status AS status,
                u.full_name AS created_by_name,
                u.email     AS created_by_email
         FROM procurements p
         LEFT JOIN users u ON u.id = p.created_by
         $whereClause
         ORDER BY p.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($dataParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $scheduleTasksByProcurement = pmtsLoadScheduleTasksForProcurements($pdo, array_column($rows, 'id'));
    $procurements = array_map(function ($row) use ($scheduleTasksByProcurement) {
        return pmtsEnrichProcurementTracking($row, $scheduleTasksByProcurement[(int) $row['id']] ?? []);
    }, $rows);

    echo json_encode([
        'success'      => true,
        'data'         => $procurements,
        'procurements' => $procurements,
        'meta'    => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch procurements.']);
    error_log("PMTS GetProcurements Error: " . $e->getMessage());
}
