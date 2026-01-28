<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';

$config = require __DIR__ . '/../../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    
    $month = $_GET['month'] ?? date('Y-m'); // 預設本月

    $sql = "
        SELECT 
            o.id, o.created_at, o.receive_date, o.status,
            u.name as staff_name,
            o.items_json
        FROM orders o
        LEFT JOIN users u ON o.requester_id = u.line_user_id
        WHERE o.order_type = 'BENEFIT_ORDER' 
          AND DATE_FORMAT(o.created_at, '%Y-%m') = ?
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$month]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 為了計算金額，需撈取產品價格
    // 注意：若產品價格有變動，這裡用的是「目前價格」。若需精確歷史價格，訂單建立時應存入 snapshot。
    $prodStmt = $pdo->query("SELECT id, name, price_member FROM products");
    $products = $prodStmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => name
    $prices = $pdo->query("SELECT id, price_member FROM products")->fetchAll(PDO::FETCH_KEY_PAIR); // id => price

    $logs = [];
    foreach ($orders as $order) {
        $items = json_decode($order['items_json'], true);
        $totalAmount = 0;
        $details = [];

        foreach ($items as $item) {
            $pid = $item['product_id'];
            $qty = $item['quantity'];
            $price = $prices[$pid] ?? 0;
            $name = $products[$pid] ?? "未知產品({$pid})";
            
            $totalAmount += $price * $qty;
            $details[] = "{$name} x {$qty}";
        }

        $logs[] = [
            'id' => $order['id'],
            'date' => date('m-d H:i', strtotime($order['created_at'])),
            'receive_date' => $order['receive_date'] ? date('m-d', strtotime($order['receive_date'])) : '-',
            'staff' => $order['staff_name'] ?? '未知',
            'details' => implode(", ", $details),
            'amount' => $totalAmount,
            'status' => $order['status']
        ];
    }

    echo json_encode(['success' => true, 'data' => $logs]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
