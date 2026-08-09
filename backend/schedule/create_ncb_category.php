<?php
// ============================================================
// PMTS – POST /schedule/create_ncb_category.php
// IT Admin adds a custom NCB category.
// Body: { "category_name": "Medical Gas" }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/category_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['it_admin']);
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $categoryName = trim($input['category_name'] ?? $input['category'] ?? '');

    if ($categoryName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Category name is required.']);
        exit;
    }

    if (strlen($categoryName) > 150) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Category name must be 150 characters or less.']);
        exit;
    }

    $pdo = getPDO();
    ensureNcbCategoryTable($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO ncb_categories (category_name, created_by)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE category_name = VALUES(category_name)"
    );
    $stmt->execute([$categoryName, $authUser['sub']]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'CREATE_NCB_CATEGORY',
        'ncb_categories',
        "Added/confirmed NCB category: {$categoryName}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'NCB category saved successfully.',
        'category_name' => $categoryName,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save NCB category.']);
    error_log('PMTS CreateNcbCategory Error: ' . $e->getMessage());
}
