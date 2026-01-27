CREATE DATABASE IF NOT EXISTS warehouse;
GRANT ALL PRIVILEGES ON warehouse.* TO 'linebot_wh'@'localhost' IDENTIFIED BY 'warehouse_pass_2026';
FLUSH PRIVILEGES;
