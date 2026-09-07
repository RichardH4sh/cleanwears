<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$slug = (string) ($_GET['slug'] ?? '');
$stmt = db()->prepare('SELECT * FROM categories WHERE slug = :slug');
$stmt->execute(['slug' => $slug]);
$category = $stmt->fetch();

if (!$category) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require __DIR__ . '/_header.php';
    echo '<div class="empty-state"><h2>Category not found</h2><a href="index.php">Back to shop</a></div>';
    require __DIR__ . '/_footer.php';
    exit;
}

$minPrice = isset($_GET['min']) && $_GET['min'] !== '' ? (float) $_GET['min'] : null;
$maxPrice = isset($_GET['max']) && $_GET['max'] !== '' ? (float) $_GET['max'] : null;
$sort     = (string) ($_GET['sort'] ?? 'newest');

$sql = "SELECT * FROM products WHERE category_id = :cid AND status = 'active'";
$params = ['cid' => $category['id']];

if ($minPrice !== null) { $sql .= ' AND price >= :min'; $params['min'] = $minPrice; }
if ($maxPrice !== null) { $sql .= ' AND price <= :max'; $params['max'] = $maxPrice; }

$sql .= match ($sort) {
    'price_asc'  => ' ORDER BY price ASC',
    'price_desc' => ' ORDER BY price DESC',
    'name'       => ' ORDER BY name ASC',
    default      => ' ORDER BY created_at DESC',
};

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = $category['name'];
require __DIR__ . '/_header.php';
?>
<section class="listing-head">
  <h1><?= e($category['name']) ?></h1>
  <form method="get" action="category.php" class="filter-bar">
    <input type="hidden" name="slug" value="<?= e($slug) ?>">
    <label>Min <input type="number" name="min" step="1" min="0" value="<?= e((string) ($minPrice ?? '')) ?>"></label>
    <label>Max <input type="number" name="max" step="1" min="0" value="<?= e((string) ($maxPrice ?? '')) ?>"></label>
    <label>Sort
      <select name="sort">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
      </select>
    </label>
    <button type="submit" class="btn-secondary">Apply</button>
  </form>
</section>

<section class="product-section">
  <div class="product-grid">
    <?php foreach ($products as $p): ?>
      <a class="product-card" href="product.php?slug=<?= e($p['slug']) ?>">
        <div class="product-card-image">
          <img src="<?= e(product_image_url($p['image_path'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        </div>
        <div class="product-card-body">
          <h3><?= e($p['name']) ?></h3>
          <span class="product-card-price"><?= money((float) $p['price']) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if (!$products): ?>
      <p>No products match those filters.</p>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
