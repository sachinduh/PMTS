<?php
// ============================================================
// PMTS – GET /schedule/get_ncb_categories.php
// Returns NCB categories for create procurement / NCB schedule.
// Roles: any authenticated user
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/category_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    requireAuth();
    $pdo = getPDO();
    $categories = getNcbCategories($pdo);

    echo json_encode([
        'success' => true,
        'data' => $categories,
        'categories' => array_map(fn($row) => $row['category_name'], $categories),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load NCB categories.']);
    error_log('PMTS GetNcbCategories Error: ' . $e->getMessage());
}
