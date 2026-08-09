<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $authUser = requireAuth();
    $pdo = getPDO();

    $userId = (int) ($authUser['sub'] ?? $authUser['id'] ?? 0);
    $unreadOnly = filter_var($_GET['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;

    $where = ['user_id = ?'];
    $params = [$userId];

    if ($unreadOnly) {
        $where[] = 'is_read = 0';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $unreadStmt->execute([$userId]);
    $unreadCount = (int) $unreadStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, user_id, title, message, type, is_read, created_at
         FROM notifications
         $whereClause
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([...$params, $limit, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'data' => $notifications,
        'unread_count' => $unreadCount,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load notifications.',
        'error' => $e->getMessage(),
    ]);
}
