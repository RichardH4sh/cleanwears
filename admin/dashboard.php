<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_admin();

$totalOrders   = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalProducts = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$pendingOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending_confirmation'")->fetchColumn();
$revenue       = (float) db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status IN ('paid','shipped')")->fetchColumn();

$recentOrders = db()->query(
    'SELECT o.id, o.total_amount, o.status, o.created_at, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 8'
)->fetchAll();

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/_header.php';
?>
<section class="stat-grid">
  <div class="stat-card"><span class="stat-label">Total orders</span><span class="stat-value"><?= $totalOrders ?></span></div>
  <div class="stat-card"><span class="stat-label">Pending confirmation</span><span class="stat-value"><?= $pendingOrders ?></span></div>
  <div class="stat-card"><span class="stat-label">Products</span><span class="stat-value"><?= $totalProducts ?></span></div>
  <div class="stat-card"><span class="stat-label">Revenue (paid + shipped)</span><span class="stat-value"><?= money($revenue) ?></span></div>
</section>

<section class="panel">
  <h2>Recent orders</h2>
  <table class="admin-table">
    <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td><a href="orders.php?id=<?= (int) $o['id'] ?>">#<?= (int) $o['id'] ?></a></td>
        <td><?= e($o['customer_name']) ?></td>
        <td><?= money((float) $o['total_amount']) ?></td>
        <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
        <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recentOrders): ?>
      <tr><td colspan="5">No orders yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
