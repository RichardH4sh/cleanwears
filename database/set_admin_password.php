<?php
/**
 * One-time CLI helper: sets a real, properly-hashed password for the seed
 * admin account (admin@cleanwears.test) created by schema.sql.
 *
 * Usage (from the project root, after importing database.sql and configuring .env):
 *   php database/set_admin_password.php you@example.com "YourNewStrongPassword"
 *
 * If no email is given, defaults to admin@cleanwears.test.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require __DIR__ . '/../includes/db.php';

$email = $argv[1] ?? 'admin@cleanwears.test';
$password = $argv[2] ?? null;

if (!$password) {
    fwrite(STDERR, "Usage: php database/set_admin_password.php <email> <new-password>\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE email = :email AND role = "admin"');
$stmt->execute(['hash' => $hash, 'email' => $email]);

if ($stmt->rowCount() === 0) {
    fwrite(STDERR, "No admin user found with email {$email}.\n");
    exit(1);
}

echo "Password updated for {$email}. You can now log in at /admin/login.php\n";
