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
    $title = trim((string)($data['title'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $imageData = trim((string)($data['image_data'] ?? $data['image'] ?? ''));
    $sortOrder = (int)($data['sort_order'] ?? 0);

    if (mb_strlen($title) > 150) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Gallery title must be 150 characters or less.']);
        exit;
    }

    if (mb_strlen($description) > 1000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Gallery description must be 1000 characters or less.']);
        exit;
    }

    $imageError = validateGalleryImageData($imageData);
    if ($imageError) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $imageError]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO about_gallery_images (title, description, image_data, created_by, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $title !== '' ? $title : null,
        $description !== '' ? $description : null,
        $imageData,
        (int)$authUser['sub'],
        $sortOrder,
    ]);

    $imageId = (int)$pdo->lastInsertId();

    createAuditLog(
        $pdo,
        (int)$authUser['sub'],
        'ADD_ABOUT_GALLERY_IMAGE',
        'about_gallery_images',
        "Added About page gallery image ID {$imageId}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Gallery image added successfully.',
        'image' => [
            'id' => $imageId,
            'title' => $title,
            'description' => $description,
            'image_data' => $imageData,
            'created_by' => (int)$authUser['sub'],
            'uploaded_by_name' => $authUser['full_name'] ?? '',
            'uploaded_by_email' => $authUser['email'] ?? '',
            'sort_order' => $sortOrder,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add gallery image.',
        'error' => $e->getMessage(),
    ]);
}
