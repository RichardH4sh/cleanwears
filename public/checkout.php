<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

// Not logged in -> straight to login, then back here after auth.
auth_require_login('login.php');

$user = auth_current_user();
$errors = [];

$stmt = db()->prepare(
    'SELECT c.id AS cart_id, c.quantity, c.variant, p.id AS product_id, p.name, p.price, p.stock_qty
     FROM cart c JOIN products p ON p.id = c.product_id
     WHERE c.user_id = :u ORDER BY c.created_at DESC'
);
$stmt->execute(['u' => auth_user_id()]);
$items = $stmt->fetchAll();

if (!$items) {
    redirect('cart.php');
}

$subtotal = array_sum(array_map(fn($i) => $i['quantity'] * (float) $i['price'], $items));

$bank = db()->query('SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);

    $shipName    = trim((string) ($_POST['ship_name'] ?? $user['name']));
    $shipPhone   = trim((string) ($_POST['ship_phone'] ?? $user['phone'] ?? ''));
    $shipAddress = trim((string) ($_POST['ship_address'] ?? $user['address'] ?? ''));

    if ($shipName === '') $errors[] = 'Name is required.';
    if ($shipPhone === '') $errors[] = 'Phone number is required.';
    if ($shipAddress === '') $errors[] = 'Delivery address is required.';

    // Re-check stock at the moment of purchase to avoid overselling.
    if (!$errors) {
        foreach ($items as $item) {
            if ($item['quantity'] > $item['stock_qty']) {
                $errors[] = $item['name'] . ' no longer has enough stock. Please update your cart.';
            }
        }
    }

    if (!$errors) {
        $db = db();
        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'INSERT INTO orders (user_id, total_amount, status, ship_name, ship_phone, ship_address)
                 VALUES (:u, :t, "pending_confirmation", :n, :p, :a)'
            );
            $stmt->execute([
                'u' => auth_user_id(), 't' => $subtotal, 'n' => $shipName, 'p' => $shipPhone, 'a' => $shipAddress,
            ]);
            $orderId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, variant, quantity, price_at_purchase)
                 VALUES (:o, :p, :n, :v, :q, :pr)'
            );
            $stockStmt = $db->prepare('UPDATE products SET stock_qty = stock_qty - :q WHERE id = :id AND stock_qty >= :q2');

            foreach ($items as $item) {
                $itemStmt->execute([
                    'o' => $orderId, 'p' => $item['product_id'], 'n' => $item['name'],
                    'v' => $item['variant'], 'q' => $item['quantity'], 'pr' => $item['price'],
                ]);
                $stockStmt->execute(['q' => $item['quantity'], 'id' => $item['product_id'], 'q2' => $item['quantity']]);
            }

            $db->prepare('DELETE FROM cart WHERE user_id = :u')->execute(['u' => auth_user_id()]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Checkout failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong placing your order. Please try again.';
        }

        if (!$errors) {
            // Email is sent AFTER the DB transaction commits, and failure never blocks the order.
            $orderRow = ['id' => $orderId, 'total_amount' => $subtotal, 'ship_phone' => $shipPhone, 'ship_address' => $shipAddress];
            $customer = ['name' => $shipName, 'email' => $user['email']];

            $sent = send_admin_new_order_email($orderRow, array_map(fn($i) => [
                'product_name' => $i['name'], 'variant' => $i['variant'], 'quantity' => $i['quantity'], 'price_at_purchase' => $i['price'],
            ], $items), $customer);

            if ($sent) {
                db()->prepare('UPDATE orders SET admin_email_sent = 1 WHERE id = :id')->execute(['id' => $orderId]);
            }

            send_customer_confirmation_email($orderRow, array_map(fn($i) => [
                'product_name' => $i['name'], 'variant' => $i['variant'], 'quantity' => $i['quantity'], 'price_at_purchase' => $i['price'],
            ], $items), $customer);

            flash_set('success', 'Thank you! Your order #' . $orderId . ' has been placed and is pending payment confirmation.');
            redirect('order-confirmation.php?id=' . $orderId);
        }
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/_header.php';
?>
<section class="checkout-page">
  <div class="checkout-summary">
    <h2>Order summary</h2>
    <table class="cart-table cart-table-compact">
      <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['name']) ?><?= $item['variant'] ? ' (' . e($item['variant']) . ')' : '' ?> &times; <?= (int) $item['quantity'] ?></td>
          <td class="cart-line-total"><?= money($item['quantity'] * (float) $item['price']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>Total</td><td class="cart-line-total"><strong><?= money($subtotal) ?></strong></td></tr></tfoot>
    </table>

    <div class="bank-details">
      <h3>Pay by bank transfer</h3>
      <?php if ($bank): ?>
        <p>Send the total above to the account below, then click <strong>Confirm Purchase</strong>. We'll verify your payment and update your order status.</p>
        <dl>
          <dt>Account name</dt><dd><?= e($bank['account_name']) ?></dd>
          <dt>Account number</dt><dd><?= e($bank['account_number']) ?></dd>
          <dt>Bank</dt><dd><?= e($bank['bank_name']) ?></dd>
        </dl>
      <?php else: ?>
        <p class="alert alert-error">Payment details are not configured yet. Please contact us before placing an order.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="checkout-form">
    <h2>Delivery details</h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" action="checkout.php" class="stack-form">
      <?= csrf_field() ?>
      <label for="ship_name">Full name</label>
      <input type="text" id="ship_name" name="ship_name" required value="<?= e($_POST['ship_name'] ?? $user['name']) ?>">
      <label for="ship_phone">Phone</label>
      <input type="tel" id="ship_phone" name="ship_phone" required value="<?= e($_POST['ship_phone'] ?? $user['phone'] ?? '') ?>">
      <label for="ship_address">Delivery address</label>
      <textarea id="ship_address" name="ship_address" rows="3" required><?= e($_POST['ship_address'] ?? $user['address'] ?? '') ?></textarea>
      <button type="submit" class="btn-primary" <?= $bank ? '' : 'disabled' ?>>Confirm Purchase</button>
      <p class="field-hint">Click this only after you've sent the bank transfer above.</p>
    </form>
  </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
