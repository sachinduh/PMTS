<?php
// ============================================================
//  PMTS – POST /notifications/mark_as_read.php
//  Mark one or all notifications as read
//  Body: { "notification_id": 5 }  ← specific notification
//  Body: { "mark_all": true }       ← all user notifications
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireAuth();
    $input    = json_decode(file_get_contents('php://input'), true);
    $pdo      = getPDO();
    $userId   = (int) $authUser['sub'];

    $markAll        = (bool) ($input['mark_all']        ?? false);
    $notificationId = (int)  ($input['notification_id'] ?? 0);

    if ($markAll) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $count = $stmt->rowCount();
        echo json_encode([
            'success' => true,
            'message' => "All $count notification(s) marked as read.",
        ]);
    } elseif ($notificationId) {
        // Verify ownership
        $check = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ? LIMIT 1");
        $check->execute([$notificationId, $userId]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Notification not found.']);
            exit;
        }
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notificationId]);
        echo json_encode(['success' => true, 'message' => 'Notification marked as read.']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Provide notification_id or set mark_all: true.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification(s) as read.']);
    error_log("PMTS MarkRead Error: " . $e->getMessage());
}
