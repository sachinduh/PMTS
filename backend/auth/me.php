<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $authUser = requireAuth();

    echo json_encode([
        'success' => true,
        'message' => 'Session is valid.',
        'user' => [
            'id'                    => (int) $authUser['id'],
            'full_name'             => $authUser['full_name'],
            'email'                 => $authUser['email'],
            'phone'                 => $authUser['phone'],
            'nic'                   => $authUser['nic'],
            'user_type'             => $authUser['user_type'],
            'department'            => $authUser['department'],
            'organization'          => $authUser['organization'],
            'profile_picture'      => $authUser['profile_picture'] ?? null,
            'role'                  => $authUser['role'],
            'requested_role'        => $authUser['requested_role'] ?? null,
            'role_locked'           => (int) ($authUser['role_locked'] ?? 0),
            'status'                => $authUser['status'],
            'failed_login_attempts' => (int) ($authUser['failed_login_attempts'] ?? 0),
            'account_locked'        => (int) ($authUser['account_locked'] ?? 0),
            'locked_at'             => $authUser['locked_at'] ?? null,
            'locked_reason'         => $authUser['locked_reason'] ?? null,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Session validation failed.',
    ]);
    error_log('PMTS Auth Me Error: ' . $e->getMessage());
}
