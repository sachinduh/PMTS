<?php
// ============================================================
//  PMTS – NCB Task File Tracking Helper
//  Stores per-schedule-task file counts by selected file type.
// ============================================================

function pmtsTaskFileTypeOptions(): array {
    return [
        'Main Procurement File',
        'Specification File',
        'Bid / Quotation Document',
        'Committee Letter',
        'Bid Opening Document',
        'Evaluation Report',
        'Approval Document',
        'Supplier Document',
        'Purchase Order',
        'Invoice / Payment File',
        'Other',
    ];
}

function pmtsNormalizeTaskFileType(?string $fileType): string {
    $fileType = trim((string) $fileType);
    $allowed = pmtsTaskFileTypeOptions();
    return in_array($fileType, $allowed, true) ? $fileType : '';
}

function pmtsEnsureTaskFileTrackingTable(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ncb_task_file_tracking` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `procurement_id` INT(11) NOT NULL COMMENT 'References procurements.id',
          `schedule_task_id` INT(11) NOT NULL COMMENT 'References procurement_time_schedule.id',
          `file_type` VARCHAR(100) NOT NULL,
          `total_files` INT(11) NOT NULL DEFAULT 0,
          `completed_files` INT(11) NOT NULL DEFAULT 0,
          `remarks` TEXT DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_task_file_type` (`schedule_task_id`, `file_type`),
          KEY `idx_task_file_tracking_proc` (`procurement_id`),
          KEY `idx_task_file_tracking_task` (`schedule_task_id`),
          CONSTRAINT `fk_task_file_tracking_proc`
            FOREIGN KEY (`procurement_id`) REFERENCES `procurements`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `fk_task_file_tracking_schedule`
            FOREIGN KEY (`schedule_task_id`) REFERENCES `procurement_time_schedule`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Per NCB schedule task file count tracking by file type'"
    );
}

function pmtsEmptyTaskFileTrackingSummary(): array {
    return [
        'type_count' => 0,
        'total_files' => 0,
        'completed_files' => 0,
        'pending_files' => 0,
    ];
}

function pmtsGetTaskFileTrackingSummary(PDO $pdo, int $scheduleTaskId): array {
    pmtsEnsureTaskFileTrackingTable($pdo);
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS type_count,
            COALESCE(SUM(total_files), 0) AS total_files,
            COALESCE(SUM(completed_files), 0) AS completed_files,
            COALESCE(SUM(GREATEST(total_files - completed_files, 0)), 0) AS pending_files
         FROM ncb_task_file_tracking
         WHERE schedule_task_id = ?"
    );
    $stmt->execute([$scheduleTaskId]);
    $summary = $stmt->fetch() ?: pmtsEmptyTaskFileTrackingSummary();
    return [
        'type_count' => (int) ($summary['type_count'] ?? 0),
        'total_files' => (int) ($summary['total_files'] ?? 0),
        'completed_files' => (int) ($summary['completed_files'] ?? 0),
        'pending_files' => (int) ($summary['pending_files'] ?? 0),
    ];
}

function pmtsGetTaskFileTrackingSummaries(PDO $pdo, int $procurementId): array {
    pmtsEnsureTaskFileTrackingTable($pdo);
    $stmt = $pdo->prepare(
        "SELECT
            schedule_task_id,
            COUNT(*) AS type_count,
            COALESCE(SUM(total_files), 0) AS total_files,
            COALESCE(SUM(completed_files), 0) AS completed_files,
            COALESCE(SUM(GREATEST(total_files - completed_files, 0)), 0) AS pending_files
         FROM ncb_task_file_tracking
         WHERE procurement_id = ?
         GROUP BY schedule_task_id"
    );
    $stmt->execute([$procurementId]);

    $summaries = [];
    foreach ($stmt->fetchAll() as $row) {
        $summaries[(int) $row['schedule_task_id']] = [
            'type_count' => (int) ($row['type_count'] ?? 0),
            'total_files' => (int) ($row['total_files'] ?? 0),
            'completed_files' => (int) ($row['completed_files'] ?? 0),
            'pending_files' => (int) ($row['pending_files'] ?? 0),
        ];
    }
    return $summaries;
}

function pmtsGetTaskFileTrackingRows(PDO $pdo, int $procurementId, int $scheduleTaskId): array {
    pmtsEnsureTaskFileTrackingTable($pdo);
    $stmt = $pdo->prepare(
        "SELECT id, procurement_id, schedule_task_id, file_type, total_files, completed_files,
                GREATEST(total_files - completed_files, 0) AS pending_files,
                remarks, created_at, updated_at
         FROM ncb_task_file_tracking
         WHERE procurement_id = ? AND schedule_task_id = ?
         ORDER BY file_type ASC, id ASC"
    );
    $stmt->execute([$procurementId, $scheduleTaskId]);
    return array_map(function ($row) {
        $row['id'] = (int) $row['id'];
        $row['procurement_id'] = (int) $row['procurement_id'];
        $row['schedule_task_id'] = (int) $row['schedule_task_id'];
        $row['total_files'] = (int) $row['total_files'];
        $row['completed_files'] = (int) $row['completed_files'];
        $row['pending_files'] = (int) $row['pending_files'];
        return $row;
    }, $stmt->fetchAll());
}

function pmtsValidateTaskBelongsToProcurement(PDO $pdo, int $procurementId, int $scheduleTaskId): ?array {
    $stmt = $pdo->prepare(
        "SELECT s.id, s.task_name, s.procurement_id, p.procurement_id AS public_id
         FROM procurement_time_schedule s
         INNER JOIN procurements p ON p.id = s.procurement_id
         WHERE s.id = ? AND s.procurement_id = ?
         LIMIT 1"
    );
    $stmt->execute([$scheduleTaskId, $procurementId]);
    return $stmt->fetch() ?: null;
}
