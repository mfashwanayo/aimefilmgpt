<?php
declare(strict_types=1);

/**
 * Shared PDO connection.
 * Set environment variables in Apache/PHP or edit the defaults for local XAMPP.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'aimefilms';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        error_log('AIMEFILMS database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('A database connection error occurred. Check the database settings.');
    }

    return $pdo;
}

/**
 * Compatibility wrapper: Stage 2 pages call Database::connection().
 * Both db() and Database::connection() return the same shared PDO object.
 */
final class Database
{
    public static function connection(): PDO
    {
        return db();
    }
}
