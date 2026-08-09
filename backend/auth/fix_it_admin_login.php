<?php
// ============================================================
// PMTS – Disabled legacy admin password reset helper
// This old helper could overwrite IT Admin credentials if opened
// in a browser, so it is intentionally blocked.
// ============================================================

require_once __DIR__ . '/../config/cors.php';

header('Content-Type: application/json; charset=UTF-8');
http_response_code(403);
echo json_encode([
    'success' => false,
    'message' => 'This legacy IT Admin reset helper is disabled for security. Use the registration page or create_first_admin.php only during initial setup when no IT Admin exists.',
]);
