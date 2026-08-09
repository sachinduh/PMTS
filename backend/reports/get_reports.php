<?php
// ============================================================
//  PMTS – GET /reports/get_reports.php
//  List saved reports with pagination
//  Query: ?page=1&limit=20&report_type=
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
    $authUser   = requireRole(['it_admin', 'director', 'procurement_officer', 'accountant']);
    $pdo        = getPDO();
    $page       = max(1, (int) ($_GET['page']   ?? 1));
    $limit      = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset     = ($page - 1) * $limit;
    $reportType = trim($_GET['report_type'] ?? '');
    $withData   = (bool) ($_GET['with_data'] ?? false);

    $where  = [];
    $params = [];

    if ($reportType !== '') {
        $where[]  = "r.report_type = ?";
        $params[] = $reportType;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reports r $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $dataField  = $withData ? "r.report_data," : "";
    $dataParams = array_merge($params, [$limit, $offset]);

    $stmt = $pdo->prepare(
        "SELECT r.id, r.report_title, r.report_type, $dataField r.created_at,
                u.full_name AS generated_by_name
         FROM reports r
         LEFT JOIN users u ON u.id = r.generated_by
         $whereClause
         ORDER BY r.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($dataParams);
    $reports = $stmt->fetchAll();

    // Parse report_data JSON if included
    if ($withData) {
        foreach ($reports as &$report) {
            if (isset($report['report_data'])) {
                $report['report_data'] = json_decode($report['report_data'], true);
            }
        }
        unset($report);
    }

    echo json_encode([
        'success' => true,
        'data'    => $reports,
        'meta'    => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch reports.']);
    error_log("PMTS GetReports Error: " . $e->getMessage());
}
