-- 新增安全庫存水位欄位
ALTER TABLE products ADD COLUMN alert_threshold_cases INT DEFAULT 5 COMMENT '大園倉安全庫存(箱)';
ALTER TABLE products ADD COLUMN alert_threshold_units INT DEFAULT 50 COMMENT '台北倉安全庫存(散)';
