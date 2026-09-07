<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if (auth_check()) {
    redirect('index.php');
}

$errors = [];
$name = $email = $phone = $address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);

    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $phone    = trim((string) ($_POST['phone'] ?? ''));
    $address  = trim((string) ($_POST['address'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!is_valid_email($email)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($phone === '') $errors[] = 'Phone number is required.';
    if ($address === '') $errors[] = 'Delivery address is required.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, phone, address, password_hash, role) VALUES (:n, :e, :p, :a, :h, "customer")'
        );
        $stmt->execute(['n' => $name, 'e' => $email, 'p' => $phone, 'a' => $address, 'h' => $hash]);

        $stmt = db()->prepare('SELECT id, name, role FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        auth_login($stmt->fetch());

        $return = $_GET['return'] ?? $_POST['return'] ?? 'index.php';
        redirect($return ?: 'index.php');
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/_header.php';
?>
<section class="auth-panel">
  <h1>Create your account</h1>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" action="register.php<?= isset($_GET['return']) ? '?return=' . e($_GET['return']) : '' ?>" class="stack-form">
    <?= csrf_field() ?>
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" required value="<?= e($name) ?>">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required value="<?= e($email) ?>">
    <label for="phone">Phone</label>
    <input type="tel" id="phone" name="phone" required value="<?= e($phone) ?>">
    <label for="address">Delivery address</label>
    <textarea id="address" name="address" rows="3" required><?= e($address) ?></textarea>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required minlength="8">
    <div class="form-actions">
      <button type="submit">Create account</button>
    </div>
  </form>
  <p class="auth-switch">Already have an account? <a href="login.php<?= isset($_GET['return']) ? '?return=' . e($_GET['return']) : '' ?>">Log in</a></p>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
