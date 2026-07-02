<?php

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $pdo = getPDO();

    $status = $_GET['status'] ?? '';

    if (!empty($status)) {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                full_name,
                email,
                phone,
                nic,
                user_type,
                department,
                organization,
                role,
                role_locked,
                status,
                created_at
            FROM users
            WHERE status = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                full_name,
                email,
                phone,
                nic,
                user_type,
                department,
                organization,
                role,
                role_locked,
                status,
                created_at
            FROM users
            ORDER BY id DESC
        ");
        $stmt->execute();
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to load users.',
        'error' => $e->getMessage()
    ]);
}