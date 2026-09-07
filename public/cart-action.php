<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

function json_fail(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if (!auth_check()) {
    json_fail('Please log in to use the cart.', 401);
}

$input = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
$token = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? null);
csrf_verify($token);

$action = $input['action'] ?? ($_POST['action'] ?? '');
$userId = auth_user_id();
$db = db();

switch ($action) {
    case 'add': {
        $productId = (int) ($input['product_id'] ?? 0);
        $variant   = trim((string) ($input['variant'] ?? '')) ?: null;
        $quantity  = max(1, (int) ($input['quantity'] ?? 1));

        $stmt = $db->prepare("SELECT id, stock_qty FROM products WHERE id = :id AND status = 'active'");
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();
        if (!$product) json_fail('Product not found.', 404);
        if ($quantity > $product['stock_qty']) json_fail('Not enough stock available.');

        $stmt = $db->prepare(
            'INSERT INTO cart (user_id, product_id, variant, quantity) VALUES (:u, :p, :v, :q)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
        );
        $stmt->execute(['u' => $userId, 'p' => $productId, 'v' => $variant, 'q' => $quantity]);
        break;
    }

    case 'update': {
        $cartId = (int) ($input['cart_id'] ?? 0);
        $quantity = max(1, (int) ($input['quantity'] ?? 1));

        $stmt = $db->prepare(
            'SELECT c.id, p.stock_qty FROM cart c JOIN products p ON p.id = c.product_id
             WHERE c.id = :id AND c.user_id = :u'
        );
        $stmt->execute(['id' => $cartId, 'u' => $userId]);
        $row = $stmt->fetch();
        if (!$row) json_fail('Cart item not found.', 404);
        if ($quantity > $row['stock_qty']) json_fail('Not enough stock available.');

        $stmt = $db->prepare('UPDATE cart SET quantity = :q WHERE id = :id AND user_id = :u');
        $stmt->execute(['q' => $quantity, 'id' => $cartId, 'u' => $userId]);
        break;
    }

    case 'remove': {
        $cartId = (int) ($input['cart_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM cart WHERE id = :id AND user_id = :u');
        $stmt->execute(['id' => $cartId, 'u' => $userId]);
        break;
    }

    default:
        json_fail('Unknown action.');
}

// Return the fresh cart state so the UI can update without a reload.
$stmt = $db->prepare(
    'SELECT c.id AS cart_id, c.quantity, c.variant, p.id AS product_id, p.name, p.price, p.image_path, p.stock_qty
     FROM cart c JOIN products p ON p.id = c.product_id
     WHERE c.user_id = :u ORDER BY c.created_at DESC'
);
$stmt->execute(['u' => $userId]);
$items = $stmt->fetchAll();

$subtotal = 0.0;
$count = 0;
foreach ($items as &$item) {
    $item['line_total'] = round($item['quantity'] * (float) $item['price'], 2);
    $item['image_url'] = product_image_url($item['image_path']);
    unset($item['image_path']);
    $subtotal += $item['line_total'];
    $count += (int) $item['quantity'];
}
unset($item);

echo json_encode([
    'ok' => true,
    'items' => $items,
    'subtotal' => round($subtotal, 2),
    'subtotal_formatted' => money($subtotal),
    'count' => $count,
]);
