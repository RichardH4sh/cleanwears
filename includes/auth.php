<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Regenerate the session id (call on login to prevent session fixation). */
function auth_regenerate_session(): void
{
    session_regenerate_id(true);
}

/** Log a user in: stores minimal identifying data in the session. */
function auth_login(array $user): void
{
    auth_regenerate_session();
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['name'];
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function auth_check(): bool
{
    return isset($_SESSION['user_id']);
}

function auth_is_admin(): bool
{
    return auth_check() && ($_SESSION['role'] ?? '') === 'admin';
}

function auth_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function auth_user_name(): ?string
{
    return $_SESSION['name'] ?? null;
}

/** Fetch the full current user row, or null if not logged in / not found. */
function auth_current_user(): ?array
{
    if (!auth_check()) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, name, email, phone, address, role, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => auth_user_id()]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/** Redirect to login if not authenticated as a customer/admin, preserving the intended URL. */
function auth_require_login(string $redirectTo = '/login.php'): void
{
    if (!auth_check()) {
        $return = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $redirectTo . '?return=' . $return);
        exit;
    }
}

/** Guard for admin-only pages. Every admin route must call this first. */
function auth_require_admin(string $redirectTo = 'login.php'): void
{
    if (!auth_is_admin()) {
        header('Location: ' . $redirectTo);
        exit;
    }
}
