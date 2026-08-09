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
    $procurementId = (int)($data['procurement_id'] ?? 0);
    $committeeType = pmtsNormalizeCommitteeType($data['committee_type'] ?? '');
    $chairmanUserId = (int)($data['chairman_user_id'] ?? 0);
    $memberUserIds = $data['member_user_ids'] ?? [];
    $sendEmail = !empty($data['send_email']);

    if ($procurementId <= 0) fail('Procurement ID is required.');
    if (!in_array($committeeType, ['BEC', 'Specification'], true)) fail('Committee type must be BEC or Specification.');
    if ($chairmanUserId <= 0) fail('Please select a chairman.');
    if (!is_array($memberUserIds)) $memberUserIds = [];

    $schedulePlannedDate = pmtsGetCommitteeTaskPlannedDate($pdo, $procurementId, $committeeType);
    $plannedDate = !empty($data['planned_date']) ? substr((string)$data['planned_date'], 0, 10) : $schedulePlannedDate;
    $letterDate = !empty($data['letter_date']) ? substr((string)$data['letter_date'], 0, 10) : ($plannedDate ?: date('Y-m-d'));
    if (!$plannedDate) $plannedDate = $letterDate;

    $memberUserIds = array_values(array_unique(array_filter(array_map('intval', $memberUserIds), fn($id) => $id > 0 && $id !== $chairmanUserId)));
    $selectedUserIds = array_values(array_unique(array_merge([$chairmanUserId], $memberUserIds)));

    $requiredRole = pmtsCommitteeRoleForType($committeeType);
    $otherCommitteeType = pmtsOtherCommitteeType($committeeType);

    $procStmt = $pdo->prepare("SELECT id, procurement_id, title, tender_number FROM procurements WHERE id = ?");
    $procStmt->execute([$procurementId]);
    $procurement = $procStmt->fetch(PDO::FETCH_ASSOC);
    if (!$procurement) fail('Procurement not found.', 404);

    $placeholders = implode(',', array_fill(0, count($selectedUserIds), '?'));
    $userStmt = $pdo->prepare("SELECT id, full_name, email, department, organization, role, status FROM users WHERE id IN ({$placeholders})");
    $userStmt->execute($selectedUserIds);
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    $usersById = [];
    foreach ($users as $user) {
        $usersById[(int)$user['id']] = $user;
    }

    foreach ($selectedUserIds as $selectedUserId) {
        if (!isset($usersById[$selectedUserId])) {
            fail('Selected user was not found in the users table.');
        }
        $user = $usersById[$selectedUserId];
        if (($user['status'] ?? '') !== 'active') {
            fail($user['full_name'] . ' is not an active user.');
        }
        if (($user['role'] ?? '') !== $requiredRole) {
            fail($user['full_name'] . ' cannot be selected. ' . getRoleMessage($committeeType));
        }
    }

    $otherStmt = $pdo->prepare("SELECT member_name FROM committee_letters WHERE procurement_id = ? AND committee_type = ? AND user_id = ? LIMIT 1");
    foreach ($selectedUserIds as $selectedUserId) {
        $otherStmt->execute([$procurementId, $otherCommitteeType, $selectedUserId]);
        $other = $otherStmt->fetch(PDO::FETCH_ASSOC);
        if ($other) {
            fail(($usersById[$selectedUserId]['full_name'] ?? 'Selected user') . " is already appointed to the {$otherCommitteeType} Committee for this procurement.");
        }
    }

    $pdo->beginTransaction();

    $deletePlaceholders = implode(',', array_fill(0, count($selectedUserIds), '?'));
    $deleteStmt = $pdo->prepare("DELETE FROM committee_letters WHERE procurement_id = ? AND committee_type = ? AND user_id IS NOT NULL AND user_id NOT IN ({$deletePlaceholders})");
    $deleteStmt->execute(array_merge([$procurementId, $committeeType], $selectedUserIds));

    $createdOrUpdated = [];
    $insertStmt = $pdo->prepare("INSERT INTO committee_letters (procurement_id, user_id, committee_type, committee_position, member_name, member_designation, member_email, letter_date, appointment_planned_date, letter_body, email_status, email_error) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'not_sent', NULL)");
    $updateStmt = $pdo->prepare("UPDATE committee_letters SET committee_position = ?, member_name = ?, member_designation = ?, member_email = ?, letter_date = ?, appointment_planned_date = ?, letter_body = ?, email_status = 'not_sent', email_error = NULL WHERE id = ?");
    $findStmt = $pdo->prepare("SELECT id FROM committee_letters WHERE procurement_id = ? AND committee_type = ? AND user_id = ? LIMIT 1");

    foreach ($selectedUserIds as $userId) {
        $user = $usersById[$userId];
        $position = $userId === $chairmanUserId ? 'Chairman' : 'Member';
        $designation = trim(($user['department'] ?? '') ?: ($user['organization'] ?? '') ?: $requiredRole);
        $body = pmtsBuildCommitteeLetterBody($procurement, $committeeType, $position, $plannedDate);

        $findStmt->execute([$procurementId, $committeeType, $userId]);
        $existing = $findStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $updateStmt->execute([$position, $user['full_name'], $designation, $user['email'], $letterDate, $plannedDate, $body, $existing['id']]);
            $letterId = (int)$existing['id'];
        } else {
            $insertStmt->execute([$procurementId, $userId, $committeeType, $position, $user['full_name'], $designation, $user['email'], $letterDate, $plannedDate, $body]);
            $letterId = (int)$pdo->lastInsertId();
        }

        $createdOrUpdated[] = [
            'id' => $letterId,
            'procurement_id' => $procurementId,
            'user_id' => $userId,
            'committee_type' => $committeeType,
            'committee_position' => $position,
            'member_name' => $user['full_name'],
            'member_designation' => $designation,
            'member_email' => $user['email'],
            'letter_date' => $letterDate,
            'appointment_planned_date' => $plannedDate,
            'letter_body' => $body,
            'email_status' => 'not_sent',
            'email_error' => null,
        ];
    }

    $emailResults = [];
    if ($sendEmail) {
        $subject = pmtsBuildCommitteeEmailSubject($procurement, $committeeType);
        $attemptAt = date('Y-m-d H:i:s');
        $emailUpdateStmt = $pdo->prepare("UPDATE committee_letters SET sent_at = ?, last_email_attempt_at = ?, email_status = ?, email_error = ? WHERE id = ?");

        foreach ($createdOrUpdated as &$letter) {
            $emailResult = pmtsSendEmail($letter['member_email'], $subject, pmtsBuildCommitteeEmailBody($letter, $procurement));
            pmtsLogEmailAttempt($pdo, $letter['member_email'], $subject, $emailResult, 'committee_letters', (int)$letter['id']);
            $status = $emailResult['success'] ? 'sent' : 'failed';
            $sentAt = $emailResult['success'] ? $attemptAt : null;
            $errorText = $emailResult['success'] ? null : ($emailResult['message'] ?? 'Unknown email error');
            $emailUpdateStmt->execute([$sentAt, $attemptAt, $status, $errorText, $letter['id']]);
            $letter['sent_at'] = $sentAt;
            $letter['last_email_attempt_at'] = $attemptAt;
            $letter['email_status'] = $status;
            $letter['email_error'] = $errorText;
            $emailResults[] = $letter;
        }
        unset($letter);
    }

    $pdo->commit();

    ok([
        'message' => $sendEmail
            ? pmtsBuildCommitteeEmailSummaryMessage($emailResults)
            : 'Committee appointments saved successfully.',
        'appointments' => $createdOrUpdated,
        'email_results' => $emailResults,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail('Failed to save committee appointments: ' . $e->getMessage(), 500);
}


function pmtsBuildCommitteeEmailSummaryMessage(array $emailResults): string
{
    $sent = 0;
    $failed = 0;
    foreach ($emailResults as $result) {
        if (($result['email_status'] ?? '') === 'sent') $sent++;
        if (($result['email_status'] ?? '') === 'failed') $failed++;
    }
    if ($failed > 0) {
        return "Committee appointments saved. Email result: {$sent} sent, {$failed} failed. Check Mail Status / email_error for the reason.";
    }
    return "Committee appointments saved and emails sent successfully to {$sent} member(s).";
}

function getRoleMessage(string $committeeType): string
{
    return pmtsNormalizeCommitteeType($committeeType) === 'BEC'
        ? 'Only active users with role bec_member can be selected for BEC.'
        : 'Only active users with role specification_committee can be selected for Specification Committee.';
}
