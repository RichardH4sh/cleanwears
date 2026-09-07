<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$products = db()->query(
    "SELECT p.*, c.name AS category_name FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active'
     ORDER BY p.created_at DESC LIMIT 12"
)->fetchAll();

$pageTitle = 'Shop all';
require __DIR__ . '/_header.php';
?>
<section class="hero">
  <div class="hero-copy">
    <h1>Clothes built to be worn, not just bought.</h1>
    <p>Small runs of considered basics — heavyweight cotton, real denim, cut to last past one season.</p>
    <a href="#new-arrivals" class="btn-primary">Shop new arrivals</a>
  </div>
</section>

<section class="product-section" id="new-arrivals">
  <div class="section-head">
    <h2>New arrivals</h2>
  </div>
  <div class="product-grid">
    <?php foreach ($products as $p): ?>
      <a class="product-card" href="product.php?slug=<?= e($p['slug']) ?>">
        <div class="product-card-image">
          <img src="<?= e(product_image_url($p['image_path'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        </div>
        <div class="product-card-body">
          <span class="product-card-category"><?= e($p['category_name']) ?></span>
          <h3><?= e($p['name']) ?></h3>
          <span class="product-card-price"><?= money((float) $p['price']) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if (!$products): ?>
      <p>No products yet — check back soon.</p>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
