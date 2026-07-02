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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

function pmtsFrontendBaseUrl(): string
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        return rtrim($origin, '/');
    }

    // Local Vite default fallback
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

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `password_resets` (
            `id`         INT(11)      NOT NULL AUTO_INCREMENT,
            `email`      VARCHAR(150) NOT NULL,
            `token`      VARCHAR(255) NOT NULL,
            `expires_at` DATETIME     NOT NULL,
            `used`       TINYINT(1)   NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pr_email` (`email`),
            KEY `idx_pr_token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

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
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $insertStmt = $pdo->prepare(
        "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
    );
    $insertStmt->execute([$email, $token, $expiresAt]);

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
            : 'Reset link was generated, but the local email service is not configured.',
        'email_sent' => $emailResult['success'],
        'email_status' => $emailResult['success']
            ? 'Email sent successfully.'
            : 'Email was not sent because PHP mail/SMTP is not configured in XAMPP. Use the development reset link below for testing.',
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
