<?php

// XAMPP default database settings.
// If your MySQL uses another port/password, change these values only.
define('DB_HOST',    getenv('PMTS_DB_HOST') ?: '127.0.0.1');
define('DB_PORT',    getenv('PMTS_DB_PORT') ?: '3306');
// PMTS uses one fixed clean database. Ignore stale Apache PMTS_DB_NAME values
// that may still point to the previous pmts_db database.
define('DB_NAME',    'pmtss_db');
define('DB_USER',    getenv('PMTS_DB_USER') ?: 'root');
define('DB_PASS',    getenv('PMTS_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');

            $technicalError = $e->getMessage();
            $friendlyMessage = 'Database connection failed. Start XAMPP MySQL and check backend/config/db.php settings.';

            if (stripos($technicalError, 'Unknown database') !== false) {
                $friendlyMessage = 'Database pmtss_db was not found. Import database/pmtss_db.sql in phpMyAdmin.';
            } elseif (stripos($technicalError, 'Access denied') !== false) {
                $friendlyMessage = 'MySQL username or password is wrong. Update DB_USER/DB_PASS in backend/config/db.php.';
            } elseif (stripos($technicalError, 'Connection refused') !== false || stripos($technicalError, 'No connection') !== false) {
                $friendlyMessage = 'MySQL is not running or the port is wrong. Start XAMPP MySQL and check DB_PORT in backend/config/db.php.';
            }

            echo json_encode([
                'success' => false,
                'message' => $friendlyMessage,
                'database' => DB_NAME,
                'host' => DB_HOST,
                'port' => DB_PORT,
                'details' => $technicalError
            ]);
            exit;
        }
    }
    return $pdo;
}
