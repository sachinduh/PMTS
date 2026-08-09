<?php
// Shared helper for committee appointment letters used by the NCB Time Schedule.

if (!function_exists('pmtsEnsureCommitteeLettersSchema')) {
    function pmtsCommitteeIdentifierAllowed(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $value);
    }

    function pmtsCommitteeColumnExists(PDO $pdo, string $table, string $column): bool
    {
        // MariaDB/MySQL can throw a syntax error when parameter markers are used
        // inside SHOW COLUMNS ... LIKE ?. Query information_schema instead so this
        // works reliably with XAMPP/MariaDB and server-side prepared statements.
        if (!pmtsCommitteeIdentifierAllowed($table) || !pmtsCommitteeIdentifierAllowed($column)) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    function pmtsCommitteeIndexExists(PDO $pdo, string $table, string $index): bool
    {
        // Same reason as above: avoid SHOW INDEX ... WHERE Key_name = ?.
        if (!pmtsCommitteeIdentifierAllowed($table) || !pmtsCommitteeIdentifierAllowed($index)) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ");
        $stmt->execute([$table, $index]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    function pmtsEnsureCommitteeLettersSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS committee_letters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            procurement_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            committee_type VARCHAR(50) NOT NULL,
            committee_position VARCHAR(30) NOT NULL DEFAULT 'Member',
            member_name VARCHAR(150) NOT NULL,
            member_designation VARCHAR(150),
            member_email VARCHAR(150),
            letter_date DATE,
            appointment_planned_date DATE DEFAULT NULL,
            letter_body TEXT,
            sent_at DATETIME DEFAULT NULL,
            last_email_attempt_at DATETIME DEFAULT NULL,
            email_status VARCHAR(50) DEFAULT 'not_sent',
            email_error TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!pmtsCommitteeColumnExists($pdo, 'committee_letters', 'user_id')) {
            $pdo->exec("ALTER TABLE committee_letters ADD COLUMN user_id INT DEFAULT NULL AFTER procurement_id");
        }
        if (!pmtsCommitteeColumnExists($pdo, 'committee_letters', 'committee_position')) {
            $pdo->exec("ALTER TABLE committee_letters ADD COLUMN committee_position VARCHAR(30) NOT NULL DEFAULT 'Member' AFTER committee_type");
        }
        if (!pmtsCommitteeColumnExists($pdo, 'committee_letters', 'appointment_planned_date')) {
            $pdo->exec("ALTER TABLE committee_letters ADD COLUMN appointment_planned_date DATE DEFAULT NULL AFTER letter_date");
        }
        if (!pmtsCommitteeColumnExists($pdo, 'committee_letters', 'sent_at')) {
            $pdo->exec("ALTER TABLE committee_letters ADD COLUMN sent_at DATETIME DEFAULT NULL AFTER letter_body");
        }
        if (!pmtsCommitteeColumnExists($pdo, 'committee_letters', 'last_email_attempt_at')) {
            $pdo->exec("ALTER TABLE committee_letters ADD COLUMN last_email_attempt_at DATETIME DEFAULT NULL AFTER sent_at");
        }
        if (!pmtsCommitteeColumnExists($pdo, 'committee_letters', 'email_status')) {
            $pdo->exec("ALTER TABLE committee_letters ADD COLUMN email_status VARCHAR(50) DEFAULT 'not_sent' AFTER last_email_attempt_at");
        }
        if (!pmtsCommitteeColumnExists($pdo, 'committee_letters', 'email_error')) {
            $pdo->exec("ALTER TABLE committee_letters ADD COLUMN email_error TEXT DEFAULT NULL AFTER email_status");
        }
        if (!pmtsCommitteeIndexExists($pdo, 'committee_letters', 'idx_committee_letters_proc_type')) {
            $pdo->exec("ALTER TABLE committee_letters ADD KEY idx_committee_letters_proc_type (procurement_id, committee_type)");
        }
        if (!pmtsCommitteeIndexExists($pdo, 'committee_letters', 'idx_committee_letters_user')) {
            $pdo->exec("ALTER TABLE committee_letters ADD KEY idx_committee_letters_user (user_id)");
        }
    }

    function pmtsNormalizeCommitteeType(string $committeeType): string
    {
        $committeeType = trim($committeeType);
        if (strcasecmp($committeeType, 'bec') === 0 || stripos($committeeType, 'bid evaluation') !== false) {
            return 'BEC';
        }
        if (stripos($committeeType, 'spec') !== false) {
            return 'Specification';
        }
        return $committeeType;
    }

    function pmtsOtherCommitteeType(string $committeeType): string
    {
        return pmtsNormalizeCommitteeType($committeeType) === 'BEC' ? 'Specification' : 'BEC';
    }

    function pmtsCommitteeRoleForType(string $committeeType): string
    {
        $committeeType = pmtsNormalizeCommitteeType($committeeType);
        return $committeeType === 'BEC' ? 'bec_member' : 'specification_committee';
    }

    function pmtsCommitteeTaskNameForType(string $committeeType): string
    {
        return pmtsNormalizeCommitteeType($committeeType) === 'BEC'
            ? 'Appoint Bid Evaluation Committee (BEC)'
            : 'Appoint Specification Preparation Committee';
    }

    function pmtsGetCommitteeTaskPlannedDate(PDO $pdo, int $procurementId, string $committeeType): ?string
    {
        $taskName = pmtsCommitteeTaskNameForType($committeeType);
        $stmt = $pdo->prepare("SELECT planned_date FROM procurement_time_schedule WHERE procurement_id = ? AND task_name = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
        $stmt->execute([$procurementId, $taskName]);
        $date = $stmt->fetchColumn();
        return $date ? substr((string) $date, 0, 10) : null;
    }

    function pmtsBuildCommitteeLetterBody(array $procurement, string $committeeType, string $position, ?string $plannedDate = null): string
    {
        $committeeType = pmtsNormalizeCommitteeType($committeeType);
        $title = $procurement['title'] ?? 'the above procurement';
        $tender = $procurement['tender_number'] ?? ($procurement['procurement_id'] ?? '');
        $committeeName = $committeeType === 'BEC' ? 'Bid Evaluation Committee (BEC)' : 'Specification Preparation Committee';
        $plannedLine = $plannedDate ? "Planned Appointment Date: {$plannedDate}\n\n" : '';

        return "You are hereby appointed as {$position} of the {$committeeName} for the procurement: {$title}.\n\n"
            . ($tender ? "Tender / Procurement Reference: {$tender}\n" : '')
            . $plannedLine
            . "You are requested to carry out the assigned duties according to applicable government procurement guidelines, maintain confidentiality, avoid conflicts of interest, and complete committee work within the approved procurement time schedule.";
    }

    function pmtsBuildCommitteeEmailSubject(array $procurement, string $committeeType): string
    {
        $committeeName = pmtsNormalizeCommitteeType($committeeType) === 'BEC' ? 'BEC' : 'Specification Committee';
        $reference = $procurement['tender_number'] ?? ($procurement['procurement_id'] ?? 'Procurement');
        return "PMTS Appointment Letter - {$committeeName} - {$reference}";
    }

    function pmtsBuildCommitteeEmailBody(array $letter, array $procurement): string
    {
        $title = $procurement['title'] ?? 'Procurement';
        $reference = $procurement['tender_number'] ?? ($procurement['procurement_id'] ?? '');
        $position = $letter['committee_position'] ?? 'Member';
        $committee = pmtsNormalizeCommitteeType($letter['committee_type'] ?? '') === 'BEC'
            ? 'Bid Evaluation Committee (BEC)'
            : 'Specification Preparation Committee';
        $plannedDate = $letter['appointment_planned_date'] ?? ($letter['letter_date'] ?? '');

        return "Dear {$letter['member_name']},\n\n"
            . "You have been appointed as {$position} of the {$committee}.\n\n"
            . "Procurement: {$title}\n"
            . ($reference ? "Reference: {$reference}\n" : '')
            . "Appointment Letter Date: " . ($letter['letter_date'] ?? date('Y-m-d')) . "\n"
            . ($plannedDate ? "Planned Appointment Date: {$plannedDate}\n" : '')
            . "\n"
            . ($letter['letter_body'] ?? '')
            . "\n\nRegards,\nProcurement Management Tracking System";
    }
}
