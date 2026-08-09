<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../queries/user_queries.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $email    = strtolower(trim($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if (empty($email) || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }

    // Never allow role to be supplied during login. Role must come from database only.
    if (array_key_exists('role', $input) || array_key_exists('requested_role', $input) || array_key_exists('is_admin', $input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Login cannot include role data. The system verifies your real role from the database after password login.',
            'code' => 'ROLE_DATA_NOT_ALLOWED',
        ]);
        exit;
    }

    $pdo = getPDO();
    ensureAccountSecurityColumns($pdo);

    $user = pmtsFetchUserByEmailForLogin($pdo, $email);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Block locked accounts before password verification.
    if ((int) ($user['account_locked'] ?? 0) === 1) {
        http_response_code(423);
        echo json_encode([
            'success' => false,
            'message' => 'Your account is locked after too many failed password attempts. Please contact IT Admin to unblock it.',
            'code'    => 'ACCOUNT_LOCKED',
        ]);
        exit;
    }

    if ($user['status'] === 'pending') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Your account is pending approval by IT Admin. Please wait for activation.',
        ]);
        exit;
    }

    if ($user['status'] === 'rejected') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Your account has been removed/rejected. Please contact the IT Admin.',
        ]);
        exit;
    }

    // IT Admin dashboard/API access requires a real approved role in the database.
    // A requested role, HTTP request value, or browser storage edit is never enough.
    if (($user['role'] ?? '') === 'it_admin') {
        if ((int) ($user['role_locked'] ?? 0) !== 1) {
            http_response_code(403);
            createAuditLog($pdo, $user['id'], 'BLOCK_UNLOCKED_IT_ADMIN_LOGIN', 'auth', "Blocked unlocked IT Admin login: {$user['email']}");
            echo json_encode([
                'success' => false,
                'message' => 'IT Admin login is blocked because this account was not approved and role-locked by the system.',
                'code'    => 'UNLOCKED_ADMIN_ROLE_BLOCKED',
            ]);
            exit;
        }

        if (!pmtsIsPrimaryItAdmin($pdo, (int) $user['id'])) {
            http_response_code(403);
            createAuditLog($pdo, $user['id'], 'BLOCK_EXTRA_IT_ADMIN_LOGIN', 'auth', "Blocked duplicate IT Admin login: {$user['email']}");
            echo json_encode([
                'success' => false,
                'message' => 'Only one IT Admin account is allowed to login. Please login with the first registered IT Admin account.',
                'code'    => 'EXTRA_IT_ADMIN_BLOCKED',
            ]);
            exit;
        }
    }

    if (!password_verify($password, $user['password'])) {
        $failedAttempts = min(LOGIN_LOCK_MAX_ATTEMPTS, ((int) ($user['failed_login_attempts'] ?? 0)) + 1);
        $remainingAttempts = max(0, LOGIN_LOCK_MAX_ATTEMPTS - $failedAttempts);
        $shouldLock = $failedAttempts >= LOGIN_LOCK_MAX_ATTEMPTS;

        pmtsRecordFailedLogin($pdo, (int) $user['id'], $failedAttempts, $shouldLock);

        if ($shouldLock) {
            createAuditLog($pdo, $user['id'], 'ACCOUNT_LOCKED', 'auth', "Account locked after 3 failed login attempts: {$user['email']}");

            http_response_code(423);
            echo json_encode([
                'success' => false,
                'message' => 'Account locked after 3 failed password attempts. IT Admin must unblock this account.',
                'code'    => 'ACCOUNT_LOCKED',
            ]);
            exit;
        }

        http_response_code(401);
        createAuditLog($pdo, $user['id'], 'LOGIN_FAILED', 'auth', "Failed login attempt {$failedAttempts}/" . LOGIN_LOCK_MAX_ATTEMPTS . " for: {$user['email']}");
        echo json_encode([
            'success' => false,
            'message' => "Invalid email or password. Attempts remaining before lock: {$remainingAttempts}.",
            'code'    => 'LOGIN_FAILED',
        ]);
        exit;
    }

    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is not active. Contact IT Admin.']);
        exit;
    }

    pmtsResetFailedLogin($pdo, (int) $user['id']);

    // Token role is always the verified database role, never frontend/request data.
    $token = generateJWT((int) $user['id'], $user['role'], $user['email']);

    createAuditLog($pdo, $user['id'], 'LOGIN', 'auth', "User logged in: {$user['email']} (role: {$user['role']})");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'token'   => $token,
        'user'    => [
            'id'                    => (int) $user['id'],
            'full_name'             => $user['full_name'],
            'email'                 => $user['email'],
            'phone'                 => $user['phone'],
            'nic'                   => $user['nic'],
            'user_type'             => $user['user_type'],
            'department'            => $user['department'],
            'organization'          => $user['organization'],
            'profile_picture'      => $user['profile_picture'] ?? null,
            'role'                  => $user['role'],
            'requested_role'        => $user['requested_role'] ?? null,
            'role_locked'           => (int) ($user['role_locked'] ?? 0),
            'status'                => $user['status'],
            'failed_login_attempts' => 0,
            'account_locked'        => 0,
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Login failed. Please try again.']);
    error_log("PMTS Login Error: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    error_log("PMTS Login Exception: " . $e->getMessage());
}
