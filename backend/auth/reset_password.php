<?php
// ============================================================
//  PMTS – POST /auth/reset_password.php
//  Verify reset token and set a new password
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $input       = json_decode(file_get_contents('php://input'), true);
    $token       = trim($input['token']        ?? '');
    $newPassword = trim($input['new_password'] ?? ($input['password'] ?? ''));

    if (empty($token) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token and new_password are required.']);
        exit;
    }

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
        exit;
    }

    $pdo = getPDO();

    // Find valid, unused, non-expired token
    $stmt = $pdo->prepare(
        "SELECT id, email FROM password_resets
         WHERE token = ?
           AND used = 0
           AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $resetRow = $stmt->fetch();

    if (!$resetRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token. Please request a new one.']);
        exit;
    }

    $email          = $resetRow['email'];
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    // Update password
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $updateStmt->execute([$hashedPassword, $email]);

    // Mark token as used
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")
        ->execute([$resetRow['id']]);

    // Get user id for audit
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $userStmt->execute([$email]);
    $userRow = $userStmt->fetch();

    createAuditLog(
        $pdo,
        $userRow['id'] ?? null,
        'RESET_PASSWORD',
        'auth',
        "Password successfully reset for: $email"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully. You can now login with your new password.',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    error_log("PMTS ResetPassword Error: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    error_log("PMTS ResetPassword Exception: " . $e->getMessage());
}
