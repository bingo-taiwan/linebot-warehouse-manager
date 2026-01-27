<?php
/**
 * API: 處理福利品訂單提交 (僅建立訂單，不預扣庫存)
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
        throw new Exception("訂單內容不可為空");
    }

    // TODO: 這裡未來應從 LIFF 獲取真實 userId，暫用管理員 ID 測試
    $userId = 'U004f8cad542e37c7834a3920e60d1077'; 

    // 0. 檢查台北倉庫存 (散數) 是否足夠
    foreach ($input['items'] as $item) {
        $pid = $item['product_id'];
        $qty = $item['quantity'];

        $checkStmt = $pdo->prepare("SELECT SUM(unit_count) as total FROM stocks WHERE product_id = ? AND warehouse_id = 'TAIPEI'");
        $checkStmt->execute([$pid]);
        $totalStock = $checkStmt->fetchColumn() ?: 0;

        if ($totalStock < $qty) {
            throw new Exception("產品 ID {$pid} 台北倉庫存不足 (需求 {$qty}, 剩餘 {$totalStock})");
        }
    }

    // 1. 記錄訂單 (狀態設為 PENDING)
    $stmt = $pdo->prepare("INSERT INTO orders (order_type, requester_id, items_json, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        'BENEFIT_ORDER',
        $userId,
        json_encode($input['items'], JSON_UNESCAPED_UNICODE),
        'PENDING'
    ]);
    $orderId = $pdo->lastInsertId();

    // 2. 發送確認訊息給員工 (含簽收按鈕)
    $lineBot = new LineBot($config['line']);
    
    $body = FlexBuilder::vbox([
        FlexBuilder::text("📦 福利品選購成功", ['weight' => 'bold', 'size' => 'lg']),
        FlexBuilder::text("訂單編號: #{$orderId}", ['size' => 'sm', 'color' => '#666666']),
        FlexBuilder::separator(['margin' => 'md']),
        FlexBuilder::text("請於收到領貨通知後，點擊下方按鈕確認領取。", ['wrap' => true, 'size' => 'sm']),
        FlexBuilder::button(
            "收到本月福利品",
            ['type' => 'postback', 'data' => "action=confirm_receipt&order_id={$orderId}", 'displayText' => '我已收到本月福利品'],
            'primary'
        )
    ], ['spacing' => 'md']);

    $lineBot->push($userId, [
        ['type' => 'flex', 'altText' => "福利品下單成功 (請於領貨後點擊確認)", 'contents' => FlexBuilder::bubble($body)]
    ]);

    echo json_encode(['success' => true, 'order_id' => $orderId]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}