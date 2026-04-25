-- Run once if you already created the DB with user_id NOT NULL on orders.
-- mysql -u root -p ensaj_shop < database_migration_orders_guest.sql

USE ensaj_shop;

ALTER TABLE orders DROP FOREIGN KEY fk_orders_user;

ALTER TABLE orders
  MODIFY user_id INT UNSIGNED NULL;

ALTER TABLE orders
  ADD CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE;
