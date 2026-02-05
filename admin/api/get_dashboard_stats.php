<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';

$config = require __DIR__ . '/../../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    
    // 1. 獲取庫存總覽與預警
    $sql = "
        SELECT 
            p.id, p.name, p.category, p.spec, p.unit_per_case,
            p.alert_threshold_cases, p.alert_threshold_units,
            COALESCE(SUM(CASE WHEN s.warehouse_id = 'DAYUAN' THEN s.case_count ELSE 0 END), 0) as dayuan_stock,
            COALESCE(SUM(CASE WHEN s.warehouse_id = 'DAYUAN' THEN s.unit_count ELSE 0 END), 0) as dayuan_stock_units,
            COALESCE(SUM(CASE WHEN s.warehouse_id = 'TAIPEI' THEN s.unit_count ELSE 0 END), 0) as taipei_stock,
            GROUP_CONCAT(DISTINCT CASE WHEN s.warehouse_id = 'DAYUAN' AND s.expiry_date IS NOT NULL THEN CONCAT(s.expiry_date, ':', s.case_count) END SEPARATOR ', ') as dayuan_expiry,
            GROUP_CONCAT(DISTINCT CASE WHEN s.warehouse_id = 'TAIPEI' AND s.expiry_date IS NOT NULL THEN CONCAT(s.expiry_date, ':', s.unit_count) END SEPARATOR ', ') as taipei_expiry
        FROM products p
        LEFT JOIN stocks s ON p.id = s.product_id AND (s.expiry_date IS NULL OR s.expiry_date > CURDATE())
        GROUP BY p.id
    ";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alerts = [];
    foreach ($products as $p) {
        if ($p['dayuan_stock'] < $p['alert_threshold_cases']) {
            $alerts[] = ['type' => 'DAYUAN', 'product' => $p['name'], 'current' => $p['dayuan_stock'], 'threshold' => $p['alert_threshold_cases']];
        }
        if ($p['taipei_stock'] < $p['alert_threshold_units']) {
            $alerts[] = ['type' => 'TAIPEI', 'product' => $p['name'], 'current' => $p['taipei_stock'], 'threshold' => $p['alert_threshold_units']];
        }
    }

    // 2. 待處理訂單數
    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'PENDING'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'products_count' => count($products),
            'pending_orders' => $pendingOrders,
            'alert_count' => count($alerts)
        ],
        'inventory' => $products,
        'alerts' => $alerts
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
