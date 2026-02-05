<?php
/**
 * API: 執行大園 -> 台北調撥 (僅建立訂單，不執行扣庫)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once '/home/lt4.mynet.com.tw/linebot_core/LineBot.php';
require_once '/home/lt4.mynet.com.tw/linebot_core/FlexBuilder.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 取得 JSON 輸入
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['items'])) {
        throw new Exception("無調撥內容");
    }

    // 從輸入取得下單者 ID (應由 LIFF 傳入)
    $requesterId = $input['userId'] ?? 'U004f8cad542e37c7834a3920e60d1077'; 

    $pdo->beginTransaction();

    // 1. 檢查大園庫存是否足夠
    foreach ($input['items'] as $item) {
        $pid = $item['product_id'];
        $qty = $item['quantity']; // 箱數

        $checkStmt = $pdo->prepare("SELECT SUM(case_count) as total FROM stocks WHERE product_id = ? AND warehouse_id = 'DAYUAN'");
        $checkStmt->execute([$pid]);
        $totalStock = $checkStmt->fetchColumn() ?: 0;

        if ($totalStock < $qty) {
            throw new Exception("大園倉產品 ID {$pid} 庫存不足 (需求 {$qty} 箱, 剩餘 {$totalStock} 箱)");
        }
    }

    // 2. 建立訂單 (DAYUAN_ORDER)
    $stmt = $pdo->prepare("INSERT INTO orders (order_type, requester_id, items_json, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        'DAYUAN_ORDER',
        $requesterId,
        json_encode($input['items'], JSON_UNESCAPED_UNICODE),
        'PENDING'
    ]);
    $orderId = $pdo->lastInsertId();

    $pdo->commit();

    // 3. 獲取所有需要接收通知的管理員
    $adminStmt = $pdo->prepare("SELECT line_user_id FROM users WHERE role IN ('ADMIN_WAREHOUSE', 'ADMIN_OFFICE') AND is_active = 1");
    $adminStmt->execute();
    $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($adminIds)) {
        // 如果沒人有權限，至少傳給下單者自己
        $adminIds = [$requesterId];
    }

    // 4. 發送通知給倉管 (大園出貨)
    $lineBot = new LineBot($config['line']);

    $body = FlexBuilder::vbox([
        FlexBuilder::text("🚛 補貨申請單 #{$orderId}", ['weight' => 'bold', 'size' => 'lg', 'color' => '#1565C0']),
        FlexBuilder::separator(['margin' => 'md']),
        FlexBuilder::text("台北倉申請調撥，請大園倉確認庫存並出貨。", ['wrap' => true, 'size' => 'sm']),
        FlexBuilder::button(
            "📦 確認出貨 (扣大園庫存)",
            ['type' => 'postback', 'data' => "action=ship_order&order_id={$orderId}", 'displayText' => "訂單 #{$orderId} 確認出貨"],
            'primary'
        )
    ], ['spacing' => 'md']);

    $pushMessages = [
        ['type' => 'flex', 'altText' => "補貨申請單 #{$orderId}", 'contents' => FlexBuilder::bubble($body)]
    ];

    foreach ($adminIds as $targetId) {
        $lineBot->push($targetId, $pushMessages);
    }

    echo json_encode(['success' => true, 'order_id' => $orderId]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}