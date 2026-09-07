<?php
declare(strict_types=1);
/** @var string $pageTitle expected to be set by the including page */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e(APP_NAME) ?> Admin</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-brand"><?= e(APP_NAME) ?><span>Admin</span></div>
    <nav class="admin-nav">
      <a href="dashboard.php" class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
      <a href="categories.php" class="<?= ($active ?? '') === 'categories' ? 'active' : '' ?>">Categories</a>
      <a href="products.php" class="<?= ($active ?? '') === 'products' ? 'active' : '' ?>">Products</a>
      <a href="orders.php" class="<?= ($active ?? '') === 'orders' ? 'active' : '' ?>">Orders</a>
      <a href="bank-account.php" class="<?= ($active ?? '') === 'bank' ? 'active' : '' ?>">Bank Account</a>
    </nav>
    <form action="logout.php" method="post" class="admin-logout">
      <?= csrf_field() ?>
      <button type="submit">Log out</button>
    </form>
  </aside>
  <main class="admin-main">
    <header class="admin-topbar">
      <h1><?= e($pageTitle ?? '') ?></h1>
      <span class="admin-user"><?= e(auth_user_name() ?? '') ?></span>
    </header>
    <?php if ($msg = flash_get('success')): ?>
      <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('error')): ?>
      <div class="alert alert-error"><?= e($msg) ?></div>
    <?php endif; ?>
