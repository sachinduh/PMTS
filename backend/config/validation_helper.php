<?php
// ============================================================
// PMTS common input validation helpers
// ============================================================

function pmtsValidateFullName(string $fullName): ?string
{
    $name = trim($fullName);

    if ($name === '') {
        return 'Full name is required.';
    }

    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        return 'Full name must be between 2 and 150 letters.';
    }

    // Names may contain letters and spaces only. Numbers and symbols are blocked.
    if (!preg_match('/^[\p{L}]+(?:\s+[\p{L}]+)*$/u', $name)) {
        return 'Full name must contain letters and spaces only. Numbers and special characters are not allowed.';
    }

    return null;
}

function pmtsValidateStrongPassword(string $password): ?string
{
    $message = 'Password must contain letters, numbers, and at least one special character. Minimum length is 8 characters. Example: Admin@123';

    if (strlen($password) < 8) {
        return $message;
    }

    $hasLetter = preg_match('/[A-Za-z]/', $password) === 1;
    $hasNumber = preg_match('/\d/', $password) === 1;
    $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password) === 1;

    if (!$hasLetter && $hasNumber && !$hasSpecial) {
        return 'Password cannot contain numbers only. Use letters, numbers, and at least one special character. Example: Admin@123';
    }

    if (!$hasLetter || !$hasNumber || !$hasSpecial) {
        return $message;
    }

    return null;
}
