<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Returns a shared PDO instance. Always uses prepared statements —
 * no raw string-concatenated SQL is used anywhere in this codebase.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Service temporarily unavailable. Please try again shortly.');
    }

    return $pdo;
}

// Backward-compatible global for scripts (e.g. CLI helpers) that expect $pdo directly.
$pdo = db();
