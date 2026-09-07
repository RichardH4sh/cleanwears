<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_login('login.php');

$stmt = db()->prepare(
    'SELECT c.id AS cart_id, c.quantity, c.variant, p.id AS product_id, p.name, p.price, p.image_path, p.stock_qty
     FROM cart c JOIN products p ON p.id = c.product_id
     WHERE c.user_id = :u ORDER BY c.created_at DESC'
);
$stmt->execute(['u' => auth_user_id()]);
$items = $stmt->fetchAll();

$subtotal = array_sum(array_map(fn($i) => $i['quantity'] * (float) $i['price'], $items));

$pageTitle = 'Your cart';
require __DIR__ . '/_header.php';
?>
<section class="cart-page">
  <h1>Your cart</h1>

  <div id="cart-empty-state" class="empty-state" style="<?= $items ? 'display:none;' : '' ?>">
    <p>Your cart is empty.</p>
    <a href="index.php" class="btn-primary">Continue shopping</a>
  </div>

  <div id="cart-content" style="<?= $items ? '' : 'display:none;' ?>">
    <table class="cart-table">
      <thead><tr><th></th><th>Item</th><th>Price</th><th>Qty</th><th>Total</th><th></th></tr></thead>
      <tbody id="cart-rows">
        <?php foreach ($items as $item): ?>
        <tr data-cart-id="<?= (int) $item['cart_id'] ?>">
          <td><img class="thumb" src="<?= e(product_image_url($item['image_path'])) ?>" alt=""></td>
          <td>
            <?= e($item['name']) ?>
            <?php if ($item['variant']): ?><div class="cart-variant">Size: <?= e($item['variant']) ?></div><?php endif; ?>
          </td>
          <td class="cart-price"><?= money((float) $item['price']) ?></td>
          <td>
            <input type="number" class="cart-qty-input" min="1" max="<?= (int) $item['stock_qty'] ?>" value="<?= (int) $item['quantity'] ?>" data-cart-id="<?= (int) $item['cart_id'] ?>">
          </td>
          <td class="cart-line-total"><?= money($item['quantity'] * (float) $item['price']) ?></td>
          <td><button type="button" class="link-danger cart-remove-btn" data-cart-id="<?= (int) $item['cart_id'] ?>">Remove</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="cart-summary">
      <span>Subtotal</span>
      <span id="cart-subtotal"><?= money($subtotal) ?></span>
    </div>
    <div class="cart-actions">
      <a href="index.php" class="btn-secondary">Continue shopping</a>
      <a href="checkout.php" class="btn-primary">Proceed to checkout</a>
    </div>
  </div>
</section>
<input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">
<?php require __DIR__ . '/_footer.php'; ?>
