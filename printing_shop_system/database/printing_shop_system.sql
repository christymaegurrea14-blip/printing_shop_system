-- ============================================================
-- PRINTING SHOP MANAGEMENT SYSTEM - DATABASE SCHEMA
-- Compatible with MySQL 5.6, 5.7, 8.0 and MariaDB 10+
-- ============================================================

CREATE DATABASE IF NOT EXISTS `printing_shop_system`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `printing_shop_system`;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(100)  NOT NULL,
  `username`       VARCHAR(50)   NOT NULL,
  `email`          VARCHAR(150)  NOT NULL,
  `password`       VARCHAR(255)  NOT NULL,
  `role`           ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `avatar`         VARCHAR(255)  DEFAULT NULL,
  `remember_token` VARCHAR(255)  DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: customers
-- ============================================================
CREATE TABLE `customers` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)  NOT NULL,
  `email`      VARCHAR(150)  DEFAULT NULL,
  `phone`      VARCHAR(20)   DEFAULT NULL,
  `address`    TEXT          DEFAULT NULL,
  `notes`      TEXT          DEFAULT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: orders
-- receipt_no VARCHAR(30) — format: RCP-XXXXXX-YYYYMMDD = 22 chars, padded to 30
-- ============================================================
CREATE TABLE `orders` (
  `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `receipt_no`   VARCHAR(30)    NOT NULL,
  `customer_id`  INT UNSIGNED   NOT NULL,
  `print_type`   ENUM('black_white','colored','photo_print','tarpaulin','id_picture') NOT NULL,
  `paper_size`   VARCHAR(50)    NOT NULL,
  `quantity`     INT UNSIGNED   NOT NULL DEFAULT 1,
  `unit_price`   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `file_path`    VARCHAR(255)   DEFAULT NULL,
  `file_name`    VARCHAR(255)   DEFAULT NULL,
  `notes`        TEXT           DEFAULT NULL,
  `status`       ENUM('pending','processing','completed','claimed') NOT NULL DEFAULT 'pending',
  `created_by`   INT UNSIGNED   NOT NULL,
  `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt_no`  (`receipt_no`),
  KEY `idx_customer`          (`customer_id`),
  KEY `idx_status`            (`status`),
  KEY `idx_created`           (`created_at`),
  CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_user`     FOREIGN KEY (`created_by`)  REFERENCES `users`     (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: payments
-- payment_date uses DATETIME NULL to avoid MySQL TIMESTAMP conflicts
-- ============================================================
CREATE TABLE `payments` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`       INT UNSIGNED  NOT NULL,
  `amount_paid`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','gcash','maya','bank_transfer','other') NOT NULL DEFAULT 'cash',
  `payment_status` ENUM('paid','unpaid','partial') NOT NULL DEFAULT 'unpaid',
  `payment_date`   DATETIME      DEFAULT NULL,
  `notes`          TEXT          DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pay_order`  (`order_id`),
  KEY `idx_pay_status` (`payment_status`),
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: sales  (optional daily aggregate — kept for reporting)
-- ============================================================
CREATE TABLE `sales` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sale_date`     DATE          NOT NULL,
  `total_orders`  INT UNSIGNED  NOT NULL DEFAULT 0,
  `total_revenue` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_paid`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_unpaid`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sale_date` (`sale_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEFAULT ADMIN ACCOUNT
-- Username : admin
-- Password : admin123  (PHP bcrypt hash below)
-- ============================================================
INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`)
VALUES (
  'Administrator',
  'admin',
  'admin@printshop.com',
  '$2b$12$Ppexp1OYLWpTmE5AXZe6CepUBPnxRW.BJnfMiIWSU1SfLsgx1A9ya',
  'admin'
);
