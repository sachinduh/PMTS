<?php
// ============================================================
// PMTS – NCB Category Helper
// Stores custom NCB schedule/procurement categories managed by IT Admin.
// ============================================================

function ensureNcbCategoryTable(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ncb_categories (
            id INT(11) NOT NULL AUTO_INCREMENT,
            category_name VARCHAR(150) NOT NULL,
            created_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ncb_category_name (category_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $defaults = [
        'Medical Equipment',
        'Pharmaceuticals',
        'Laboratory Supplies',
        'IT Equipment',
        'Furniture',
        'Construction',
        'Services',
        'Other',
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO ncb_categories (category_name) VALUES (?)");
    foreach ($defaults as $category) {
        $stmt->execute([$category]);
    }
}

function getNcbCategories(PDO $pdo): array {
    ensureNcbCategoryTable($pdo);
    $stmt = $pdo->query("SELECT id, category_name, created_at FROM ncb_categories ORDER BY category_name ASC");
    return $stmt->fetchAll();
}

function ncbCategoryExists(PDO $pdo, string $categoryName): bool {
    ensureNcbCategoryTable($pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ncb_categories WHERE category_name = ?");
    $stmt->execute([$categoryName]);
    return (int) $stmt->fetchColumn() > 0;
}
