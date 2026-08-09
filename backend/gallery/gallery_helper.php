<?php
// ============================================================
// PMTS About Page Image Gallery helper
// Images are stored as compressed data URLs so XAMPP upload
// setup does not require a separate writable uploads folder.
// ============================================================

function ensureAboutGalleryTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `about_gallery_images` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(150) DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `image_data` LONGTEXT NOT NULL COMMENT 'Compressed image data URL uploaded by IT Admin',
        `created_by` INT(11) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_about_gallery_active` (`is_active`, `sort_order`, `created_at`),
        KEY `idx_about_gallery_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function validateGalleryImageData(string $imageData): ?string {
    if ($imageData === '') {
        return 'Please select an image to upload.';
    }

    if (strlen($imageData) > 7000000) {
        return 'Gallery image is too large. Please choose a smaller image.';
    }

    if (!preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $imageData)) {
        return 'Invalid image format. Use JPG, PNG, GIF, or WEBP.';
    }

    return null;
}
