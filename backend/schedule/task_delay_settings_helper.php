<?php
// ============================================================
// PMTS – Global schedule task allowed-delay settings
// IT Admin configures these in System Settings. New procurement
// time schedules copy the configured delay days task-by-task.
// ============================================================

function pmtsEnsureTaskDelaySettingsTable(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schedule_task_delay_settings (
            id INT(11) NOT NULL AUTO_INCREMENT,
            task_name VARCHAR(255) NOT NULL,
            responsible_role VARCHAR(255) DEFAULT NULL,
            allowed_delay_days INT(11) NOT NULL DEFAULT 0,
            sort_order INT(11) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_schedule_task_delay_task_name (task_name),
            KEY idx_schedule_task_delay_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function pmtsSanitizeTaskDelayDays($value): int {
    if ($value === null || $value === '') {
        return 0;
    }
    $days = (int) $value;
    if ($days < 0) {
        return 0;
    }
    if ($days > 3650) {
        return 3650;
    }
    return $days;
}

function pmtsSeedTaskDelaySettings(PDO $pdo, array $defaultTasks): void {
    pmtsEnsureTaskDelaySettingsTable($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO schedule_task_delay_settings (task_name, responsible_role, allowed_delay_days, sort_order)
         VALUES (?, ?, 0, ?)
         ON DUPLICATE KEY UPDATE
            responsible_role = VALUES(responsible_role),
            sort_order = VALUES(sort_order)"
    );

    foreach ($defaultTasks as $index => $task) {
        $stmt->execute([
            trim((string) ($task['task_name'] ?? '')),
            trim((string) ($task['responsible_role'] ?? '')),
            $index + 1,
        ]);
    }
}

function pmtsGetTaskDelaySettings(PDO $pdo, array $defaultTasks): array {
    pmtsSeedTaskDelaySettings($pdo, $defaultTasks);
    $stmt = $pdo->query(
        "SELECT id, task_name, responsible_role, allowed_delay_days, sort_order
         FROM schedule_task_delay_settings
         ORDER BY sort_order ASC, id ASC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(function ($row) {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'task_name' => $row['task_name'] ?? '',
            'responsible_role' => $row['responsible_role'] ?? '',
            'allowed_delay_days' => pmtsSanitizeTaskDelayDays($row['allowed_delay_days'] ?? 0),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }, $rows);
}

function pmtsGetTaskDelaySettingsMap(PDO $pdo, array $defaultTasks): array {
    $settings = pmtsGetTaskDelaySettings($pdo, $defaultTasks);
    $map = [];
    foreach ($settings as $row) {
        $map[$row['task_name']] = pmtsSanitizeTaskDelayDays($row['allowed_delay_days'] ?? 0);
    }
    return $map;
}

function pmtsSaveTaskDelaySetting(PDO $pdo, array $defaultTasks, string $taskName, $allowedDelayDays): array {
    pmtsSeedTaskDelaySettings($pdo, $defaultTasks);

    $taskName = trim($taskName);
    $matchedTask = null;
    foreach ($defaultTasks as $index => $task) {
        if (($task['task_name'] ?? '') === $taskName) {
            $matchedTask = [
                'task_name' => $task['task_name'],
                'responsible_role' => $task['responsible_role'] ?? '',
                'sort_order' => $index + 1,
            ];
            break;
        }
    }

    if (!$matchedTask) {
        throw new InvalidArgumentException('Invalid schedule task selected.');
    }

    $days = pmtsSanitizeTaskDelayDays($allowedDelayDays);
    $stmt = $pdo->prepare(
        "INSERT INTO schedule_task_delay_settings (task_name, responsible_role, allowed_delay_days, sort_order)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            responsible_role = VALUES(responsible_role),
            allowed_delay_days = VALUES(allowed_delay_days),
            sort_order = VALUES(sort_order)"
    );
    $stmt->execute([
        $matchedTask['task_name'],
        $matchedTask['responsible_role'],
        $days,
        $matchedTask['sort_order'],
    ]);

    return [
        'task_name' => $matchedTask['task_name'],
        'responsible_role' => $matchedTask['responsible_role'],
        'allowed_delay_days' => $days,
        'sort_order' => $matchedTask['sort_order'],
    ];
}
