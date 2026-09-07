<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_login('login.php');

$orderId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :u');
$stmt->execute(['id' => $orderId, 'u' => auth_user_id()]);
$order = $stmt->fetch();

if (!$order) {
    redirect('index.php');
}

$pageTitle = 'Order confirmed';
require __DIR__ . '/_header.php';
?>
<section class="empty-state">
  <h1>Thank you for your order</h1>
  <p>Order <strong>#<?= (int) $order['id'] ?></strong> is now <strong>pending payment confirmation</strong>. We'll update the status once your bank transfer is verified.</p>
  <div class="cart-actions">
    <a href="orders.php" class="btn-primary">View my orders</a>
    <a href="index.php" class="btn-secondary">Continue shopping</a>
  </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
