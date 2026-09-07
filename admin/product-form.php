<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_admin();

$product = ['id' => 0, 'name' => '', 'description' => '', 'price' => '', 'stock_qty' => '', 'category_id' => '', 'sizes' => '', 'status' => 'active', 'image_path' => null];
$isEdit = false;

if (isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['id']]);
    $found = $stmt->fetch();
    if ($found) {
        $product = $found;
        $isEdit = true;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);

    $id          = (int) ($_POST['id'] ?? 0);
    $name        = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $price       = (string) ($_POST['price'] ?? '');
    $stockQty    = (string) ($_POST['stock_qty'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $sizes       = trim((string) ($_POST['sizes'] ?? ''));
    $status      = (string) ($_POST['status'] ?? 'active');

    $product['id']          = $id;
    $product['name']        = $name;
    $product['description'] = $description;
    $product['price']       = $price;
    $product['stock_qty']   = $stockQty;
    $product['category_id'] = $categoryId;
    $product['sizes']       = $sizes;
    $product['status']      = $status;

    if ($name === '') $errors[] = 'Name is required.';
    if (!is_numeric($price) || (float) $price < 0) $errors[] = 'Enter a valid price.';
    if (!ctype_digit($stockQty)) $errors[] = 'Enter a valid stock quantity.';
    if ($categoryId <= 0) $errors[] = 'Choose a category.';
    if (!in_array($status, ['active', 'draft', 'archived'], true)) $errors[] = 'Invalid status.';

    $imagePath = $product['image_path'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        try {
            $uploaded = handle_product_image_upload($_FILES['image']);
            if ($uploaded) {
                $imagePath = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $slug = slugify($name) . '-' . substr(md5((string) microtime(true)), 0, 5);
        try {
            if ($id > 0) {
                $stmt = db()->prepare(
                    'UPDATE products SET name=:name, description=:description, price=:price, stock_qty=:stock_qty,
                     category_id=:category_id, image_path=:image_path, sizes=:sizes, status=:status WHERE id=:id'
                );
                $stmt->execute([
                    'name' => $name, 'description' => $description, 'price' => $price, 'stock_qty' => $stockQty,
                    'category_id' => $categoryId, 'image_path' => $imagePath, 'sizes' => $sizes, 'status' => $status, 'id' => $id,
                ]);
                flash_set('success', 'Product updated.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO products (name, slug, description, price, stock_qty, category_id, image_path, sizes, status)
                     VALUES (:name, :slug, :description, :price, :stock_qty, :category_id, :image_path, :sizes, :status)'
                );
                $stmt->execute([
                    'name' => $name, 'slug' => $slug, 'description' => $description, 'price' => $price, 'stock_qty' => $stockQty,
                    'category_id' => $categoryId, 'image_path' => $imagePath, 'sizes' => $sizes, 'status' => $status,
                ]);
                flash_set('success', 'Product created.');
            }
            redirect('products.php');
        } catch (PDOException $e) {
            $errors[] = 'Could not save the product. Please try again.';
        }
    }
}

// Categories are pulled live from the DB every time this form renders —
// a newly created category (see categories.php) shows up here immediately.
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$pageTitle = $isEdit ? 'Edit Product' : 'Add Product';
$active = 'products';
require __DIR__ . '/_header.php';
?>
<section class="panel panel-narrow">
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="post" action="product-form.php<?= $isEdit ? '?id=' . (int) $product['id'] : '' ?>" enctype="multipart/form-data" class="stack-form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">

    <label for="name">Product name</label>
    <input type="text" id="name" name="name" required maxlength="180" value="<?= e($product['name']) ?>">

    <label for="description">Description</label>
    <textarea id="description" name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea>

    <div class="form-row">
      <div>
        <label for="price">Price (USD)</label>
        <input type="number" id="price" name="price" step="0.01" min="0" required value="<?= e((string) $product['price']) ?>">
      </div>
      <div>
        <label for="stock_qty">Stock quantity</label>
        <input type="number" id="stock_qty" name="stock_qty" min="0" required value="<?= e((string) $product['stock_qty']) ?>">
      </div>
    </div>

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required>
      <option value="">Select a category&hellip;</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $product['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (!$categories): ?>
      <p class="field-hint">No categories yet — <a href="categories.php">create one first</a>.</p>
    <?php endif; ?>

    <label for="sizes">Available sizes (comma-separated, optional)</label>
    <input type="text" id="sizes" name="sizes" placeholder="S, M, L, XL" value="<?= e($product['sizes'] ?? '') ?>">

    <label for="status">Status</label>
    <select id="status" name="status">
      <?php foreach (['active', 'draft', 'archived'] as $s): ?>
        <option value="<?= $s ?>" <?= $product['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="image">Product image</label>
    <?php if (!empty($product['image_path'])): ?>
      <img class="thumb-lg" src="<?= e(product_image_url($product['image_path'])) ?>" alt="">
    <?php endif; ?>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">

    <div class="form-actions">
      <button type="submit"><?= $isEdit ? 'Save changes' : 'Add product' ?></button>
      <a href="products.php" class="btn-secondary">Cancel</a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
