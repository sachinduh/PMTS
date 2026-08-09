<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_helper.php';

header('Content-Type: application/json');

function input_json() { return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data = []) { echo json_encode(array_merge(['success' => true], $data)); exit; }
function fail($message, $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'message' => $message]); exit; }

try {
    $data = input_json();
    $recipient = trim($data['recipient_email'] ?? '');
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        fail('Valid recipient_email is required.');
    }

    $subject = 'PMTS SMTP Test Email';
    $body = "This is a PMTS SMTP test email.\n\nIf you received this message, SMTP/PHPMailer is configured correctly.\n\nTime: " . date('Y-m-d H:i:s');
    $result = pmtsSendEmail($recipient, $subject, $body);

    $pdo = getPDO();
    pmtsLogEmailAttempt($pdo, $recipient, $subject, $result, 'smtp_test', null);

    ok([
        'message' => $result['success'] ? 'SMTP test email sent successfully.' : 'SMTP test email failed. Check email_error/message.',
        'email_status' => $result['success'] ? 'sent' : 'failed',
        'email_error' => $result['success'] ? null : ($result['message'] ?? 'Unknown email error'),
        'smtp_status' => pmtsSmtpStatus(),
    ]);
} catch (Throwable $e) {
    fail('SMTP test failed: ' . $e->getMessage(), 500);
}
