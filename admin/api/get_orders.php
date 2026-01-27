<?php
require_once __DIR__ . '/../../config.php';

$config = require __DIR__ . '/../../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    
    $sql = "
        SELECT 
            o.id, o.order_type, o.status, o.created_at, o.receive_date,
            u.name as requester_name,
            o.items_json
        FROM orders o
        LEFT JOIN users u ON o.requester_id = u.line_user_id
        ORDER BY o.created_at DESC
    ";
    $stmt = $pdo->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 處理 items_json
    foreach ($orders as &$order) {
        $items = json_decode($order['items_json'], true);
        $itemStr = [];
        foreach ($items as $item) {
            // 這裡為了效能暫時不 JOIN products，實際應用可優化
            $itemStr[] = "ID:{$item['product_id']} x {$item['quantity']}";
        }
        $order['items_display'] = implode(", ", $itemStr);
        unset($order['items_json']);
    }

    if (isset($_GET['format']) && $_GET['format'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=orders_export_' . date('Ymd') . '.csv');
        $output = fopen('php://output', 'w');
        
        // BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['訂單編號', '類型', '申請人', '內容', '狀態', '建立日期', '領取日期']);
        foreach ($orders as $row) {
            fputcsv($output, [
                $row['id'],
                $row['order_type'],
                $row['requester_name'],
                $row['items_display'],
                $row['status'],
                $row['created_at'],
                $row['receive_date']
            ]);
        }
        fclose($output);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $orders]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
