<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../config/notification_helper.php';
require_once __DIR__ . '/../config/validation_helper.php';
require_once __DIR__ . '/../queries/user_queries.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

try {
    $pdo = getPDO();
    pmtsEnsureRegistrationRoleColumns($pdo);
    pmtsEnsureAccountSecurityColumns($pdo);

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input.'
        ]);
        exit;
    }

    $required = ['full_name', 'email', 'password', 'requested_role'];

    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Field '$field' is required."
            ]);
            exit;
        }
    }

    $fullName      = trim($input['full_name']);
    $email         = strtolower(trim($input['email']));
    $phone         = trim($input['phone'] ?? '');
    $nic           = trim($input['nic'] ?? '');
    $userType      = 'Hospital Staff';
    $department    = trim($input['department'] ?? '');
    $organization  = trim($input['organization'] ?? '') ?: 'Badulla Hospital';
    $password      = (string) $input['password'];
    $requestedRole = strtolower(trim($input['requested_role'] ?? ''));

    // Never trust frontend role/status values. The backend alone decides the real account role.
    $forbiddenClientRoleKeys = ['role', 'status', 'role_locked', 'account_locked', 'is_admin'];
    foreach ($forbiddenClientRoleKeys as $key) {
        if (array_key_exists($key, $input)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Registration cannot include system role or account status values. The backend decides account activation.'
            ]);
            exit;
        }
    }

    if (in_array($requestedRole, ['admin', 'administrator'], true)) {
        $requestedRole = 'it_admin';
    }

    if (!in_array($requestedRole, pmtsAllowedRegistrationRoles(), true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Please select a valid requested role.'
        ]);
        exit;
    }

    $nameError = pmtsValidateFullName($fullName);
    if ($nameError !== null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $nameError
        ]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email address format.'
        ]);
        exit;
    }

    $passwordError = pmtsValidateStrongPassword($password);
    if ($passwordError !== null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $passwordError
        ]);
        exit;
    }

    if (pmtsEmailExists($pdo, $email)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'An account with this email already exists.'
        ]);
        exit;
    }

    $isItAdminRequest = $requestedRole === 'it_admin';
    $autoApproveItAdmin = false;

    if ($isItAdminRequest) {
        $autoApproveItAdmin = pmtsCanAutoApproveItAdmin($pdo, $email);
        if (!$autoApproveItAdmin) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'An IT Admin account already exists. Only one IT Admin can register. Please select another requested role or contact the current IT Admin.'
            ]);
            exit;
        }
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Normal staff accounts wait for approval. The first IT Admin activates immediately; later IT Admin requests are blocked.
    $role = $autoApproveItAdmin ? 'it_admin' : 'pending';
    $status = $autoApproveItAdmin ? 'active' : 'pending';
    $roleLocked = $autoApproveItAdmin ? 1 : 0;

    $newUserId = pmtsInsertRegisteredUser($pdo, [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'nic' => $nic,
        'user_type' => $userType,
        'department' => $department,
        'organization' => $organization,
        'password' => $hashedPassword,
        'role' => $role,
        'requested_role' => $requestedRole,
        'status' => $status,
        'role_locked' => $roleLocked,
    ]);

    $requestedRoleLabel = pmtsRoleLabel($requestedRole);

    if ($autoApproveItAdmin) {
        createAuditLog(
            $pdo,
            $newUserId,
            'REGISTER_AUTO_IT_ADMIN',
            'auth',
            "IT Admin self-registration auto-approved for $email"
        );

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'First IT Admin registration successful. Your account is active and you can login now.',
            'data' => [
                'id'             => $newUserId,
                'full_name'      => $fullName,
                'email'          => $email,
                'role'           => $role,
                'requested_role' => $requestedRole,
                'status'         => $status,
                'role_locked'    => $roleLocked,
                'user_type'      => $userType
            ]
        ]);
        exit;
    }

    pmtsNotifyRole(
        $pdo,
        'it_admin',
        'New User Registration',
        "A new hospital staff account is waiting for approval: $fullName ($email). Requested role: $requestedRoleLabel.",
        'system'
    );

    createAuditLog(
        $pdo,
        $newUserId,
        'REGISTER',
        'auth',
        "New user registered: $email (type: $userType, requested role: $requestedRole)"
    );

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful. Your account is pending approval by the IT Admin.',
        'data' => [
            'id'             => $newUserId,
            'full_name'      => $fullName,
            'email'          => $email,
            'role'           => $role,
            'requested_role' => $requestedRole,
            'status'         => $status,
            'role_locked'    => $roleLocked,
            'user_type'      => $userType
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error.',
        'error' => $e->getMessage()
    ]);

    exit;
} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Registration failed.',
        'error' => $e->getMessage()
    ]);

    exit;
}
