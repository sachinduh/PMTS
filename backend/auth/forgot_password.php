<?php
// ============================================================
//  PMTS – POST /auth/forgot_password.php
//  Generate a secure password reset token and try to email it.
//  If XAMPP/PHP mail is not configured, the API still returns a
//  local development reset link so testing can continue.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../config/email_helper.php';
require_once __DIR__ . '/password_reset_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

function pmtsFrontendBaseUrl(): string
{
    $configuredUrl = trim((string) (getenv('PMTS_FRONTEND_URL') ?: ''));
    if ($configuredUrl !== '') {
        return rtrim($configuredUrl, '/');
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    // During VS Code local development the PHP API runs on port 8000,
    // while the React reset-password page runs on Vite port 5173.
    if (preg_match('/^(localhost|127\.0\.0\.1)(:8000)?$/i', $host)) {
        return 'http://localhost:5173';
    }

    if ($host !== '') {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($isHttps ? 'https' : 'http') . '://' . $host;
    }

    return 'http://localhost:5173';
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = strtolower(trim($input['email'] ?? ''));

    if ($email === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
        exit;
    }

    $pdo = getPDO();

    pmtsEnsurePasswordResetTable($pdo);

    // Delete old used/expired reset records to keep the table small.
    $pdo->exec("DELETE FROM password_resets WHERE used = 1 OR expires_at <= NOW()");

    // Do not reveal whether email exists.
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'success' => true,
            'message' => 'If this email is registered and active, a password reset link will be sent.',
        ]);
        exit;
    }

    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")
        ->execute([$email]);

    $token = bin2hex(random_bytes(32));

    // Use the MySQL server clock for both creation and later validation.
    // This prevents a token from appearing immediately expired when PHP and
    // MySQL use different time zones on a local XAMPP installation.
    $insertStmt = $pdo->prepare(
        "INSERT INTO password_resets (email, token, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
    );
    $insertStmt->execute([$email, $token]);

    $expiresStmt = $pdo->prepare(
        "SELECT expires_at FROM password_resets WHERE id = ? LIMIT 1"
    );
    $expiresStmt->execute([(int) $pdo->lastInsertId()]);
    $expiresAt = (string) ($expiresStmt->fetchColumn() ?: '');

    $resetLink = pmtsFrontendBaseUrl() . '/reset-password?token=' . urlencode($token);

    $subject = 'PMTS Password Reset Link';
    $body = "Hello " . ($user['full_name'] ?? 'PMTS User') . ",\n\n"
        . "A password reset request was made for your PMTS account.\n\n"
        . "Open this link to reset your password:\n"
        . $resetLink . "\n\n"
        . "This link will expire in 1 hour. If you did not request this, please ignore this email.\n\n"
        . "PMTS - Procurement Management Tracking System";

    $emailResult = pmtsSendEmail($email, $subject, $body);

    createAuditLog($pdo, (int)$user['id'], 'FORGOT_PASSWORD', 'auth', "Password reset requested for: $email");

    $response = [
        'success' => true,
        'message' => $emailResult['success']
            ? 'Password reset link has been sent to your email.'
            : 'Reset request processed.',
        'email_sent' => $emailResult['success'],
        'expires_at' => $expiresAt,
    ];

    // Keep this only for local development/testing. Remove in production.
    if (!$emailResult['success']) {
        $response['dev_reset_link'] = $resetLink;
        $response['dev_token'] = $token;
    }

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    error_log("PMTS ForgotPassword Error: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    error_log("PMTS ForgotPassword Exception: " . $e->getMessage());
}
