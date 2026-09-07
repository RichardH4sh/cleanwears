<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_admin();

$validStatuses = ['pending_confirmation', 'paid', 'shipped', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if (in_array($status, $validStatuses, true)) {
        $stmt = db()->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        flash_set('success', 'Order #' . $id . ' marked as ' . str_replace('_', ' ', $status) . '.');
    }
    redirect('orders.php' . ($id ? '?id=' . $id : ''));
}

$viewingId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$viewingOrder = null;
$viewingItems = [];
$viewingCustomer = null;

if ($viewingId) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = :id');
    $stmt->execute(['id' => $viewingId]);
    $viewingOrder = $stmt->fetch() ?: null;

    if ($viewingOrder) {
        $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $stmt->execute(['id' => $viewingId]);
        $viewingItems = $stmt->fetchAll();

        $stmt = db()->prepare('SELECT name, email, phone FROM users WHERE id = :id');
        $stmt->execute(['id' => $viewingOrder['user_id']]);
        $viewingCustomer = $stmt->fetch() ?: null;
    }
}

$orders = db()->query(
    'SELECT o.id, o.total_amount, o.status, o.created_at, u.name AS customer_name
     FROM orders o JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC'
)->fetchAll();

$pageTitle = 'Orders';
$active = 'orders';
require __DIR__ . '/_header.php';
?>

<?php if ($viewingOrder): ?>
<section class="panel panel-narrow">
  <div class="panel-head-row">
    <h2>Order #<?= (int) $viewingOrder['id'] ?></h2>
    <a href="orders.php">&larr; All orders</a>
  </div>

  <table class="admin-table admin-table-plain">
    <tr><th>Customer</th><td><?= e($viewingCustomer['name'] ?? '') ?></td></tr>
    <tr><th>Email</th><td><?= e($viewingCustomer['email'] ?? '') ?></td></tr>
    <tr><th>Phone</th><td><?= e($viewingOrder['ship_phone']) ?></td></tr>
    <tr><th>Delivery address</th><td><?= nl2br(e($viewingOrder['ship_address'])) ?></td></tr>
    <tr><th>Placed</th><td><?= e(date('M j, Y g:i A', strtotime($viewingOrder['created_at']))) ?></td></tr>
    <tr><th>Admin email sent</th><td><?= $viewingOrder['admin_email_sent'] ? 'Yes' : 'No (check email_log for errors)' ?></td></tr>
  </table>

  <h3>Items</h3>
  <table class="admin-table">
    <thead><tr><th>Product</th><th>Variant</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
    <tbody>
    <?php $total = 0; foreach ($viewingItems as $it): $lineTotal = $it['quantity'] * (float) $it['price_at_purchase']; $total += $lineTotal; ?>
      <tr>
        <td><?= e($it['product_name']) ?></td>
        <td><?= e($it['variant'] ?? '—') ?></td>
        <td><?= (int) $it['quantity'] ?></td>
        <td><?= money((float) $it['price_at_purchase']) ?></td>
        <td><?= money($lineTotal) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="4" style="text-align:right;font-weight:600;">Total</td><td><?= money((float) $viewingOrder['total_amount']) ?></td></tr></tfoot>
  </table>

  <h3>Update status</h3>
  <form method="post" action="orders.php" class="inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="id" value="<?= (int) $viewingOrder['id'] ?>">
    <select name="status">
      <?php foreach ($validStatuses as $s): ?>
        <option value="<?= $s ?>" <?= $viewingOrder['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Update</button>
  </form>
</section>
<?php endif; ?>

<section class="panel">
  <h2>All orders</h2>
  <table class="admin-table">
    <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td>#<?= (int) $o['id'] ?></td>
        <td><?= e($o['customer_name']) ?></td>
        <td><?= money((float) $o['total_amount']) ?></td>
        <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
        <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
        <td><a href="orders.php?id=<?= (int) $o['id'] ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
      <tr><td colspan="6">No orders yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
