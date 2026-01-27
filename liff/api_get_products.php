<?php
/**
 * API: 獲取可選購商品清單
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 查詢產品及其在庫存表中的總量 (只計算台北倉的散貨)
    $sql = "SELECT 
                p.id, 
                p.name, 
                p.category, 
                p.spec, 
                p.price_member,
                p.image_url,
                SUM(s.unit_count) as total_units
            FROM products p
            LEFT JOIN stocks s ON p.id = s.product_id AND s.warehouse_id = 'TAIPEI'
            WHERE (s.expiry_date IS NULL OR s.expiry_date > CURDATE())
            GROUP BY p.id
            HAVING total_units > 0";

    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'data' => $products,
        'quota_limit' => $config['ui']['employee_benefit_quota'] // 傳回 10000 額度限制
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
