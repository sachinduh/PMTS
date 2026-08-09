<?php
// ============================================================
// PMTS – Schedule Delay Alert Helper
// Rules requested:
//  1) If a planned date is missed, notify Director.
//  2) Two-week delay period is tracked.
//  3) After two weeks, next one week stays YELLOW.
//  4) After that week, alert becomes RED.
//  5) Every delayed day creates one Director notification/email.
// ============================================================

require_once __DIR__ . '/../config/email_helper.php';
require_once __DIR__ . '/../schedule/schedule_schema_helper.php';

if (!function_exists('pmtsEnsureDelayAlertColumns')) {
    function pmtsColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    function pmtsIndexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
        );
        $stmt->execute([$table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    }

    function pmtsEnsureDelayAlertColumns(PDO $pdo): void
    {
        pmtsEnsureAllowedDelayDaysColumn($pdo);

        if (!pmtsColumnExists($pdo, 'delay_alerts', 'schedule_task_id')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD COLUMN schedule_task_id INT(11) DEFAULT NULL AFTER procurement_id");
        }
        if (!pmtsColumnExists($pdo, 'delay_alerts', 'alert_color')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD COLUMN alert_color ENUM('yellow','red') NOT NULL DEFAULT 'yellow' AFTER risk_level");
        }
        if (!pmtsColumnExists($pdo, 'delay_alerts', 'delayed_days')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD COLUMN delayed_days INT(11) NOT NULL DEFAULT 0 AFTER actual_date");
        }
        if (!pmtsColumnExists($pdo, 'delay_alerts', 'alert_date')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD COLUMN alert_date DATE DEFAULT NULL AFTER delayed_days");
        }
        if (!pmtsColumnExists($pdo, 'delay_alerts', 'email_status')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD COLUMN email_status ENUM('not_sent','sent','failed') NOT NULL DEFAULT 'not_sent' AFTER alert_date");
        }
        if (!pmtsColumnExists($pdo, 'delay_alerts', 'email_error')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD COLUMN email_error TEXT DEFAULT NULL AFTER email_status");
        }
        if (!pmtsColumnExists($pdo, 'delay_alerts', 'notified_at')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD COLUMN notified_at TIMESTAMP NULL DEFAULT NULL AFTER email_error");
        }
        if (!pmtsColumnExists($pdo, 'procurement_time_schedule', 'last_delay_alert_date')) {
            $pdo->exec("ALTER TABLE procurement_time_schedule ADD COLUMN last_delay_alert_date DATE DEFAULT NULL AFTER remarks");
        }
        if (!pmtsIndexExists($pdo, 'delay_alerts', 'idx_da_schedule_task')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD KEY idx_da_schedule_task (schedule_task_id)");
        }
        if (!pmtsIndexExists($pdo, 'delay_alerts', 'idx_da_alert_date')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD KEY idx_da_alert_date (alert_date)");
        }
        if (!pmtsIndexExists($pdo, 'delay_alerts', 'uq_da_task_alert_date')) {
            $pdo->exec("ALTER TABLE delay_alerts ADD UNIQUE KEY uq_da_task_alert_date (schedule_task_id, alert_date)");
        }
        if (!pmtsIndexExists($pdo, 'procurement_time_schedule', 'idx_schedule_last_delay_alert_date')) {
            $pdo->exec("ALTER TABLE procurement_time_schedule ADD KEY idx_schedule_last_delay_alert_date (last_delay_alert_date)");
        }
    }

    function pmtsScheduleDelayInfo(?string $plannedDate, ?string $actualDate, string $status, ?string $today = null, int $allowedDelayDays = 0): ?array
    {
        if (!$plannedDate || $status === 'skipped') {
            return null;
        }

        $today = $today ?: date('Y-m-d');
        $allowedDelayDays = pmtsSanitizeAllowedDelayDays($allowedDelayDays);
        $deadlineDate = pmtsScheduleDeadlineDate($plannedDate, $allowedDelayDays);
        $comparisonDate = $actualDate ?: $today;
        $daysLate = pmtsScheduleDaysLateBeyondAllowed($plannedDate, $comparisonDate, $allowedDelayDays);

        if ($daysLate < 1) {
            return null;
        }

        if (!$actualDate && $deadlineDate >= $today) {
            return null;
        }

        $allowedText = $allowedDelayDays > 0
            ? " after the {$allowedDelayDays} allowed delay day(s)"
            : '';

        if ($daysLate >= 22) {
            $color = 'red';
            $risk = 'critical';
            $label = $actualDate ? 'Completed Late - Red Delay' : 'Red Delay';
            $description = $actualDate
                ? "Actual date is more than 21 day(s) after the allowed deadline{$allowedText}."
                : "Delayed more than one week after the two-week delay alert period{$allowedText}.";
        } elseif ($daysLate >= 15) {
            $color = 'yellow';
            $risk = 'high';
            $label = $actualDate ? 'Completed Late - Yellow Delay' : 'Yellow Delay';
            $description = $actualDate
                ? "Actual date is after the two-week delay alert period{$allowedText}."
                : "Delayed after the two-week delay alert period{$allowedText}.";
        } else {
            $color = 'yellow';
            $risk = 'medium';
            $label = $actualDate ? 'Completed Late' : 'Missed Allowed Deadline';
            $description = $actualDate
                ? "Actual date is {$daysLate} day(s) after the allowed deadline."
                : "Allowed deadline missed; still within the two-week delay alert period.";
        }

        return [
            'is_delayed' => true,
            'days_late' => $daysLate,
            'allowed_delay_days' => $allowedDelayDays,
            'deadline_date' => $deadlineDate,
            'alert_color' => $color,
            'risk_level' => $risk,
            'label' => $label,
            'description' => $description,
            'planned_date' => $plannedDate,
            'actual_date' => $actualDate,
            'today' => $today,
        ];
    }

    function pmtsGetActiveDirectors(PDO $pdo): array
    {
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE role = 'director' AND status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function pmtsBuildDelayMessage(array $task, array $delay): string
    {
        return "{$delay['label']}: Task '{$task['task_name']}' for {$task['procurement_title']} ({$task['public_procurement_id']}) "
             . "is {$delay['days_late']} day(s) late beyond the allowed delay. Planned date: {$task['planned_date']}. "
             . "Allowed delay: " . (int) ($delay['allowed_delay_days'] ?? $task['allowed_delay_days'] ?? 0) . " day(s). "
             . "Allowed deadline: " . ($delay['deadline_date'] ?? $task['planned_date']) . ". {$delay['description']}";
    }

    function pmtsRunScheduleDelayCheck(PDO $pdo, ?int $actorUserId = null, bool $sendEmail = true): array
    {
        pmtsEnsureDelayAlertColumns($pdo);

        $today = date('Y-m-d');
        $directors = pmtsGetActiveDirectors($pdo);

        $stmt = $pdo->prepare(
            "SELECT s.id AS schedule_task_id,
                    s.procurement_id,
                    s.task_name,
                    s.planned_date,
                    s.allowed_delay_days,
                    DATE_ADD(s.planned_date, INTERVAL COALESCE(s.allowed_delay_days, 0) DAY) AS allowed_deadline_date,
                    s.actual_date,
                    s.status AS task_status,
                    s.last_delay_alert_date,
                    p.procurement_id AS public_procurement_id,
                    p.title AS procurement_title,
                    p.tender_number,
                    p.current_status,
                    p.procurement_type
             FROM procurement_time_schedule s
             INNER JOIN procurements p ON p.id = s.procurement_id
             WHERE s.planned_date IS NOT NULL
               AND DATE_ADD(s.planned_date, INTERVAL COALESCE(s.allowed_delay_days, 0) DAY) < ?
               AND s.actual_date IS NULL
               AND s.status NOT IN ('completed', 'skipped')
               AND p.current_status NOT IN ('completed', 'cancelled')
             ORDER BY s.planned_date ASC, s.id ASC"
        );
        $stmt->execute([$today]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $insertAlert = $pdo->prepare(
            "INSERT INTO delay_alerts
                (procurement_id, schedule_task_id, alert_type, message, risk_level, alert_color, expected_date, actual_date, delayed_days, alert_date, email_status, notified_at, status)
             VALUES
                (?, ?, 'schedule_delay', ?, ?, ?, ?, NULL, ?, ?, 'not_sent', NOW(), 'active')"
        );

        $markExisting = $pdo->prepare(
            "UPDATE delay_alerts
             SET risk_level = ?, alert_color = ?, delayed_days = ?, message = ?, notified_at = COALESCE(notified_at, NOW())
             WHERE schedule_task_id = ? AND alert_date = ?"
        );

        $dupAlert = $pdo->prepare("SELECT id FROM delay_alerts WHERE schedule_task_id = ? AND alert_date = ? LIMIT 1");
        $notifyStmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, type, is_read)
             VALUES (?, ?, ?, ?, 0)"
        );
        $dupNotif = $pdo->prepare(
            "SELECT id FROM notifications
             WHERE user_id = ? AND title = ? AND message = ? AND DATE(created_at) = ?
             LIMIT 1"
        );
        $updateTask = $pdo->prepare(
            "UPDATE procurement_time_schedule
             SET status = CASE WHEN status IN ('pending','in_progress') THEN 'delayed' ELSE status END,
                 last_delay_alert_date = ?
             WHERE id = ?"
        );
        $updateEmail = $pdo->prepare(
            "UPDATE delay_alerts SET email_status = ?, email_error = ? WHERE id = ?"
        );

        $createdAlerts = 0;
        $existingToday = 0;
        $notificationsCreated = 0;
        $emailsSent = 0;
        $emailFailures = 0;
        $yellowCount = 0;
        $redCount = 0;

        foreach ($tasks as $task) {
            $delay = pmtsScheduleDelayInfo($task['planned_date'], $task['actual_date'], $task['task_status'], $today, (int) ($task['allowed_delay_days'] ?? 0));
            if (!$delay) {
                continue;
            }

            if ($delay['alert_color'] === 'red') $redCount++; else $yellowCount++;

            $message = pmtsBuildDelayMessage($task, $delay);
            $title = ($delay['alert_color'] === 'red' ? ' Red Delay Alert' : ' Yellow Delay Alert')
                   . " - {$task['public_procurement_id']}";
            $notificationType = $delay['alert_color'] === 'red' ? 'error' : 'warning';

            $dupAlert->execute([$task['schedule_task_id'], $today]);
            $existingAlertId = $dupAlert->fetchColumn();
            $alertId = null;

            if ($existingAlertId) {
                $existingToday++;
                $alertId = (int) $existingAlertId;
                $markExisting->execute([
                    $delay['risk_level'],
                    $delay['alert_color'],
                    $delay['days_late'],
                    $message,
                    $task['schedule_task_id'],
                    $today,
                ]);
            } else {
                $insertAlert->execute([
                    $task['procurement_id'],
                    $task['schedule_task_id'],
                    $message,
                    $delay['risk_level'],
                    $delay['alert_color'],
                    $delay['deadline_date'] ?? $task['planned_date'],
                    $delay['days_late'],
                    $today,
                ]);
                $alertId = (int) $pdo->lastInsertId();
                $createdAlerts++;
            }

            $updateTask->execute([$today, $task['schedule_task_id']]);

            // Daily notification/email to Director. If alert already exists today, avoid duplicates.
            if (!$existingAlertId) {
                $emailErrorMessages = [];
                $emailSentForThisAlert = false;

                foreach ($directors as $director) {
                    $dupNotif->execute([$director['id'], $title, $message, $today]);
                    if (!$dupNotif->fetch()) {
                        $notifyStmt->execute([$director['id'], $title, $message, $notificationType]);
                        $notificationsCreated++;
                    }

                    if ($sendEmail && !empty($director['email'])) {
                        $emailBody = "Dear {$director['full_name']},\n\n"
                            . "A procurement schedule planned date has been missed.\n\n"
                            . "Procurement ID: {$task['public_procurement_id']}\n"
                            . "Title: {$task['procurement_title']}\n"
                            . "Type: {$task['procurement_type']}\n"
                            . "Task: {$task['task_name']}\n"
                            . "Planned Date: {$task['planned_date']}\n"
                            . "Allowed Delay: " . (int) ($delay['allowed_delay_days'] ?? 0) . " day(s)\n"
                            . "Allowed Deadline: " . ($delay['deadline_date'] ?? $task['planned_date']) . "\n"
                            . "Delayed Days Beyond Allowed Deadline: {$delay['days_late']}\n"
                            . "Alert Level: {$delay['label']}\n"
                            . "Description: {$delay['description']}\n\n"
                            . "Please login to PMTS and check Delay Alerts / procurement tracking.\n\n"
                            . "PMTS Automated Delay Alert";

                        $emailResult = pmtsSendEmail($director['email'], $title, $emailBody);
                        if ($emailResult['success']) {
                            $emailSentForThisAlert = true;
                            $emailsSent++;
                        } else {
                            $emailFailures++;
                            $emailErrorMessages[] = $director['email'] . ': ' . $emailResult['message'];
                        }
                    }
                }

                if ($alertId) {
                    if (!$sendEmail) {
                        $updateEmail->execute(['not_sent', 'Email sending disabled for this run.', $alertId]);
                    } elseif ($emailSentForThisAlert) {
                        $updateEmail->execute(['sent', $emailErrorMessages ? implode(' | ', $emailErrorMessages) : null, $alertId]);
                    } else {
                        $updateEmail->execute(['failed', $emailErrorMessages ? implode(' | ', $emailErrorMessages) : 'No email could be sent.', $alertId]);
                    }
                }
            }
        }

        return [
            'success' => true,
            'today' => $today,
            'overdue_tasks_found' => count($tasks),
            'alerts_created' => $createdAlerts,
            'alerts_already_existing_today' => $existingToday,
            'notifications_created' => $notificationsCreated,
            'emails_sent' => $emailsSent,
            'email_failures' => $emailFailures,
            'directors_found' => count($directors),
            'yellow_alerts' => $yellowCount,
            'red_alerts' => $redCount,
            'message' => 'Schedule delay check completed.',
        ];
    }
}
