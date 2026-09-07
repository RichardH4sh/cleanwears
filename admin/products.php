<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = db()->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute(['id' => $id]);
    flash_set('success', 'Product deleted.');
    redirect('products.php');
}

$products = db()->query(
    'SELECT p.*, c.name AS category_name
     FROM products p JOIN categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC'
)->fetchAll();

$pageTitle = 'Products';
$active = 'products';
require __DIR__ . '/_header.php';
?>
<section class="panel">
  <div class="panel-head-row">
    <h2>All products</h2>
    <a href="product-form.php" class="btn-primary">Add product</a>
  </div>
  <table class="admin-table">
    <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><img class="thumb" src="<?= e(product_image_url($p['image_path'])) ?>" alt=""></td>
        <td><?= e($p['name']) ?></td>
        <td><?= e($p['category_name']) ?></td>
        <td><?= money((float) $p['price']) ?></td>
        <td><?= (int) $p['stock_qty'] ?></td>
        <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
        <td class="table-actions">
          <a href="product-form.php?id=<?= (int) $p['id'] ?>">Edit</a>
          <form method="post" action="products.php" onsubmit="return confirm('Delete this product?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="link-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?>
      <tr><td colspan="7">No products yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
