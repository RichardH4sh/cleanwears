<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php'; // ensures session is started

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Renders a hidden <input> field. Echo this inside every <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifies the token from POST data (or a JSON/AJAX payload) against the session token.
 * Kills the request with a 419 status on mismatch.
 */
function csrf_verify(?string $token): void
{
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Your session expired. Please refresh the page and try again.']);
        } else {
            die('Your session expired. Please go back and try again.');
        }
        exit;
    }
}
