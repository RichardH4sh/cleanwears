<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_admin();

$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            flash_set('error', 'Category name is required.');
        } else {
            $slug = slugify($name);
            try {
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE categories SET name = :name, slug = :slug WHERE id = :id');
                    $stmt->execute(['name' => $name, 'slug' => $slug, 'id' => $id]);
                    flash_set('success', 'Category updated.');
                } else {
                    $stmt = db()->prepare('INSERT INTO categories (name, slug) VALUES (:name, :slug)');
                    $stmt->execute(['name' => $name, 'slug' => $slug]);
                    flash_set('success', 'Category created.');
                }
            } catch (PDOException $e) {
                flash_set('error', 'A category with that name already exists.');
            }
        }
        redirect('categories.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = db()->prepare('DELETE FROM categories WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Category deleted.');
        } catch (PDOException $e) {
            // FK RESTRICT on products.category_id
            flash_set('error', 'Cannot delete a category that still has products assigned to it.');
        }
        redirect('categories.php');
    }
}

if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$categories = db()->query(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c ORDER BY c.name'
)->fetchAll();

$pageTitle = 'Categories';
$active = 'categories';
require __DIR__ . '/_header.php';
?>
<section class="panel panel-narrow">
  <h2><?= $editing ? 'Edit category' : 'Add category' ?></h2>
  <form method="post" action="categories.php" class="stack-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" required maxlength="100" value="<?= e($editing['name'] ?? '') ?>">
    <div class="form-actions">
      <button type="submit"><?= $editing ? 'Save changes' : 'Add category' ?></button>
      <?php if ($editing): ?><a href="categories.php" class="btn-secondary">Cancel</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="panel">
  <h2>All categories</h2>
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Slug</th><th>Products</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
      <tr>
        <td><?= e($c['name']) ?></td>
        <td><?= e($c['slug']) ?></td>
        <td><?= (int) $c['product_count'] ?></td>
        <td class="table-actions">
          <a href="categories.php?edit=<?= (int) $c['id'] ?>">Edit</a>
          <form method="post" action="categories.php" onsubmit="return confirm('Delete this category?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button type="submit" class="link-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$categories): ?>
      <tr><td colspan="4">No categories yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
