-- 新增福利品額度欄位，預設 10000
ALTER TABLE users ADD COLUMN benefit_quota INT DEFAULT 10000 COMMENT '每月福利品額度';

-- 設定 VIP 用戶額度為 20000
UPDATE users SET benefit_quota = 20000 WHERE line_user_id = 'Ud73b84a2f6219421f13c59202121c13f';
