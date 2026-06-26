<?php
// ============================================================
//  PMTS – GET /help/get_tickets.php
//  IT Admin sees all tickets; regular users see their own
//  Query: ?status=open&page=1&limit=20
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

    $page   = max(1, (int) ($_GET['page']   ?? 1));
    $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $status = trim($_GET['status'] ?? '');

    $where  = [];
    $params = [];

    // IT Admin sees all; others see only their own
    if ($authUser['role'] !== 'it_admin') {
        $where[]  = "hs.user_id = ?";
        $params[] = $authUser['sub'];
    }

    if ($status !== '') {
        $where[]  = "hs.status = ?";
        $params[] = $status;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM help_support hs $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $dataParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare(
        "SELECT hs.*, u.full_name AS user_name, u.email AS user_email, u.department
         FROM help_support hs
         LEFT JOIN users u ON u.id = hs.user_id
         $whereClause
         ORDER BY hs.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($dataParams);
    $tickets = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $tickets,
        'meta'    => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch support tickets.']);
    error_log("PMTS GetTickets Error: " . $e->getMessage());
}
