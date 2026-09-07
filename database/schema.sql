-- =====================================================================
-- Clean Wears — E-Commerce Database Schema
-- Engine: InnoDB (foreign keys, transactions)
-- Charset: utf8mb4
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- CREATE DATABASE IF NOT EXISTS clean_wears
  -- CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE clean_wears;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(120)    NOT NULL,
  `email`         VARCHAR(190)    NOT NULL,
  `phone`         VARCHAR(30)     DEFAULT NULL,
  `address`       VARCHAR(500)    DEFAULT NULL,
  `password_hash` VARCHAR(255)    NOT NULL,
  `role`          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- categories
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(120) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- products
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(180)  NOT NULL,
  `slug`        VARCHAR(200)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `price`       DECIMAL(10,2) NOT NULL,
  `stock_qty`   INT UNSIGNED  NOT NULL DEFAULT 0,
  `category_id` INT UNSIGNED  NOT NULL,
  `image_path`  VARCHAR(255)  DEFAULT NULL,
  `sizes`       VARCHAR(255)  DEFAULT NULL COMMENT 'Comma-separated list, e.g. S,M,L,XL',
  `status`      ENUM('active','draft','archived') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_status` (`status`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product_images (supports multiple images per product)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  KEY `idx_product_images_product` (`product_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- cart (persisted per logged-in user)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `variant`    VARCHAR(50)  DEFAULT NULL COMMENT 'Size / variant label, e.g. M',
  `quantity`   INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cart_user_product_variant` (`user_id`,`product_id`,`variant`),
  KEY `idx_cart_user` (`user_id`),
  KEY `idx_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- orders
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT UNSIGNED NOT NULL,
  `total_amount`   DECIMAL(10,2) NOT NULL,
  `status`         ENUM('pending_confirmation','paid','shipped','cancelled') NOT NULL DEFAULT 'pending_confirmation',
  `ship_name`      VARCHAR(120)  NOT NULL,
  `ship_phone`     VARCHAR(30)   NOT NULL,
  `ship_address`   VARCHAR(500)  NOT NULL,
  `admin_email_sent` TINYINT(1)  NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- order_items
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`         INT UNSIGNED NOT NULL,
  `product_id`       INT UNSIGNED NOT NULL,
  `product_name`     VARCHAR(180) NOT NULL COMMENT 'Snapshot at time of purchase',
  `variant`          VARCHAR(50)  DEFAULT NULL,
  `quantity`         INT UNSIGNED NOT NULL,
  `price_at_purchase` DECIMAL(10,2) NOT NULL,
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- bank_accounts (admin-managed; shown to customers at checkout)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `bank_accounts`;
CREATE TABLE `bank_accounts` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `account_name`   VARCHAR(150) NOT NULL,
  `account_number` VARCHAR(50)  NOT NULL,
  `bank_name`      VARCHAR(150) NOT NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_bank_accounts_active` (`is_active`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- email_log (auditing of transactional emails)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `email_log`;
CREATE TABLE `email_log` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id`    INT UNSIGNED DEFAULT NULL,
  `recipient`   VARCHAR(190) NOT NULL,
  `subject`     VARCHAR(255) NOT NULL,
  `type`        ENUM('admin_new_order','customer_confirmation') NOT NULL,
  `status`      ENUM('sent','failed') NOT NULL,
  `error`       TEXT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_email_log_order` (`order_id`),
  CONSTRAINT `fk_email_log_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Seed data
-- =====================================================================

-- Default admin user. This row is created with a locked (unusable) password hash.
-- Run `php database/set_admin_password.php` once after import to set a real password
-- (it uses PHP's own password_hash(), so the hash is guaranteed valid on your server).
INSERT INTO `users` (`name`, `email`, `phone`, `address`, `password_hash`, `role`) VALUES
('Site Admin', 'admin@cleanwears.test', NULL, NULL, '*LOCKED*', 'admin');

INSERT INTO `categories` (`name`, `slug`) VALUES
('T-Shirts', 't-shirts'),
('Outerwear', 'outerwear'),
('Denim', 'denim');

INSERT INTO `products` (`name`, `slug`, `description`, `price`, `stock_qty`, `category_id`, `image_path`, `sizes`, `status`) VALUES
('Essential Crew Tee', 'essential-crew-tee', 'A heavyweight cotton crew-neck tee, garment-washed for a soft, lived-in feel.', 28.00, 40, 1, NULL, 'XS,S,M,L,XL', 'active'),
('Field Overshirt', 'field-overshirt', 'A brushed-cotton overshirt built for layering, with a boxy fit and dropped shoulder.', 96.00, 18, 2, NULL, 'S,M,L,XL', 'active'),
('Straight Fit Denim', 'straight-fit-denim', 'Rigid selvedge denim in a straight cut, made to break in over time.', 118.00, 25, 3, NULL, '28,30,32,34,36', 'active');

INSERT INTO `bank_accounts` (`account_name`, `account_number`, `bank_name`, `is_active`) VALUES
('Clean Wears Ltd', '0000000000', 'Example Bank', 1);
