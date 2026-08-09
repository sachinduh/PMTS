<?php
// ============================================================
// PMTS SMTP Settings for PHPMailer
// ============================================================
// Fill these values with your real SMTP account details.
// Gmail example:
//   PMTS_SMTP_HOST      = smtp.gmail.com
//   PMTS_SMTP_PORT      = 587
//   PMTS_SMTP_SECURE    = tls
//   PMTS_SMTP_USERNAME  = your Gmail address
//   PMTS_SMTP_PASSWORD  = your Gmail App Password, not normal password
//
// Security note: Do not upload/share a ZIP after adding a real password.
// ============================================================

if (!function_exists('pmtsEnvValue')) {
    function pmtsEnvValue(string $key, $default = '') {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('pmtsEnvBool')) {
    function pmtsEnvBool(string $key, bool $default = false): bool {
        $value = getenv($key);
        if ($value === false || $value === '') return $default;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}

// Change this to true after adding real SMTP username/password below.
if (!defined('PMTS_SMTP_ENABLED')) {
    define('PMTS_SMTP_ENABLED', pmtsEnvBool('PMTS_SMTP_ENABLED', false));
}

if (!defined('PMTS_SMTP_HOST')) {
    define('PMTS_SMTP_HOST', pmtsEnvValue('PMTS_SMTP_HOST', 'smtp.gmail.com'));
}

if (!defined('PMTS_SMTP_PORT')) {
    define('PMTS_SMTP_PORT', (int) pmtsEnvValue('PMTS_SMTP_PORT', 587)); // Gmail STARTTLS: 587, SSL: 465
}

if (!defined('PMTS_SMTP_SECURE')) {
    define('PMTS_SMTP_SECURE', pmtsEnvValue('PMTS_SMTP_SECURE', 'tls')); // tls, ssl, or empty string
}

if (!defined('PMTS_SMTP_AUTH')) {
    define('PMTS_SMTP_AUTH', pmtsEnvBool('PMTS_SMTP_AUTH', true));
}

if (!defined('PMTS_SMTP_USERNAME')) {
    define('PMTS_SMTP_USERNAME', pmtsEnvValue('PMTS_SMTP_USERNAME', '')); // Example: yourhospitalemail@gmail.com
}

if (!defined('PMTS_SMTP_PASSWORD')) {
    define('PMTS_SMTP_PASSWORD', pmtsEnvValue('PMTS_SMTP_PASSWORD', '')); // Gmail App Password or SMTP password
}

if (!defined('PMTS_MAIL_FROM_EMAIL')) {
    define('PMTS_MAIL_FROM_EMAIL', pmtsEnvValue('PMTS_MAIL_FROM_EMAIL', PMTS_SMTP_USERNAME ?: 'no-reply@pmts.local'));
}

if (!defined('PMTS_MAIL_FROM_NAME')) {
    define('PMTS_MAIL_FROM_NAME', pmtsEnvValue('PMTS_MAIL_FROM_NAME', 'PMTS - Badulla Hospital'));
}

if (!defined('PMTS_MAIL_REPLY_TO')) {
    define('PMTS_MAIL_REPLY_TO', pmtsEnvValue('PMTS_MAIL_REPLY_TO', PMTS_MAIL_FROM_EMAIL));
}
