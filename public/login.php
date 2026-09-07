<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if (auth_check()) {
    redirect('index.php');
}

$error = null;
$returnTo = (string) ($_GET['return'] ?? $_POST['return'] ?? 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email AND role = "customer"');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        auth_login($user);
        redirect($returnTo ?: 'index.php');
    }
    $error = 'Invalid email or password.';
}
?>
<?php $pageTitle = 'Log in'; require __DIR__ . '/_header.php'; ?>
<section class="auth-panel">
  <h1>Log in</h1>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="login.php?return=<?= e($returnTo) ?>" class="stack-form">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($returnTo) ?>">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <div class="form-actions">
      <button type="submit">Log in</button>
    </div>
  </form>
  <p class="auth-switch">New here? <a href="register.php?return=<?= e($returnTo) ?>">Create an account</a></p>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
