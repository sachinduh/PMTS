<?php
// ============================================================
// PMTS – Simple Email Helper
// Uses PHP mail() so it works without Composer. For real email
// sending in XAMPP/production, configure SMTP/sendmail in php.ini
// or replace this helper with PHPMailer SMTP settings.
// ============================================================

if (!function_exists('pmtsSendEmail')) {
    function pmtsSendEmail(string $to, string $subject, string $body): array
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid recipient email.'];
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: PMTS Alerts <no-reply@pmts.local>',
            'Reply-To: no-reply@pmts.local',
            'X-Mailer: PHP/' . phpversion(),
        ];

        $ok = false;
        try {
            $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if (!$ok) {
            return [
                'success' => false,
                'message' => 'PHP mail() failed. Configure SMTP/sendmail in php.ini or use PHPMailer for production email.',
            ];
        }

        return ['success' => true, 'message' => 'Email sent.'];
    }
}
