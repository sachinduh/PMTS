<?php
// ============================================================
// PMTS – Initial IT Admin bootstrap
// Creates the first IT Admin only when no active IT Admin exists.
// No default name, email, or password is stored in this file.
// The normal registration page can also create the first IT Admin.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../config/validation_helper.php';
require_once __DIR__ . '/../queries/user_queries.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $pdo = getPDO();
    pmtsEnsureRegistrationRoleColumns($pdo);
    pmtsEnsureAccountSecurityColumns($pdo);

    if (pmtsActiveItAdminCount($pdo) > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'An active IT Admin already exists. This endpoint will not reset or overwrite Admin credentials.',
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
        exit;
    }

    $fullName = trim((string)($input['full_name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');

    if ($fullName === '' || $email === '' || $password === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'full_name, email, and password are required.',
        ]);
        exit;
    }

    $nameError = pmtsValidateFullName($fullName);
    if ($nameError !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $nameError]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
        exit;
    }

    $passwordError = pmtsValidateStrongPassword($password);
    if ($passwordError !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $passwordError]);
        exit;
    }

    if (pmtsEmailExists($pdo, $email)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
        exit;
    }

    $newUserId = pmtsInsertRegisteredUser($pdo, [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => trim((string)($input['phone'] ?? '')),
        'nic' => trim((string)($input['nic'] ?? '')),
        'user_type' => 'Hospital Staff',
        'department' => trim((string)($input['department'] ?? 'Information Technology')),
        'organization' => trim((string)($input['organization'] ?? '')) ?: 'Badulla Hospital',
        'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        'role' => 'it_admin',
        'requested_role' => 'it_admin',
        'status' => 'active',
        'role_locked' => 1,
    ]);

    createAuditLog(
        $pdo,
        $newUserId,
        'CREATE_FIRST_IT_ADMIN',
        'auth',
        "First IT Admin account created for $email"
    );

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'First IT Admin created successfully. The account is active and can login now.',
        'data' => [
            'id' => $newUserId,
            'full_name' => $fullName,
            'email' => $email,
            'role' => 'it_admin',
            'status' => 'active',
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create first IT Admin.',
        'error' => $e->getMessage(),
    ]);
}
