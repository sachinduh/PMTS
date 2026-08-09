<?php

function pmtsEnsurePasswordResetTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(150) NOT NULL,
            `token` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_password_reset_token` (`token`),
            KEY `idx_pr_email` (`email`),
            KEY `idx_pr_expires` (`expires_at`),
            KEY `idx_pr_used` (`used`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
