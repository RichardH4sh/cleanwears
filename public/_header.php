<?php
declare(strict_types=1);
/** @var string $pageTitle */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$navCategories = db()->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();

$cartCount = 0;
if (auth_check()) {
    $stmt = db()->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = :uid');
    $stmt->execute(['uid' => auth_user_id()]);
    $cartCount = (int) $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? '') ?><?= isset($pageTitle) ? ' — ' : '' ?><?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="site-header-inner">
    <a href="index.php" class="wordmark"><?= e(APP_NAME) ?></a>
    <nav class="main-nav">
      <a href="index.php">Shop all</a>
      <?php foreach ($navCategories as $cat): ?>
        <a href="category.php?slug=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="header-actions">
      <?php if (auth_check()): ?>
        <a href="orders.php">Orders</a>
        <a href="logout.php" onclick="event.preventDefault();document.getElementById('logout-form').submit();">Log out</a>
        <form id="logout-form" action="logout.php" method="post" hidden><?= csrf_field() ?></form>
      <?php else: ?>
        <a href="login.php">Log in</a>
      <?php endif; ?>
      <a href="cart.php" class="cart-link">Cart<span class="cart-count" id="cart-count"><?= $cartCount ?></span></a>
    </div>
  </div>
</header>
<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success page-alert"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error page-alert"><?= e($msg) ?></div>
<?php endif; ?>
<main class="site-main">
