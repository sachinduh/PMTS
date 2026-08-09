<?php
// ============================================================
// PMTS – Schedule schema helper
// Keeps schedule-table upgrade SQL in one place so endpoint files
// can call helper functions instead of repeating ALTER/column checks.
// ============================================================

if (!function_exists('pmtsScheduleColumnExists')) {
    function pmtsScheduleColumnExists(PDO $pdo, string $column): bool {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'procurement_time_schedule'
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    function pmtsEnsureAllowedDelayDaysColumn(PDO $pdo): void {
        if (!pmtsScheduleColumnExists($pdo, 'allowed_delay_days')) {
            $pdo->exec(
                "ALTER TABLE procurement_time_schedule
                 ADD COLUMN allowed_delay_days INT(11) NOT NULL DEFAULT 0
                 COMMENT 'IT Admin configured number of days this task may be delayed before delay alerts start'
                 AFTER planned_date"
            );
        }
    }

    function pmtsSanitizeAllowedDelayDays($value): int {
        if ($value === null || $value === '') {
            return 0;
        }

        $days = (int) $value;
        if ($days < 0) {
            return 0;
        }

        // Safety cap to avoid accidental extremely large values.
        return min($days, 3650);
    }

    function pmtsScheduleDeadlineDate(?string $plannedDate, int $allowedDelayDays = 0): ?string {
        if (!$plannedDate) {
            return null;
        }

        $allowedDelayDays = pmtsSanitizeAllowedDelayDays($allowedDelayDays);
        return date('Y-m-d', strtotime(substr((string) $plannedDate, 0, 10) . " +{$allowedDelayDays} days"));
    }

    function pmtsScheduleDaysLateBeyondAllowed(?string $plannedDate, ?string $comparisonDate, int $allowedDelayDays = 0): int {
        $deadlineDate = pmtsScheduleDeadlineDate($plannedDate, $allowedDelayDays);
        if (!$deadlineDate || !$comparisonDate) {
            return 0;
        }

        return (int) floor((strtotime(substr((string) $comparisonDate, 0, 10)) - strtotime($deadlineDate)) / 86400);
    }
}
