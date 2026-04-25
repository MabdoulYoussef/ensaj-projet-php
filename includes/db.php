<?php
/**
 * Single PDO connection for the app. Always use prepared statements.
 */
declare(strict_types=1);

if (!defined('DB_HOST')) {
    $configPath = dirname(__DIR__) . '/config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException(
            'Missing config/config.php. Copy config/config.example.php to config/config.php and edit credentials.'
        );
    }
    require $configPath;
}

/**
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
