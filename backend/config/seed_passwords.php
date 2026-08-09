<?php
/**
 * PMTS – Password Seed Utility
 * Run this script only on a local development database after importing SQL.
 *
 * It updates non-admin demo users to the strong sample password: Admin@123
 */

require_once __DIR__ . '/db.php';

$plainPassword = 'Admin@123';
$hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$testEmails = [
    'director@badullahospital.lk',
    'procurement@badullahospital.lk',
    'accountant@badullahospital.lk',
    'bec@badullahospital.lk',
    'specification@badullahospital.lk',
    'bec.member2@badullahospital.lk',
    'sachinduhimsara06@gmail.com',
    'jananee@gmail.com',
    'udari@gmail.com',
    'deslivapasindu47@gmail.com',
];

try {
    $pdo = getPDO();
    $placeholders = implode(',', array_fill(0, count($testEmails), '?'));
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email IN ($placeholders)");
    $stmt->execute(array_merge([$hash], $testEmails));

    echo "<h2 style='color:green;font-family:monospace;'>Passwords updated successfully.</h2>";
    echo "<p><b>Plain demo password:</b> <code>" . htmlspecialchars($plainPassword) . "</code></p>";
    echo "<p><b>Rows updated:</b> " . (int) $stmt->rowCount() . "</p>";
    echo "<p style='color:red;'><b>Delete this file after use for security.</b><br><code>" . htmlspecialchars(__FILE__) . "</code></p>";
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h2 style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
