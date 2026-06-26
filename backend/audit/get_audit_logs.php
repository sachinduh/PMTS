<?php
// ============================================================
//  PMTS – GET /audit/get_audit_logs.php
//  Returns paginated audit trail (IT Admin only)
//  Query: ?page=1&limit=50&user_id=&module=&action=&date_from=&date_to=
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
    $authUser = requireRole(['it_admin']);
    $pdo      = getPDO();

    $page     = max(1, (int) ($_GET['page']   ?? 1));
    $limit    = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset   = ($page - 1) * $limit;
    $userId   = (int)   ($_GET['user_id']   ?? 0);
    $module   = trim($_GET['module']   ?? '');
    $action   = trim($_GET['action']   ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo   = trim($_GET['date_to']   ?? '');
    $search   = trim($_GET['search']   ?? '');

    $where  = [];
    $params = [];

    if ($userId) {
        $where[]  = "al.user_id = ?";
        $params[] = $userId;
    }
    if ($module !== '') {
        $where[]  = "al.module = ?";
        $params[] = $module;
    }
    if ($action !== '') {
        $where[]  = "al.action = ?";
        $params[] = $action;
    }
    if ($dateFrom !== '') {
        $where[]  = "DATE(al.created_at) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[]  = "DATE(al.created_at) <= ?";
        $params[] = $dateTo;
    }
    if ($search !== '') {
        $where[]  = "al.description LIKE ?";
        $params[] = "%$search%";
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $dataParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare(
        "SELECT al.*, u.full_name AS user_name, u.role AS user_role
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         $whereClause
         ORDER BY al.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($dataParams);
    $logs = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $logs,
        'meta'    => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch audit logs.']);
    error_log("PMTS GetAuditLogs Error: " . $e->getMessage());
}
