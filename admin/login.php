<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if (auth_is_admin()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $stmt = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email AND role = "admin"');
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();

        if ($admin && $admin['password_hash'] !== '*LOCKED*' && password_verify($password, $admin['password_hash'])) {
            auth_login($admin);
            redirect('dashboard.php');
        }
        $error = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
  <form class="admin-login-card" method="post" action="login.php" novalidate>
    <h1><?= e(APP_NAME) ?> <span>Admin</span></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <button type="submit">Log in</button>
  </form>
</body>
</html>
