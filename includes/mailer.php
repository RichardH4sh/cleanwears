<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Builds a ready-to-send PHPMailer instance configured from .env.
 * Throws if PHPMailer isn't installed (composer install not run yet).
 */
function make_mailer(): PHPMailer
{
    if (!class_exists(PHPMailer::class)) {
        throw new RuntimeException(
            'PHPMailer is not installed. Run "composer install" in the project root.'
        );
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPAuth   = SMTP_USERNAME !== '';
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

    return $mail;
}

/** Writes an entry to email_log for auditing, regardless of send outcome. */
function log_email(?int $orderId, string $recipient, string $subject, string $type, bool $sent, ?string $error = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO email_log (order_id, recipient, subject, type, status, error)
         VALUES (:order_id, :recipient, :subject, :type, :status, :error)'
    );
    $stmt->execute([
        'order_id'  => $orderId,
        'recipient' => $recipient,
        'subject'   => $subject,
        'type'      => $type,
        'status'    => $sent ? 'sent' : 'failed',
        'error'     => $error,
    ]);
}

/**
 * Builds the itemized HTML table used in both the admin and customer emails.
 * @param array<int, array{product_name:string, variant:?string, quantity:int, price_at_purchase:float}> $items
 */
function build_order_items_html(array $items, float $total): string
{
    $rows = '';
    foreach ($items as $item) {
        $lineTotal = $item['quantity'] * (float) $item['price_at_purchase'];
        $variant = $item['variant'] ? ' (' . e($item['variant']) . ')' : '';
        $rows .= '<tr>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . e($item['product_name']) . $variant . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:center;">' . (int) $item['quantity'] . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">' . money((float) $item['price_at_purchase']) . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;">' . money($lineTotal) . '</td>'
            . '</tr>';
    }

    return '<table style="width:100%;border-collapse:collapse;font-family:sans-serif;font-size:14px;">'
        . '<thead><tr style="text-align:left;background:#f5f3f0;">'
        . '<th style="padding:6px 10px;">Item</th><th style="padding:6px 10px;text-align:center;">Qty</th>'
        . '<th style="padding:6px 10px;text-align:right;">Unit price</th><th style="padding:6px 10px;text-align:right;">Line total</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody>'
        . '<tfoot><tr><td colspan="3" style="padding:10px;text-align:right;font-weight:bold;">Total</td>'
        . '<td style="padding:10px;text-align:right;font-weight:bold;">' . money($total) . '</td></tr></tfoot></table>';
}

/**
 * Sends the "new order" notification to the admin. Never throws — failures are
 * logged and returned as false so order creation is never blocked (per spec).
 *
 * @param array $order  Row from `orders` (must include id, total_amount, ship_name, ship_phone, ship_address)
 * @param array $items  Rows from `order_items`
 * @param array $customer  ['name' => ..., 'email' => ...]
 */
function send_admin_new_order_email(array $order, array $items, array $customer): bool
{
    $subject = 'New order #' . $order['id'] . ' — ' . APP_NAME;
    $itemsHtml = build_order_items_html($items, (float) $order['total_amount']);

    $body = '<h2 style="font-family:sans-serif;">New order received</h2>'
        . '<p style="font-family:sans-serif;">A customer has confirmed a purchase and should have made a bank transfer. Verify payment before marking the order as paid.</p>'
        . '<table style="font-family:sans-serif;font-size:14px;margin-bottom:16px;">'
        . '<tr><td style="padding:2px 10px 2px 0;color:#666;">Customer</td><td>' . e($customer['name']) . '</td></tr>'
        . '<tr><td style="padding:2px 10px 2px 0;color:#666;">Email</td><td>' . e($customer['email']) . '</td></tr>'
        . '<tr><td style="padding:2px 10px 2px 0;color:#666;">Phone</td><td>' . e($order['ship_phone']) . '</td></tr>'
        . '<tr><td style="padding:2px 10px 2px 0;color:#666;">Delivery address</td><td>' . nl2br(e($order['ship_address'])) . '</td></tr>'
        . '<tr><td style="padding:2px 10px 2px 0;color:#666;">Order #</td><td>' . (int) $order['id'] . '</td></tr>'
        . '</table>'
        . $itemsHtml;

    try {
        $mail = make_mailer();
        $mail->addAddress(ADMIN_NOTIFY_EMAIL);
        $mail->addReplyTo($customer['email'], $customer['name']);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = "New order #{$order['id']} from {$customer['name']} ({$customer['email']}). Total: " . money((float) $order['total_amount']);
        $mail->send();
        log_email((int) $order['id'], ADMIN_NOTIFY_EMAIL, $subject, 'admin_new_order', true);
        return true;
    } catch (Throwable $e) {
        error_log('Admin order email failed: ' . $e->getMessage());
        log_email((int) $order['id'], ADMIN_NOTIFY_EMAIL, $subject, 'admin_new_order', false, $e->getMessage());
        return false;
    }
}

/** Optional confirmation email to the customer. Also never throws. */
function send_customer_confirmation_email(array $order, array $items, array $customer): bool
{
    $subject = 'We received your order #' . $order['id'] . ' — ' . APP_NAME;
    $itemsHtml = build_order_items_html($items, (float) $order['total_amount']);

    $body = '<h2 style="font-family:sans-serif;">Thanks for your order, ' . e($customer['name']) . '</h2>'
        . '<p style="font-family:sans-serif;">We\'ve recorded your order and are waiting to confirm your bank transfer. '
        . 'We\'ll update your order status once payment is verified.</p>'
        . $itemsHtml;

    try {
        $mail = make_mailer();
        $mail->addAddress($customer['email'], $customer['name']);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = "Thanks for your order #{$order['id']}. Total: " . money((float) $order['total_amount']);
        $mail->send();
        log_email((int) $order['id'], $customer['email'], $subject, 'customer_confirmation', true);
        return true;
    } catch (Throwable $e) {
        error_log('Customer confirmation email failed: ' . $e->getMessage());
        log_email((int) $order['id'], $customer['email'], $subject, 'customer_confirmation', false, $e->getMessage());
        return false;
    }
}
