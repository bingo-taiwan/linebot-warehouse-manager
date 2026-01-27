-- 為台北倉補入測試庫存
INSERT INTO stocks (warehouse_id, product_id, case_count, expiry_date, note) VALUES 
('TAIPEI', 1, 5, '2027-12-30', '台北倉初始測試'),
('TAIPEI', 2, 3, '2027-09-11', '台北倉初始測試'),
('TAIPEI', 4, 10, '2027-08-17', '台北倉初始測試'),
('TAIPEI', 5, 2, '2028-05-12', '台北倉初始測試');
