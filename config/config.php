<?php
/**
 * Central configuration bootstrap.
 * Loads .env (if present) into getenv()/$_ENV and exposes typed constants.
 * No third-party dependency required for env loading — kept intentionally tiny.
 */

declare(strict_types=1);

function clothline_load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        // Strip matching surrounding quotes, if any.
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

clothline_load_env(__DIR__ . '/.env');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

// --- Database ---
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'clean_wears'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// --- App ---
define('APP_URL', rtrim((string) env('APP_URL', ''), '/'));
define('APP_NAME', env('APP_NAME', 'Clean Wears'));
define('SESSION_NAME', env('SESSION_NAME', 'clean_wears_session'));

// --- SMTP ---
define('SMTP_HOST', env('SMTP_HOST', ''));
define('SMTP_PORT', (int) env('SMTP_PORT', '587'));
define('SMTP_ENCRYPTION', env('SMTP_ENCRYPTION', 'tls'));
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', 'no-reply@cleanwears.test'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', APP_NAME));
define('ADMIN_NOTIFY_EMAIL', env('ADMIN_NOTIFY_EMAIL', SMTP_FROM_EMAIL));

// --- Uploads ---
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/products');
define('UPLOAD_URL', APP_URL . '/../uploads/products');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// --- Error display: OFF in production. Flip via APP_DEBUG=1 in .env for local dev. ---
if (env('APP_DEBUG', '0') === '1') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}
