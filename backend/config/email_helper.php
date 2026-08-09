<?php
// ============================================================
// PMTS – SMTP/PHPMailer Email Helper
// Sends real emails through SMTP when backend/config/smtp_config.php
// is configured. A small PHPMailer-compatible SMTP sender is bundled
// in backend/vendor so Composer is not required in XAMPP.
// ============================================================

require_once __DIR__ . '/smtp_config.php';

$pmtsAutoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
foreach ($pmtsAutoloaders as $pmtsAutoloader) {
    if (is_file($pmtsAutoloader)) {
        require_once $pmtsAutoloader;
        break;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!function_exists('pmtsSmtpConfigured')) {
    function pmtsSmtpConfigured(): bool
    {
        return defined('PMTS_SMTP_ENABLED')
            && PMTS_SMTP_ENABLED
            && trim((string) PMTS_SMTP_HOST) !== ''
            && (int) PMTS_SMTP_PORT > 0
            && trim((string) PMTS_MAIL_FROM_EMAIL) !== ''
            && (!PMTS_SMTP_AUTH || (trim((string) PMTS_SMTP_USERNAME) !== '' && trim((string) PMTS_SMTP_PASSWORD) !== ''));
    }
}

if (!function_exists('pmtsSmtpStatus')) {
    function pmtsSmtpStatus(): array
    {
        $missing = [];
        if (!defined('PMTS_SMTP_ENABLED') || !PMTS_SMTP_ENABLED) $missing[] = 'PMTS_SMTP_ENABLED must be true';
        if (trim((string) PMTS_SMTP_HOST) === '') $missing[] = 'PMTS_SMTP_HOST is empty';
        if ((int) PMTS_SMTP_PORT <= 0) $missing[] = 'PMTS_SMTP_PORT is empty/invalid';
        if (trim((string) PMTS_MAIL_FROM_EMAIL) === '') $missing[] = 'PMTS_MAIL_FROM_EMAIL is empty';
        if (PMTS_SMTP_AUTH && trim((string) PMTS_SMTP_USERNAME) === '') $missing[] = 'PMTS_SMTP_USERNAME is empty';
        if (PMTS_SMTP_AUTH && trim((string) PMTS_SMTP_PASSWORD) === '') $missing[] = 'PMTS_SMTP_PASSWORD is empty';
        if (!class_exists(PHPMailer::class)) $missing[] = 'PHPMailer class is not available';
        if ((PMTS_SMTP_SECURE === 'tls' || PMTS_SMTP_SECURE === 'ssl') && !extension_loaded('openssl')) $missing[] = 'PHP openssl extension is not enabled';

        return [
            'configured' => count($missing) === 0,
            'enabled' => defined('PMTS_SMTP_ENABLED') && PMTS_SMTP_ENABLED,
            'host' => PMTS_SMTP_HOST,
            'port' => (int) PMTS_SMTP_PORT,
            'secure' => PMTS_SMTP_SECURE,
            'auth' => (bool) PMTS_SMTP_AUTH,
            'username_set' => trim((string) PMTS_SMTP_USERNAME) !== '',
            'password_set' => trim((string) PMTS_SMTP_PASSWORD) !== '',
            'from_email' => PMTS_MAIL_FROM_EMAIL,
            'from_name' => PMTS_MAIL_FROM_NAME,
            'reply_to' => PMTS_MAIL_REPLY_TO,
            'phpmailer_available' => class_exists(PHPMailer::class),
            'openssl_enabled' => extension_loaded('openssl'),
            'missing' => $missing,
        ];
    }
}

if (!function_exists('pmtsEnsureEmailLogSchema')) {
    function pmtsEnsureEmailLogSchema(PDO $pdo): void
    {
        // Create the table if it is missing. If the user created an older/different
        // version manually, the column checks below add the columns needed by code.
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_send_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = [
            'related_table' => "VARCHAR(100) DEFAULT NULL",
            'related_id' => "INT DEFAULT NULL",
            'letter_id' => "INT DEFAULT NULL",
            'procurement_id' => "INT DEFAULT NULL",
            'committee_type' => "VARCHAR(50) DEFAULT NULL",
            'recipient_name' => "VARCHAR(255) DEFAULT NULL",
            'recipient_email' => "VARCHAR(190) NOT NULL DEFAULT ''",
            'subject' => "VARCHAR(255) DEFAULT NULL",
            'email_status' => "VARCHAR(50) NOT NULL DEFAULT 'pending'",
            'error_message' => "TEXT DEFAULT NULL",
            'sent_at' => "DATETIME DEFAULT NULL",
            'attempted_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        ];

        $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_send_logs' AND COLUMN_NAME = ?");
        foreach ($columns as $column => $definition) {
            $check->execute([$column]);
            if ((int) $check->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE email_send_logs ADD COLUMN `$column` $definition");
            }
        }
    }
}

if (!function_exists('pmtsLogEmailAttempt')) {
    function pmtsLogEmailAttempt(?PDO $pdo, string $to, string $subject, array $emailResult, ?string $relatedTable = null, ?int $relatedId = null): void
    {
        if (!$pdo) return;
        try {
            pmtsEnsureEmailLogSchema($pdo);
            $status = !empty($emailResult['success']) ? 'sent' : 'failed';
            $sentAt = !empty($emailResult['success']) ? date('Y-m-d H:i:s') : null;
            $error = !empty($emailResult['success']) ? null : ($emailResult['message'] ?? 'Unknown email error');
            $stmt = $pdo->prepare("INSERT INTO email_send_logs (related_table, related_id, recipient_email, subject, email_status, error_message, sent_at, attempted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$relatedTable, $relatedId, $to, $subject, $status, $error, $sentAt, date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            // Do not stop the main workflow if logging itself fails.
        }
    }
}

if (!function_exists('pmtsSendEmail')) {
    function pmtsSendEmail(string $to, string $subject, string $body, array $options = []): array
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid recipient email.'];
        }

        if (!class_exists(PHPMailer::class)) {
            return [
                'success' => false,
                'message' => 'PHPMailer is not available. Keep backend/vendor in the project or install phpmailer/phpmailer with Composer.',
            ];
        }

        if (!pmtsSmtpConfigured()) {
            $status = pmtsSmtpStatus();
            $reason = $status['missing'] ? implode('; ', $status['missing']) : 'Unknown SMTP configuration problem';
            return [
                'success' => false,
                'message' => 'SMTP is not configured correctly: ' . $reason,
                'smtp_status' => $status,
            ];
        }

        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = PMTS_SMTP_HOST;
            $mail->SMTPAuth = (bool) PMTS_SMTP_AUTH;
            $mail->Username = PMTS_SMTP_USERNAME;
            $mail->Password = PMTS_SMTP_PASSWORD;
            $mail->Port = (int) PMTS_SMTP_PORT;
            $mail->Timeout = $options['timeout'] ?? 30;

            $secure = strtolower(trim((string) PMTS_SMTP_SECURE));
            if ($secure === 'tls' || $secure === 'starttls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($secure === 'ssl' || $secure === 'smtps') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
            }

            $fromEmail = $options['from_email'] ?? PMTS_MAIL_FROM_EMAIL;
            $fromName = $options['from_name'] ?? PMTS_MAIL_FROM_NAME;
            $replyTo = $options['reply_to'] ?? PMTS_MAIL_REPLY_TO;

            $mail->setFrom($fromEmail, $fromName);
            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, $fromName);
            }
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(!empty($options['is_html']));
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            $mail->send();

            return ['success' => true, 'message' => 'Email sent through SMTP/PHPMailer.'];
        } catch (PHPMailerException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
