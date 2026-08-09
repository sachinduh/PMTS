<?php
// ============================================================
//  PMTS – Audit Log Helper
//  Include this in any PHP file that needs to write audit logs
// ============================================================

function createAuditLog(
    PDO    $pdo,
    ?int   $userId,
    string $action,
    string $module,
    string $description
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Support IPv6-mapped IPv4
        if ($ip === '::1') $ip = '127.0.0.1';

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, module, description, ip_address)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $action, $module, $description, $ip]);
    } catch (PDOException $e) {
        // Audit failure must never break the main flow
        error_log("PMTS AuditLog Error: " . $e->getMessage());
    }
}
