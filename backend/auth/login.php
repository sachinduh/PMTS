<?php

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
    $input = json_decode(file_get_contents('php://input'), true);

    $email    = strtolower(trim($input['email']    ?? ''));
    $password = trim($input['password'] ?? '');

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }

    $pdo = getPDO();

    // --- Fetch user by email ---
    $stmt = $pdo->prepare(
        "SELECT id, full_name, email, phone, nic, user_type, department, organization,
                password, role, status
         FROM users
         WHERE email = ?
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // --- Check account status BEFORE password verify (fail fast, reveal nothing extra) ---
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
            'message' => 'Your account has been rejected. Please contact the IT Admin.',
        ]);
        exit;
    }

    // --- Verify password ---
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        createAuditLog($pdo, $user['id'], 'LOGIN_FAILED', 'auth', "Failed login attempt for: {$user['email']}");
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // --- Only active users reach here ---
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is not active. Contact IT Admin.']);
        exit;
    }

    // --- Generate JWT ---
    $token = generateJWT($user['id'], $user['role'], $user['email']);

    // --- Audit log ---
    createAuditLog($pdo, $user['id'], 'LOGIN', 'auth', "User logged in: {$user['email']} (role: {$user['role']})");

    http_response_code(200);
    echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'token'   => $token,
    'user'    => [
        'id'           => (int) $user['id'],
        'full_name'    => $user['full_name'],
        'email'        => $user['email'],
        'phone'        => $user['phone'],
        'nic'          => $user['nic'],
        'user_type'    => $user['user_type'],
        'department'   => $user['department'],
        'organization' => $user['organization'],
        'role'         => $user['role'],
        'status'       => $user['status'],
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
