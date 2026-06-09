<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // --- Required field validation ---
    $required = ['full_name', 'email', 'password', 'user_type'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '$field' is required."]);
            exit;
        }
    }

    $fullName     = trim($input['full_name']);
    $email        = strtolower(trim($input['email']));
    $phone        = trim($input['phone']        ?? '');
    $nic          = trim($input['nic']           ?? '');
    $userType     = trim($input['user_type']);
    $department   = trim($input['department']   ?? '');
    $organization = trim($input['organization'] ?? '');
    $password     = $input['password'];

    // Notify all active IT Admins about new registration
$adminStmt = $pdo->prepare("
    SELECT id 
    FROM users 
    WHERE role = 'it_admin' 
    AND status = 'active'
");
$adminStmt->execute();
$admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($admins as $admin) {
    $notifyStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, message, is_read, created_at)
        VALUES (?, ?, 0, NOW())
    ");

    $notifyStmt->execute([
        $admin['id'],
        'New user registration pending approval.'
    ]);
}
    // --- Email format check ---
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
        exit;
    }

    // --- Password length check ---
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    // --- user_type ENUM check ---
    $validUserTypes = ['Hospital Staff', 'Outside Person'];
    if (!in_array($userType, $validUserTypes, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'user_type must be "Hospital Staff" or "Outside Person".']);
        exit;
    }

    $pdo = getPDO();

    // --- Check duplicate email ---
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
        exit;
    }

    // --- Hash password (bcrypt, cost 12) ---
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // --- Determine role based on user_type ---
    // Outside persons automatically get 'outside_person' role
    $role = ($userType === 'Outside Person') ? 'outside_person' : 'pending';

    // --- Insert user ---
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, email, phone, nic, user_type, department, organization, password, role, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([
        $fullName,
        $email,
        $phone,
        $nic,
        $userType,
        $department,
        $organization,
        $hashedPassword,
        $role,
    ]);
    $newUserId = (int) $pdo->lastInsertId();

    // --- Audit log ---
    createAuditLog($pdo, $newUserId, 'REGISTER', 'auth', "New user registered: $email (type: $userType)");

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful. Your account is pending approval by the IT Admin.',
        'data'    => [
            'id'        => $newUserId,
            'full_name' => $fullName,
            'email'     => $email,
            'role'      => $role,
            'status'    => 'pending',
            'user_type' => $userType,
        ],
    ]);

}catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}
