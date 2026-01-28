-- 清除所有訂單
TRUNCATE TABLE orders;

-- 清除台北倉測試庫存 (保留大園倉)
DELETE FROM stocks WHERE warehouse_id = 'TAIPEI' AND note LIKE '%測試%';

-- 將台北倉其他可能因測試產生的散數歸零 (如果有)
UPDATE stocks SET unit_count = 0 WHERE warehouse_id = 'TAIPEI';
