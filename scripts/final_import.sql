SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE stocks;
TRUNCATE TABLE products;
SET FOREIGN_KEY_CHECKS = 1;
INSERT INTO products (id, name, spec) VALUES (1, '呷足飽裸包', '20g/包');
INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES ('DAYUAN', 1, 750, '2025-12-30');
INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES ('TAIPEI', 1, 138, '2027-09-11');
INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES ('TAIPEI', 1, 36, '2025-07-01');