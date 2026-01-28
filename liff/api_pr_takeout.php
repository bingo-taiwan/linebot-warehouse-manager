<?php
/**
 * API: 處理公關品自取 (直接扣庫，不佔額度)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['items'])) {
        throw new Exception("取貨內容不可為空");
    }

    $userId = 'Ud73b84a2f6219421f13c59202121c13f'; // VIP 用戶

    $pdo->beginTransaction();

    // 1. 記錄訂單 (類型 PR_TAKEOUT, 狀態 RECEIVED)
    $stmt = $pdo->prepare("INSERT INTO orders (order_type, requester_id, items_json, status, receive_date) VALUES (?, ?, ?, ?, CURDATE())");
    $stmt->execute([
        'PR_TAKEOUT',
        $userId,
        json_encode($input['items'], JSON_UNESCAPED_UNICODE),
        'RECEIVED'
    ]);
    $orderId = $pdo->lastInsertId();

    // 2. 直接扣除台北倉散貨
    foreach ($input['items'] as $item) {
        $pid = $item['product_id'];
        $qty = $item['quantity'];

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
            throw new Exception("台北倉產品 ID {$pid} 庫存不足，無法取貨");
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'order_id' => $orderId]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
