<?php
/**
 * PMTS – Password Seed Utility
 * Run this script ONCE after importing pmtss_db.sql into phpMyAdmin.
 *
 * URL: http://localhost/PMTS%20System/PMTS/backend/config/seed_passwords.php
 *
 * What it does:
 *  1. Generates a real bcrypt hash for "123456"
 *  2. Updates ALL sample test users with the correct hash
 *  3. Deletes itself for security (or prints a warning)
 */

require_once __DIR__ . '/db.php';

$plainPassword = '123456';
$hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$conn = getDBConnection();

$testEmails = [
    'itadmin@badullahospital.lk',
    'director@badullahospital.lk',
    'procurement@badullahospital.lk',
    'accountant@badullahospital.lk',
    'tec@badullahospital.lk',
];

$placeholders = implode(',', array_fill(0, count($testEmails), '?'));
$stmt = $conn->prepare(
    "UPDATE users SET password = ? WHERE email IN ($placeholders)"
);

$params = array_merge([$hash], $testEmails);
$types  = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo "<h2 style='color:green;font-family:monospace;'> Passwords updated successfully!</h2>";
    echo "<p><b>Hash used:</b> <code>" . htmlspecialchars($hash) . "</code></p>";
    echo "<p><b>Plain password:</b> <code>123456</code></p>";
    echo "<p><b>Users updated:</b></p><ul>";
    foreach ($testEmails as $email) {
        echo "<li>" . htmlspecialchars($email) . "</li>";
    }
    echo "</ul>";
    echo "<p style='color:red;'><b> Delete this file now for security!</b><br>
          Path: <code>" . __FILE__ . "</code></p>";
} else {
    echo "<h2 style='color:red;'> Error: " . htmlspecialchars($stmt->error) . "</h2>";
}

$stmt->close();
$conn->close();
?>
