<?php
spl_autoload_register(function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $baseDir = __DIR__ . '/PHPMailer/PHPMailer/src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
