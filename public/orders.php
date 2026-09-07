<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_login('login.php');

$stmt = db()->prepare('SELECT * FROM orders WHERE user_id = :u ORDER BY created_at DESC');
$stmt->execute(['u' => auth_user_id()]);
$orders = $stmt->fetchAll();

$viewingId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$viewingItems = [];
if ($viewingId) {
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :id AND order_id IN (SELECT id FROM orders WHERE user_id = :u)');
    $stmt->execute(['id' => $viewingId, 'u' => auth_user_id()]);
    $viewingItems = $stmt->fetchAll();
}

$pageTitle = 'Your orders';
require __DIR__ . '/_header.php';
?>
<section class="orders-page">
  <h1>Your orders</h1>

  <?php if (!$orders): ?>
    <div class="empty-state">
      <p>You haven't placed any orders yet.</p>
      <a href="index.php" class="btn-primary">Start shopping</a>
    </div>
  <?php else: ?>
    <table class="cart-table">
      <thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?= (int) $o['id'] ?></td>
          <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
          <td><?= money((float) $o['total_amount']) ?></td>
          <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
          <td><a href="orders.php?id=<?= (int) $o['id'] ?>">Details</a></td>
        </tr>
        <?php if ($viewingId === (int) $o['id']): ?>
        <tr class="order-detail-row">
          <td colspan="5">
            <table class="cart-table cart-table-compact">
              <tbody>
              <?php foreach ($viewingItems as $it): ?>
                <tr>
                  <td><?= e($it['product_name']) ?><?= $it['variant'] ? ' (' . e($it['variant']) . ')' : '' ?> &times; <?= (int) $it['quantity'] ?></td>
                  <td class="cart-line-total"><?= money($it['quantity'] * (float) $it['price_at_purchase']) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
