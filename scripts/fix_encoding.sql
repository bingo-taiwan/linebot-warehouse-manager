-- 修正類別名稱亂碼
UPDATE products SET category = '產品' WHERE id IN (1, 2, 3, 4, 5, 6, 8);
UPDATE products SET category = '包材' WHERE id = 7;

-- 順便修正可能變成亂碼的品名 (預防萬一)
UPDATE products SET name = '貓頭鷹外盒' WHERE id = 7;
