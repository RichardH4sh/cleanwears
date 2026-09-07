<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = (string) ($_GET['slug'] ?? '');
$stmt = db()->prepare(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.slug = :slug AND p.status = 'active'"
);
$stmt->execute(['slug' => $slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require __DIR__ . '/_header.php';
    echo '<div class="empty-state"><h2>Product not found</h2><a href="index.php">Back to shop</a></div>';
    require __DIR__ . '/_footer.php';
    exit;
}

$stmt = db()->prepare('SELECT image_path FROM product_images WHERE product_id = :id ORDER BY sort_order');
$stmt->execute(['id' => $product['id']]);
$gallery = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!$gallery) {
    $gallery = [$product['image_path']];
}

$sizes = array_filter(array_map('trim', explode(',', (string) $product['sizes'])));

$pageTitle = $product['name'];
require __DIR__ . '/_header.php';
?>
<section class="product-detail">
  <div class="product-gallery">
    <div class="product-gallery-main">
      <img id="main-image" src="<?= e(product_image_url($gallery[0])) ?>" alt="<?= e($product['name']) ?>">
    </div>
    <?php if (count($gallery) > 1): ?>
    <div class="product-gallery-thumbs">
      <?php foreach ($gallery as $img): ?>
        <img src="<?= e(product_image_url($img)) ?>" class="gallery-thumb" data-full="<?= e(product_image_url($img)) ?>" alt="">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="product-info">
    <a class="product-eyebrow" href="category.php?slug=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
    <h1><?= e($product['name']) ?></h1>
    <p class="product-price"><?= money((float) $product['price']) ?></p>
    <p class="product-description"><?= nl2br(e($product['description'] ?? '')) ?></p>

    <?php if ($product['stock_qty'] <= 0): ?>
      <p class="out-of-stock">Out of stock</p>
    <?php else: ?>
      <form id="add-to-cart-form" data-product-id="<?= (int) $product['id'] ?>">
        <?php if ($sizes): ?>
        <div class="size-select">
          <span>Size</span>
          <div class="size-options">
            <?php foreach ($sizes as $i => $size): ?>
              <label class="size-pill">
                <input type="radio" name="variant" value="<?= e($size) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <span><?= e($size) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="qty-row">
          <label for="qty">Qty</label>
          <input type="number" id="qty" name="quantity" min="1" max="<?= (int) $product['stock_qty'] ?>" value="1">
        </div>

        <button type="submit" class="btn-primary add-to-cart-btn">Add to cart</button>
        <p class="add-to-cart-message" aria-live="polite"></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<input type="hidden" id="csrf-token" value="<?= e(csrf_token()) ?>">
<?php require __DIR__ . '/_footer.php'; ?>
