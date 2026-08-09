<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/gallery_helper.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    requireAuth();
    $pdo = getPDO();
    ensureAboutGalleryTable($pdo);

    $stmt = $pdo->prepare(
        "SELECT g.id, g.title, g.description, g.image_data, g.created_by, g.sort_order, g.created_at,
                u.full_name AS uploaded_by_name, u.email AS uploaded_by_email
         FROM about_gallery_images g
         LEFT JOIN users u ON u.id = g.created_by
         WHERE g.is_active = 1
         ORDER BY g.sort_order ASC, g.id DESC"
    );
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($images as &$image) {
        $image['id'] = (int)($image['id'] ?? 0);
        $image['created_by'] = $image['created_by'] !== null ? (int)$image['created_by'] : null;
        $image['sort_order'] = (int)($image['sort_order'] ?? 0);
    }
    unset($image);

    echo json_encode([
        'success' => true,
        'images' => $images,
        'data' => $images,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load About page gallery.',
        'error' => $e->getMessage(),
    ]);
}
