<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../schedule/ncb_tasks.php';

try {
    requireAuth();
    $pdo = getPDO();
    $tasks = pmtsGetTaskDelaySettings($pdo, getDefaultProcurementScheduleTasks());

    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
    ]);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode([
        'success' => false,
        'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Failed to load schedule task delay settings.',
    ]);
}
