<?php
// ============================================================
//  PMTS – POST /help/create_ticket.php
//  User submits a help/support ticket
//  Body: { "subject": "...", "message": "..." }
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
    $authUser = requireAuth();
    $input    = json_decode(file_get_contents('php://input'), true);

    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');

    if (!$subject || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'subject and message are required.']);
        exit;
    }

    if (strlen($subject) > 255) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subject must be 255 characters or less.']);
        exit;
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare(
        "INSERT INTO help_support (user_id, subject, message, status)
         VALUES (?, ?, ?, 'open')"
    );
    $stmt->execute([$authUser['sub'], $subject, $message]);
    $ticketId = (int) $pdo->lastInsertId();

    // Notify IT Admin(s)
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'it_admin' AND status = 'active'")->fetchAll();
    $notifStmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES (?, 'New Support Ticket', ?, 'info')"
    );
    foreach ($admins as $admin) {
        $notifStmt->execute([
            $admin['id'],
            "New ticket #$ticketId from {$authUser['email']}: $subject",
        ]);
    }

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'CREATE_TICKET',
        'help',
        "Support ticket #$ticketId created: $subject"
    );

    http_response_code(201);
    echo json_encode([
        'success'   => true,
        'message'   => "Support ticket #$ticketId submitted. IT Admin will respond shortly.",
        'ticket_id' => $ticketId,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit support ticket.']);
    error_log("PMTS CreateTicket Error: " . $e->getMessage());
}
