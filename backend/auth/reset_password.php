<?php
// ============================================================
//  PMTS – POST /auth/reset_password.php
//  Verify reset token and set a new password
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/../config/validation_helper.php';
require_once __DIR__ . '/password_reset_helper.php';

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

    $passwordError = pmtsValidateStrongPassword((string) $newPassword);
    if ($passwordError !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $passwordError]);
        exit;
    }

    $pdo = getPDO();
    pmtsEnsurePasswordResetTable($pdo);

    // Read the token first so the user receives an accurate message.
    // Expiry is compared with the same MySQL clock used to create the token.
    $stmt = $pdo->prepare(
        "SELECT id, email, used, expires_at,
                CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END AS is_not_expired
         FROM password_resets
         WHERE token = ?
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $resetRow = $stmt->fetch();

    if (!$resetRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This reset link is invalid. Please request a new password reset link.']);
        exit;
    }

    if ((int) $resetRow['used'] === 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This reset link has already been used or was replaced by a newer link. Please request a new one.']);
        exit;
    }

    if ((int) $resetRow['is_not_expired'] !== 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This reset link has expired. Please request a new one.']);
        exit;
    }

    $email          = $resetRow['email'];
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->beginTransaction();

    // Change the password and clear any failed-login lock at the same time.
    $updateStmt = $pdo->prepare(
        "UPDATE users
         SET password = ?,
             failed_login_attempts = 0,
             last_failed_login_at = NULL,
             account_locked = 0,
             locked_at = NULL,
             locked_reason = NULL,
             unlocked_by = NULL,
             unlocked_at = NOW()
         WHERE email = ? AND status = 'active'"
    );
    $updateStmt->execute([$hashedPassword, $email]);

    if ($updateStmt->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The account is no longer active. Please contact the IT Admin.']);
        exit;
    }

    // Invalidate every outstanding token for this email so none can be reused.
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")
        ->execute([$email]);

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

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully. You can now login with your new password.',
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    error_log("PMTS ResetPassword Error: " . $e->getMessage());
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    error_log("PMTS ResetPassword Exception: " . $e->getMessage());
}
