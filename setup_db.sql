CREATE DATABASE IF NOT EXISTS warehouse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'linebot_wh'@'localhost' IDENTIFIED BY 'warehouse_pass_2026';
GRANT ALL PRIVILEGES ON warehouse.* TO 'linebot_wh'@'localhost';
FLUSH PRIVILEGES;
