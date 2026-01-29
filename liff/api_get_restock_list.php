<?php
/**
 * API: 獲取補貨清單 (同時包含台北現況與大園庫存)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 撈取產品，並分別計算台北散數與大園箱數
    // 優先撈取大園倉有效庫存
    $sql = "
        SELECT 
            p.id, 
            p.name, 
            p.category, 
            p.spec, 
            p.unit_per_case,
            
            -- 台北倉現有散數
            COALESCE((SELECT SUM(unit_count) FROM stocks s1 WHERE s1.product_id = p.id AND s1.warehouse_id = 'TAIPEI'), 0) as taipei_units,
            
            -- 大園倉可調撥箱數 (排除過期)
            -- 若 unit_per_case=1 (如雜項)，則將 unit_count 視為箱數
            COALESCE((
                SELECT SUM(case_count + CASE WHEN p.unit_per_case = 1 THEN unit_count ELSE 0 END) 
                FROM stocks s2 
                WHERE s2.product_id = p.id AND s2.warehouse_id = 'DAYUAN' AND (s2.expiry_date IS NULL OR s2.expiry_date > CURDATE())
            ), 0) as dayuan_cases
            
        FROM products p
        GROUP BY p.id
        HAVING dayuan_cases > 0 -- 只列出大園有貨可補的
        ORDER BY p.category, p.id
    ";

    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $products]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
