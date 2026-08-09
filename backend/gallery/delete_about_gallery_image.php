<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';
require_once __DIR__ . '/gallery_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function input_json(): array {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
        exit;
    }

    $authUser = requireRole(['it_admin']);
    $pdo = getPDO();
    ensureAboutGalleryTable($pdo);

    $data = input_json();
    $imageId = (int)($data['image_id'] ?? $data['id'] ?? 0);

    if ($imageId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Gallery image ID is required.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, title FROM about_gallery_images WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Gallery image not found.']);
        exit;
    }

    $update = $pdo->prepare('UPDATE about_gallery_images SET is_active = 0 WHERE id = ?');
    $update->execute([$imageId]);

    createAuditLog(
        $pdo,
        (int)$authUser['sub'],
        'DELETE_ABOUT_GALLERY_IMAGE',
        'about_gallery_images',
        "Removed About page gallery image ID {$imageId}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Gallery image removed successfully.',
        'image_id' => $imageId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to remove gallery image.',
        'error' => $e->getMessage(),
    ]);
}
