<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email_helper.php';
require_once __DIR__ . '/committee_appointment_helper.php';

header('Content-Type: application/json');

function input_json() { return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data = []) { echo json_encode(array_merge(['success' => true], $data)); exit; }
function fail($message, $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'message' => $message]); exit; }

try {
    $pdo = getPDO();
    pmtsEnsureCommitteeLettersSchema($pdo);
    pmtsEnsureEmailLogSchema($pdo);

    $data = input_json();
    $letterId = (int)($data['id'] ?? 0);
    $action = trim((string)($data['action'] ?? 'opened_mail_client'));

    if ($letterId <= 0) {
        fail('Letter ID is required.');
    }

    if (!in_array($action, ['opened_mail_client', 'sent_manually'], true)) {
        fail('Invalid manual email action.');
    }

    $stmt = $pdo->prepare("SELECT l.*, p.procurement_id AS procurement_code, p.title, p.tender_number FROM committee_letters l LEFT JOIN procurements p ON p.id = l.procurement_id WHERE l.id = ?");
    $stmt->execute([$letterId]);
    $letter = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$letter) {
        fail('Committee letter not found.', 404);
    }

    $now = date('Y-m-d H:i:s');
    $status = $action === 'sent_manually' ? 'sent_manually' : 'mail_client_opened';
    $sentAt = $action === 'sent_manually' ? $now : ($letter['sent_at'] ?? null);
    $errorText = $action === 'sent_manually'
        ? null
        : 'Manual mailto link opened. PMTS cannot confirm delivery until the user clicks Mark Sent after sending from Gmail/Outlook.';

    $update = $pdo->prepare("UPDATE committee_letters SET sent_at = ?, last_email_attempt_at = ?, email_status = ?, email_error = ? WHERE id = ?");
    $update->execute([$sentAt, $now, $status, $errorText, $letterId]);

    $subject = pmtsBuildCommitteeEmailSubject([
        'procurement_id' => $letter['procurement_code'] ?? '',
        'title' => $letter['title'] ?? '',
        'tender_number' => $letter['tender_number'] ?? '',
    ], $letter['committee_type'] ?? '');

    $logResult = [
        'success' => $action === 'sent_manually',
        'message' => $action === 'sent_manually'
            ? 'Marked as sent manually after using mailto.'
            : 'Mail client opened through mailto. Waiting for manual send confirmation.',
    ];
    pmtsLogEmailAttempt($pdo, $letter['member_email'] ?? '', $subject, $logResult, 'committee_letters', $letterId);

    ok([
        'message' => $action === 'sent_manually'
            ? 'Manual email status saved as sent manually.'
            : 'Mail app status saved. Please click Send in Gmail/Outlook, then Mark Sent in PMTS.',
        'email_status' => $status,
        'sent_at' => $sentAt,
        'email_error' => $errorText,
    ]);
} catch (Throwable $e) {
    fail('Failed to update manual email status: ' . $e->getMessage(), 500);
}
