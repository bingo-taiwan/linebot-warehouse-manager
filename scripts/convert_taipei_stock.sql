-- 將台北倉的庫存從「箱」轉換為「散」
-- 邏輯：unit_count = unit_count + (case_count * unit_per_case)
-- 前提：product_id 必須對應到 products 表以獲取 unit_per_case

UPDATE stocks s
JOIN products p ON s.product_id = p.id
SET 
    s.unit_count = s.unit_count + (s.case_count * p.unit_per_case),
    s.case_count = 0
WHERE s.warehouse_id = 'TAIPEI' AND s.case_count > 0;
