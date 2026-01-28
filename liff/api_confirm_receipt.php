<?php
/**
 * API: 執行訂單簽收 (扣庫存)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['order_id'])) throw new Exception("無訂單 ID");

    $orderId = $input['order_id'];

    $pdo->beginTransaction();

    // 1. 檢查訂單
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'PENDING'");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) throw new Exception("訂單已處理或不存在");

    // 2. 扣庫存 (複製自 MainHandler 邏輯)
    $items = json_decode($order['items_json'], true);
    foreach ($items as $item) {
        $pid = $item['product_id'];
        $qty = $item['quantity'];

        // 優先扣除台北倉效期最接近的
        $stockStmt = $pdo->prepare("SELECT id, unit_count FROM stocks WHERE product_id = ? AND warehouse_id = 'TAIPEI' AND unit_count > 0 ORDER BY expiry_date ASC");
        $stockStmt->execute([$pid]);
        $rows = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

        $remainingToDeduct = $qty;
        foreach ($rows as $stockRow) {
            if ($remainingToDeduct <= 0) break;
            $deduct = min($stockRow['unit_count'], $remainingToDeduct);
            $updateStmt = $pdo->prepare("UPDATE stocks SET unit_count = unit_count - ? WHERE id = ?");
            $updateStmt->execute([$deduct, $stockRow['id']]);
            $remainingToDeduct -= $deduct;
        }

        if ($remainingToDeduct > 0) {
            throw new Exception("台北倉產品(ID:{$pid})庫存不足，無法完成簽收。");
        }
    }

    // 3. 更新狀態
    $updateOrder = $pdo->prepare("UPDATE orders SET status = 'RECEIVED', receive_date = CURDATE() WHERE id = ?");
    $updateOrder->execute([$orderId]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
