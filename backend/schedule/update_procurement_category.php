<?php
// ============================================================
// PMTS – POST /schedule/update_procurement_category.php
// IT Admin updates the category shown in the procurement time schedule.
// Body: { "procurement_id": 5, "category": "Medical Equipment" }
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
    $procId = (int) ($input['procurement_id'] ?? 0);
    $category = trim($input['category'] ?? '');

    if (!$procId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'procurement_id is required.']);
        exit;
    }

    if ($category === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Category is required.']);
        exit;
    }

    $pdo = getPDO();
    ensureNcbCategoryTable($pdo);

    $procStmt = $pdo->prepare("SELECT id, procurement_id, procurement_type, category FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procId]);
    $proc = $procStmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    if (!ncbCategoryExists($pdo, $category)) {
        $insert = $pdo->prepare("INSERT INTO ncb_categories (category_name, created_by) VALUES (?, ?)");
        $insert->execute([$category, $authUser['sub']]);
    }

    $update = $pdo->prepare("UPDATE procurements SET category = ? WHERE id = ?");
    $update->execute([$category, $procId]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'UPDATE_PROCUREMENT_CATEGORY',
        'procurements',
        "Updated category for {$proc['procurement_id']} from '{$proc['category']}' to '{$category}'"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Schedule category updated successfully.',
        'category' => $category,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update schedule category.']);
    error_log('PMTS UpdateProcurementCategory Error: ' . $e->getMessage());
}
