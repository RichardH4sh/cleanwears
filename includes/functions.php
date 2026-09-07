<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Escape output for safe HTML rendering (XSS prevention). */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return '$' . number_format($amount, 2);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** One-time flash messages, e.g. flash_set('success', 'Saved.'); then flash_get('success'). */
function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (empty($_SESSION['flash'][$key])) {
        return null;
    }
    $msg = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
    $text = trim($text, '-');
    $text = iconv('utf-8', 'ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text) ?? '';
    return $text !== '' ? $text : 'n-a';
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Handles a single uploaded product image: validates type/size and moves it
 * into UPLOAD_DIR with a random filename. Returns the relative path to store
 * in the DB (e.g. "uploads/products/xxxx.jpg"), or null if no file was sent.
 * Throws RuntimeException on validation failure.
 */
function handle_product_image_upload(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image is too large. Max size is 5MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        throw new RuntimeException('Only JPEG, PNG, or WEBP images are allowed.');
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = UPLOAD_DIR . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    return 'uploads/products/' . $filename;
}

/** Resolves a stored product image_path to a browser-usable URL, with a fallback placeholder. */
function product_image_url(?string $path): string
{
    if (!$path) {
        return APP_URL . '/assets/img/placeholder.svg';
    }
    return APP_URL . '/../' . ltrim($path, '/');
}
