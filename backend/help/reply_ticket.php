<?php
// ============================================================
//  PMTS – POST /help/reply_ticket.php
//  IT Admin replies to a support ticket
//  Body: { "ticket_id": 3, "reply": "...", "status": "resolved" }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['it_admin']);
    $input    = json_decode(file_get_contents('php://input'), true);

    $ticketId = (int)   ($input['ticket_id'] ?? 0);
    $reply    = trim($input['reply']     ?? '');
    $status   = trim($input['status']   ?? 'in_progress');

    if (!$ticketId || !$reply) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ticket_id and reply are required.']);
        exit;
    }

    $validStatuses = ['open', 'in_progress', 'resolved', 'closed'];
    if (!in_array($status, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status. Use: open, in_progress, resolved, closed.']);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("SELECT id, user_id, subject, status FROM help_support WHERE id = ? LIMIT 1");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Support ticket not found.']);
        exit;
    }

    $pdo->prepare(
        "UPDATE help_support SET reply = ?, status = ? WHERE id = ?"
    )->execute([$reply, $status, $ticketId]);

    // Notify the ticket owner
    $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES (?, 'Support Ticket Reply', ?, 'info')"
    )->execute([
        $ticket['user_id'],
        "Your support ticket '{$ticket['subject']}' has been replied to. Status: $status.",
    ]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'REPLY_TICKET',
        'help',
        "Replied to ticket #$ticketId. Status set to: $status"
    );

    echo json_encode([
        'success' => true,
        'message' => "Reply sent for ticket #$ticketId. Status updated to: $status",
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to reply to ticket.']);
    error_log("PMTS ReplyTicket Error: " . $e->getMessage());
}
