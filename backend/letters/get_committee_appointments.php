<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email_helper.php';
require_once __DIR__ . '/committee_appointment_helper.php';

header('Content-Type: application/json');

function ok($data = []) { echo json_encode(array_merge(['success' => true], $data)); exit; }
function fail($message, $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'message' => $message]); exit; }

try {
    $pdo = getPDO();
    pmtsEnsureCommitteeLettersSchema($pdo);

    $procurementId = (int)($_GET['procurement_id'] ?? 0);
    $committeeType = pmtsNormalizeCommitteeType($_GET['committee_type'] ?? '');

    if ($procurementId <= 0) fail('Procurement ID is required.');
    if (!in_array($committeeType, ['BEC', 'Specification'], true)) fail('Committee type must be BEC or Specification.');

    $role = pmtsCommitteeRoleForType($committeeType);
    $otherCommitteeType = pmtsOtherCommitteeType($committeeType);
    $plannedDate = pmtsGetCommitteeTaskPlannedDate($pdo, $procurementId, $committeeType);

    $procStmt = $pdo->prepare("SELECT id, procurement_id, title, tender_number FROM procurements WHERE id = ?");
    $procStmt->execute([$procurementId]);
    $procurement = $procStmt->fetch(PDO::FETCH_ASSOC);
    if (!$procurement) fail('Procurement not found.', 404);

    $activeRoleStmt = $pdo->prepare("SELECT id, full_name, email, department, organization, role, status
        FROM users
        WHERE status = 'active'
          AND role = ?
        ORDER BY full_name ASC");
    $activeRoleStmt->execute([$role]);
    $activeRoleUsers = $activeRoleStmt->fetchAll(PDO::FETCH_ASSOC);

    $blockedStmt = $pdo->prepare("SELECT DISTINCT u.id, u.full_name, u.email, u.department, u.organization, u.role, u.status
        FROM users u
        INNER JOIN committee_letters cl ON cl.user_id = u.id
        WHERE u.status = 'active'
          AND u.role = ?
          AND cl.procurement_id = ?
          AND cl.committee_type = ?
        ORDER BY u.full_name ASC");
    $blockedStmt->execute([$role, $procurementId, $otherCommitteeType]);
    $blockedCandidates = $blockedStmt->fetchAll(PDO::FETCH_ASSOC);
    $blockedIds = array_map(fn($user) => (int)$user['id'], $blockedCandidates);

    // Main selectable list: correct role + active + not already appointed to the opposite committee for this procurement.
    $candidates = array_values(array_filter($activeRoleUsers, fn($user) => !in_array((int)$user['id'], $blockedIds, true)));

    $pendingRequestedCandidates = [];
    $hasRequestedRoleStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'requested_role'");
    $hasRequestedRoleStmt->execute();
    if ((int)$hasRequestedRoleStmt->fetchColumn() > 0) {
        $pendingStmt = $pdo->prepare("SELECT id, full_name, email, department, organization, role, requested_role, status
            FROM users
            WHERE requested_role = ?
              AND (status <> 'active' OR role <> ?)
            ORDER BY full_name ASC");
        $pendingStmt->execute([$role, $role]);
        $pendingRequestedCandidates = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $committeeLabel = $committeeType === 'BEC' ? 'BEC Member' : 'Specification Committee Member';
    $emptyMessage = '';
    if (count($candidates) === 0) {
        if (count($activeRoleUsers) === 0 && count($pendingRequestedCandidates) > 0) {
            $emptyMessage = "There are users who requested the {$committeeLabel} role, but they are not active with users.role = '{$role}' yet. Approve them in IT Admin > User Management, or update their users.role and users.status in SQL.";
        } elseif (count($activeRoleUsers) === 0) {
            $emptyMessage = "No active users were found with users.role = '{$role}'. The dropdown only shows active users with this exact role value.";
        } elseif (count($blockedCandidates) > 0) {
            $emptyMessage = "Active {$committeeLabel} users exist, but they are already appointed to the opposite committee for this procurement, so they are blocked. Remove the incorrect opposite-committee appointment record or select another user.";
        } else {
            $emptyMessage = "No eligible users are available for this committee selection.";
        }
    }

    $letterStmt = $pdo->prepare("SELECT id, procurement_id, user_id, committee_type, committee_position, member_name, member_designation, member_email, letter_date, appointment_planned_date, letter_body, sent_at, last_email_attempt_at, email_status, email_error, created_at FROM committee_letters WHERE procurement_id = ? AND committee_type = ? ORDER BY FIELD(committee_position, 'Chairman', 'Member'), member_name ASC");
    $letterStmt->execute([$procurementId, $committeeType]);
    $appointments = $letterStmt->fetchAll(PDO::FETCH_ASSOC);

    ok([
        'procurement' => $procurement,
        'committee_type' => $committeeType,
        'role' => $role,
        'excluded_committee_type' => $otherCommitteeType,
        'planned_date' => $plannedDate,
        'candidates' => $candidates,
        'appointments' => $appointments,
        'blocked_candidates' => $blockedCandidates,
        'pending_requested_candidates' => $pendingRequestedCandidates,
        'empty_message' => $emptyMessage,
        'smtp_status' => pmtsSmtpStatus(),
        'role_summary' => [
            'active_required_role_count' => count($activeRoleUsers),
            'eligible_count' => count($candidates),
            'blocked_by_opposite_committee_count' => count($blockedCandidates),
            'pending_requested_role_count' => count($pendingRequestedCandidates),
        ],
    ]);
} catch (Throwable $e) {
    fail('Failed to load committee appointment details: ' . $e->getMessage(), 500);
}
