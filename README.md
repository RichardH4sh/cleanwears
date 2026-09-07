# Clean Wears

A full-stack e-commerce site for a clothing brand: a customer storefront with
cart/checkout (manual bank-transfer payment) and an admin dashboard for
managing categories, products, bank details, and orders.

**Stack:** PHP 8.1+ (PDO/MySQLi, prepared statements everywhere) · MySQL 8 ·
HTML5/CSS3 · vanilla JS + jQuery for AJAX · PHPMailer for transactional email.

---

## 1. Requirements

- PHP 8.1 or later, with the `pdo_mysql` and `fileinfo` extensions enabled
- MySQL 8 (or MariaDB 10.4+)
- Composer (for PHPMailer)
- A local server: PHP's built-in server, XAMPP, MAMP, or any Apache/Nginx + PHP-FPM setup

## 2. Get the code running

```bash
cd clean-wears
composer install                 # installs PHPMailer into /vendor
cp config/.env.example config/.env
```

Edit `config/.env` and fill in:

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` — your MySQL credentials
- `APP_URL` — the base URL where `/public` is served (see step 4)
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`,
  `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` — your SMTP provider's details
- `ADMIN_NOTIFY_EMAIL` — the inbox that should receive "new order" emails

> **SMTP credentials weren't provided yet.** The `.env.example` file has
> placeholder values — the site runs fine without them (order emails will
> simply fail gracefully and get logged to the `email_log` table and PHP's
> error log), but fill them in before relying on the email notification.

## 3. Import the database

```bash
mysql -u root -p < database/schema.sql
```

This creates the `clean_wears` database, all tables (with foreign keys and
indexes), and seed data: 3 sample categories, 3 sample products, one bank
account row, and an admin user (`admin@cleanwears.test`) with a **locked**
password.

Set a real admin password (this uses PHP's own `password_hash()`, so it's
guaranteed to be a valid bcrypt hash on your server):

```bash
php database/set_admin_password.php admin@cleanwears.test "YourStrongPassword123"
```

## 4. Run it locally

Simplest option — PHP's built-in server, pointed at `/public` for the
storefront:

```bash
php -S localhost:8000 -t public
```

Visit `http://localhost:8000`. The admin dashboard lives outside the
document root you just served, so for local dev either:

- run a second server for it: `php -S localhost:8001 -t admin`, or
- serve the whole project root instead of just `/public`:
  `php -S localhost:8000` (from the project root), then visit
  `http://localhost:8000/public/` and `http://localhost:8000/admin/`.

If you're using XAMPP/MAMP/a full Apache+PHP setup instead, point your vhost's
document root at the project root (not `/public`) so both `/public` and
`/admin` are reachable, and set `APP_URL` in `.env` to match
(e.g. `http://localhost/clean-wears/public`).

Log in to the admin dashboard at `/admin/login.php` with the email/password
you set in step 3.

## 5. Uploaded product images

Product images are stored on disk under `uploads/products/` (created
automatically on first upload) and referenced in the `products.image_path`
and `product_images.image_path` columns. Make sure that folder is writable by
your web server user:

```bash
chmod -R 755 uploads/products
```

## 6. How the checkout flow works

This is a **manual/offline payment model**, not a payment gateway:

1. Customer adds items to their cart (AJAX, no page reloads — `assets/js/cart.js` + `public/cart-action.php`).
2. At checkout, if not logged in, they're sent to `login.php` and returned to `checkout.php` afterward.
3. Checkout shows the order summary and the bank account details currently
   set by the admin (`admin/bank-account.php` → `bank_accounts` table).
4. Customer manually sends the bank transfer, then clicks **Confirm Purchase**.
5. The order is created with status `pending_confirmation`, the cart is
   cleared, stock is decremented, and:
   - an email is sent to `ADMIN_NOTIFY_EMAIL` with the customer's name,
     email, phone, delivery address, itemized order, and total
   - an optional confirmation email is sent to the customer
   - **email failures never block order creation** — they're caught, logged
     to PHP's error log and the `email_log` table, and the order still
     succeeds
6. Admin reviews the order in `admin/orders.php`, verifies the bank payment
   manually, and updates the status to `paid` → `shipped` (or `cancelled`).

## 7. Project structure

```
/admin        Admin dashboard (login, dashboard, categories, products, bank account, orders)
/public       Customer storefront (home, category, product, cart, login/register, checkout, orders)
/includes     Shared PHP: db.php, auth.php, csrf.php, functions.php, mailer.php
/config       config.php (env loader + constants), .env.example
/database     schema.sql, set_admin_password.php
/assets       css/, js/, img/
/uploads      Uploaded product images (created at runtime)
```

## 8. Security notes

- All queries use PDO prepared statements — no string-concatenated SQL.
- Passwords are hashed with `password_hash()` (bcrypt).
- Every state-changing form (login, register, checkout, all admin forms,
  cart AJAX calls) carries a CSRF token verified server-side.
- All dynamic output is escaped with `htmlspecialchars()` via the `e()` helper.
- Admin routes call `auth_require_admin()` at the top of every file.
- Session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` when served over HTTPS.
- Change the seed admin password immediately (step 3) — the seeded row ships
  locked and unusable until you do.

## 9. Known limitations / next steps

- No payment gateway — this is intentionally a manual bank-transfer flow per
  the project brief.
- No automated test suite is included.
- Multiple product images: the schema supports several images per product
  (`product_images` table) but the admin UI currently uploads one primary
  image per product. Extending `product-form.php` to manage a gallery is a
  straightforward next step if you need it.
