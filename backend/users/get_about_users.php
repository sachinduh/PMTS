<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    requireAuth();

    $pdo = getPDO();
    ensureAccountSecurityColumns($pdo);

    $stmt = $pdo->prepare(
        "SELECT id, full_name, email, phone, department, organization, role, status, profile_picture, created_at
         FROM users
         WHERE status = 'active'
           AND LOWER(full_name) <> 'kavindu jayasinghe'
           AND LOWER(email) <> 'procurement2@badullahospital.lk'
         ORDER BY FIELD(role, 'it_admin', 'director', 'procurement_officer', 'accountant', 'bec_member', 'specification_committee'), full_name ASC, id ASC"
    );
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $user['id'] = (int)($user['id'] ?? 0);
        $user['phone'] = $user['phone'] ?? '';
        $user['department'] = $user['department'] ?? '';
        $user['organization'] = $user['organization'] ?? '';
        $user['profile_picture'] = $user['profile_picture'] ?? null;
    }
    unset($user);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'data' => $users,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load about page users.',
        'error' => $e->getMessage(),
    ]);
}
