<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/email_helper.php';

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'smtp' => pmtsSmtpStatus(),
]);
