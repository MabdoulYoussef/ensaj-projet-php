-- ENSAJ shop database for phpMyAdmin import
-- Includes only: products, orders, order_items

CREATE DATABASE IF NOT EXISTS ensaj_shop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ensaj_shop;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE products (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(200) NOT NULL,
  description TEXT NULL,
  price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock       INT UNSIGNED NOT NULL DEFAULT 0,
  image_path  VARCHAR(255) NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_products_active_price (is_active, price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NULL,
  status           ENUM('pending','paid','shipped','cancelled') NOT NULL DEFAULT 'pending',
  total_amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  shipping_name    VARCHAR(120) NOT NULL,
  shipping_email   VARCHAR(255) NOT NULL,
  shipping_phone   VARCHAR(40) NULL,
  shipping_address TEXT NOT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_orders_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  quantity     INT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_order_items_order (order_id),
  KEY idx_order_items_product (product_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO products (name, description, price, stock, image_path, is_active) VALUES
  ('Baggy Denim Jeans - Washed Blue', 'Relaxed fit men''s baggy jeans with vintage wash and wide leg cut.', 349.00, 26, 'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=1200&q=80', 1),
  ('Oversized Essential Hoodie - Black', 'Heavyweight cotton blend hoodie with drop shoulders and oversized silhouette.', 299.00, 20, 'https://images.unsplash.com/photo-1619603364904-c0498317e145?auto=format&fit=crop&w=1200&q=80', 1),
  ('Straight Cargo Pants - Olive', 'Streetwear cargo pants with multiple utility pockets and straight fit.', 319.00, 18, 'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?auto=format&fit=crop&w=1200&q=80', 1),
  ('Boxy Graphic Tee - Off White', 'Premium cotton tee with boxy fit and front graphic print.', 149.00, 40, 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=1200&q=80', 1),
  ('Relaxed Fit Chino Trousers - Beige', 'Clean everyday chinos with relaxed fit for smart casual outfits.', 259.00, 24, 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?auto=format&fit=crop&w=1200&q=80', 1),
  ('Denim Jacket - Stone Grey', 'Classic men''s denim jacket with modern slightly oversized cut.', 389.00, 14, 'https://images.unsplash.com/photo-1576995853123-5a10305d93c0?auto=format&fit=crop&w=1200&q=80', 1),
  ('Flannel Overshirt - Brown Check', 'Layer-ready flannel overshirt, soft brushed fabric and loose fit.', 279.00, 16, 'https://images.unsplash.com/photo-1617137968427-85924c800a22?auto=format&fit=crop&w=1200&q=80', 1),
  ('Techwear Windbreaker - Matte Black', 'Lightweight water-resistant windbreaker with urban techwear look.', 429.00, 12, 'https://images.unsplash.com/photo-1556906781-9a412961c28c?auto=format&fit=crop&w=1200&q=80', 1),
  ('Loose Fit Sweatpants - Charcoal', 'Soft fleece sweatpants with loose fit and cuffed ankle.', 219.00, 28, 'https://images.unsplash.com/photo-1506629905607-d405b7a16a74?auto=format&fit=crop&w=1200&q=80', 1),
  ('Minimal Leather Sneakers - White', 'Everyday low-top sneakers with clean design and cushioned sole.', 459.00, 15, 'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1200&q=80', 1),
  ('Classic Bomber Jacket - Navy', 'Light padded bomber jacket with ribbed collar and clean street silhouette.', 449.00, 11, 'https://images.unsplash.com/photo-1521223890158-f9f7c3d5d504?auto=format&fit=crop&w=1200&q=80', 1),
  ('Knit Polo Shirt - Sand', 'Soft textured knit polo for elevated casual styling.', 239.00, 19, 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?auto=format&fit=crop&w=1200&q=80', 1);
