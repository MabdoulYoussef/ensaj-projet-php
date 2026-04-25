-- ENSAJ shop: starter schema (MySQL 8+ / MariaDB 10.3+)
-- Import: mysql -u root -p < database.sql

CREATE DATABASE IF NOT EXISTS ensaj_shop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ensaj_shop;

-- ---------------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(120) NOT NULL DEFAULT '',
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Categories (for product search/filter later)
-- ---------------------------------------------------------------------------
CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  slug        VARCHAR(140) NOT NULL,
  parent_id   INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_parent (parent_id),
  CONSTRAINT fk_categories_parent
    FOREIGN KEY (parent_id) REFERENCES categories (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Products
-- ---------------------------------------------------------------------------
CREATE TABLE products (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  name        VARCHAR(200) NOT NULL,
  slug        VARCHAR(220) NOT NULL,
  description TEXT NULL,
  price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock       INT UNSIGNED NOT NULL DEFAULT 0,
  image_path  VARCHAR(255) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_slug (slug),
  KEY idx_products_category (category_id),
  KEY idx_products_active_price (is_active, price),
  FULLTEXT KEY ft_products_name_desc (name, description),
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Persistent cart: cart_key = "u:123" (user) or "s:SESSION_ID" (guest)
-- One row per (cart_key, product_id); avoids NULL issues with UNIQUE indexes.
-- ---------------------------------------------------------------------------
CREATE TABLE cart_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_key     VARCHAR(128) NOT NULL,
  user_id      INT UNSIGNED NULL,
  product_id   INT UNSIGNED NOT NULL,
  quantity     INT UNSIGNED NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_product (cart_key, product_id),
  KEY idx_cart_user (user_id),
  KEY idx_cart_key (cart_key),
  CONSTRAINT fk_cart_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cart_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Orders
-- ---------------------------------------------------------------------------
CREATE TABLE orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NULL,
  status          ENUM('pending','paid','shipped','cancelled') NOT NULL DEFAULT 'pending',
  total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  shipping_name   VARCHAR(120) NOT NULL,
  shipping_email  VARCHAR(255) NOT NULL,
  shipping_phone  VARCHAR(40) NULL,
  shipping_address TEXT NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_orders_user (user_id),
  KEY idx_orders_status_created (status, created_at),
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  unit_price  DECIMAL(10,2) NOT NULL,
  quantity    INT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Demo data (optional)
-- ---------------------------------------------------------------------------
INSERT INTO categories (name, slug) VALUES
  ('Electronics', 'electronics'),
  ('Books', 'books');

INSERT INTO products (category_id, name, slug, description, price, stock, is_active) VALUES
  (1, 'Sample USB Cable', 'sample-usb-cable', 'Demo product for development.', 9.99, 50, 1),
  (2, 'PHP Learning Guide', 'php-learning-guide', 'Starter book placeholder.', 19.50, 30, 1);
