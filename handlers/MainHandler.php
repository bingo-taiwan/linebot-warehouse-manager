<?php
/**
 * Main Event Handler
 */

class MainHandler {
    private $lineBot;
    private $config;
    private $pdo;

    public function __construct($lineBot, $config) {
        $this->lineBot = $lineBot;
        $this->config = $config;
        $this->initDB();
        
        // Load FlexBuilder
        require_once '/home/lt4.mynet.com.tw/linebot_core/FlexBuilder.php';
    }

    private function initDB() {
        $db = $this->config['db'];
        $dsn = "mysql:host={$db['mysql']['host']};dbname={$db['mysql']['database']};charset={$db['mysql']['charset']}";
        $this->pdo = new PDO($dsn, $db['mysql']['username'], $db['mysql']['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function handle($event) {
        $userId = $event['source']['userId'] ?? 'unknown';
        $user = $this->getUser($userId);

        if (!$user) {
            $this->lineBot->reply($event['replyToken'], [
                ['type' => 'text', 'text' => "⚠️ 您的身份尚未核准.\n\n請將以下 ID 提供給管理員：\n" . $userId]
            ]);
            return;
        }

        $type = $event['type'];
        if ($type === 'message') {
            $this->handleMessage($event, $user);
        } elseif ($type === 'postback') {
            $this->handlePostback($event, $user);
        } elseif ($type === 'follow') {
            $this->handleFollow($event);
        }
    }

    private function getUser($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE line_user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function handleMessage($event, $user) {
        $text = $event['message']['text'] ?? '';

        if ($text === '庫存' || $text === '查詢') {
            $this->replyStockSummary($event['replyToken']);
        } else {
            $this->lineBot->reply($event['replyToken'], [
                ['type' => 'text', 'text' => "您好 {$user['name']}！目前我能幫您查詢庫存。"]
            ]);
        }
    }

    private function handlePostback($event, $user) {
        parse_str($event['postback']['data'], $query);
        $action = $query['action'] ?? '';

        if ($action === 'view_stock') {
            if (in_array($user['role'], ['ADMIN_WAREHOUSE', 'ADMIN_OFFICE'])) {
                $wh = $query['wh'] ?? 'DAYUAN';
                $this->replyStockDetail($event['replyToken'], $wh);
            } else {
                $this->lineBot->reply($event['replyToken'], [
                    ['type' => 'text', 'text' => "抱歉，您沒有權限查看明細。"]
                ]);
            }
        } elseif ($action === 'confirm_receipt') {
            $this->handleConfirmReceipt($event, $user, $query['order_id']);
        }
    }

    private function handleConfirmReceipt($event, $user, $orderId) {
        try {
            $this->pdo->beginTransaction();

            // 1. 檢查訂單狀態
            $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'PENDING'");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->lineBot->replyText($event['replyToken'], "❌ 該訂單已處理或不存在。");
                $this->pdo->rollBack();
                return;
            }

            $items = json_decode($order['items_json'], true);

            // 2. 根據訂單類型執行不同扣庫邏輯
            if ($order['order_type'] === 'BENEFIT_ORDER') {
                // 福利品：扣除台北倉散貨
                foreach ($items as $item) {
                    $pid = $item['product_id'];
                    $qty = $item['quantity']; // 散數

                    // 優先扣除台北倉效期最接近的
                    $stockStmt = $this->pdo->prepare("SELECT id, unit_count FROM stocks WHERE product_id = ? AND warehouse_id = 'TAIPEI' AND unit_count > 0 ORDER BY expiry_date ASC");
                    $stockStmt->execute([$pid]);
                    $rows = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

                    $remainingToDeduct = $qty;
                    foreach ($rows as $stockRow) {
                        if ($remainingToDeduct <= 0) break;
                        $deduct = min($stockRow['unit_count'], $remainingToDeduct);
                        $updateStmt = $this->pdo->prepare("UPDATE stocks SET unit_count = unit_count - ? WHERE id = ?");
                        $updateStmt->execute([$deduct, $stockRow['id']]);
                        $remainingToDeduct -= $deduct;
                    }

                    if ($remainingToDeduct > 0) {
                        throw new Exception("台北倉產品(ID:{$pid})庫存不足，無法完成簽收。");
                    }
                }
                $successMsg = "✅ 福利品簽收成功！已扣除台北倉庫存。";

            } elseif ($order['order_type'] === 'DAYUAN_ORDER') {
                // 大園補貨：扣除大園箱數 -> 增加台北散數
                foreach ($items as $item) {
                    $pid = $item['product_id'];
                    $qty = $item['quantity']; // 箱數

                    // 1. 取得換算率
                    $prodStmt = $this->pdo->prepare("SELECT unit_per_case FROM products WHERE id = ?");
                    $prodStmt->execute([$pid]);
                    $unitPerCase = $prodStmt->fetchColumn();

                    // 2. 扣除大園庫存 (FIFO)
                    $stockStmt = $this->pdo->prepare("SELECT id, case_count, expiry_date, production_date FROM stocks WHERE product_id = ? AND warehouse_id = 'DAYUAN' AND case_count > 0 ORDER BY expiry_date ASC");
                    $stockStmt->execute([$pid]);
                    $batches = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

                    $remainingToDeduct = $qty;
                    foreach ($batches as $batch) {
                        if ($remainingToDeduct <= 0) break;

                        $deduct = min($batch['case_count'], $remainingToDeduct);
                        
                        // 更新大園庫存
                        $updateSrc = $this->pdo->prepare("UPDATE stocks SET case_count = case_count - ? WHERE id = ?");
                        $updateSrc->execute([$deduct, $batch['id']]);

                        // 3. 增加台北庫存 (散數)
                        // 嘗試尋找台北倉相同效期的批次，若有則合併，無則新增
                        $destStmt = $this->pdo->prepare("SELECT id FROM stocks WHERE product_id = ? AND warehouse_id = 'TAIPEI' AND expiry_date = ?");
                        $destStmt->execute([$pid, $batch['expiry_date']]);
                        $destId = $destStmt->fetchColumn();

                        $unitsToAdd = $deduct * $unitPerCase;

                        if ($destId) {
                            $updateDest = $this->pdo->prepare("UPDATE stocks SET unit_count = unit_count + ? WHERE id = ?");
                            $updateDest->execute([$unitsToAdd, $destId]);
                        } else {
                            $insertDest = $this->pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date, production_date, note) VALUES (?, ?, ?, ?, ?, ?)");
                            $insertDest->execute(['TAIPEI', $pid, $unitsToAdd, $batch['expiry_date'], $batch['production_date'], '大園調撥']);
                        }

                        $remainingToDeduct -= $deduct;
                    }

                    if ($remainingToDeduct > 0) {
                        throw new Exception("大園倉產品(ID:{$pid})庫存不足，無法完成調撥。");
                    }
                }
                $successMsg = "✅ 補貨簽收成功！已從大園倉扣除並入庫至台北倉。";
            }

            // 3. 更新訂單狀態
            $updateOrder = $this->pdo->prepare("UPDATE orders SET status = 'RECEIVED', receive_date = CURDATE() WHERE id = ?");
            $updateOrder->execute([$orderId]);

            $this->pdo->commit();
            $this->lineBot->replyText($event['replyToken'], $successMsg);

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->lineBot->replyText($event['replyToken'], "⚠️ 簽收失敗：" . $e->getMessage());
        }
    }

    private function handleFollow($event) {
        $this->lineBot->reply($event['replyToken'], [
            ['type' => 'text', 'text' => "歡迎使用倉庫管理系統！請待管理員設定您的權限後即可開始使用。"]
        ]);
    }

    private function replyStockSummary($replyToken) {
        // 從資料庫抓取簡易統計 (注意：台北倉現在是 unit_count)
        $stmt = $this->pdo->query("SELECT warehouse_id, COUNT(*) as count, SUM(case_count) as total_cases, SUM(unit_count) as total_units FROM stocks GROUP BY warehouse_id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bodyContents = [
            FlexBuilder::title("📦 倉庫庫存概況"),
            FlexBuilder::separator()
        ];

        foreach ($rows as $row) {
            $whName = ($row['warehouse_id'] === 'DAYUAN') ? '大園倉' : '台北倉';
            $qtyDisplay = ($row['warehouse_id'] === 'DAYUAN') 
                ? $row['total_cases'] . " 箱" 
                : $row['total_units'] . " 散";

            $bodyContents[] = FlexBuilder::hbox([
                FlexBuilder::text($whName, ['weight' => 'bold', 'flex' => 1]),
                FlexBuilder::text($row['count'] . " 品項", ['align' => 'end', 'color' => '#666666']),
                FlexBuilder::text($qtyDisplay, ['align' => 'end', 'weight' => 'bold', 'flex' => 1])
            ], ['margin' => 'md']);
            
            $bodyContents[] = FlexBuilder::button(
                "查看明細",
                FlexBuilder::postbackAction("查看{$whName}明細", "action=view_stock&wh=" . $row['warehouse_id']),
                'secondary'
            );
        }

        $bubble = FlexBuilder::bubble(FlexBuilder::vbox($bodyContents, ['spacing' => 'sm']));
        $this->lineBot->replyFlex($replyToken, "庫存概況", $bubble);
    }

    private function replyStockDetail($replyToken, $warehouseId) {
        $stmt = $this->pdo->prepare("SELECT p.name, s.case_count, s.unit_count, s.expiry_date, p.spec FROM stocks s JOIN products p ON s.product_id = p.id WHERE s.warehouse_id = ?");
        $stmt->execute([$warehouseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $whName = ($warehouseId === 'DAYUAN') ? '大園倉' : '台北倉';
        
        $bodyContents = [
            FlexBuilder::title("【{$whName}】明細"),
            FlexBuilder::separator()
        ];

        if (empty($rows)) {
            $bodyContents[] = FlexBuilder::text("目前無任何庫存資料。", ['margin' => 'md', 'color' => '#999999']);
        } else {
            foreach ($rows as $row) {
                $isExpired = (strtotime($row['expiry_date']) < time());
                $expiryColor = $isExpired ? '#FF0000' : '#666666';
                
                // 根據倉庫顯示不同單位
                if ($warehouseId === 'DAYUAN') {
                    $qtyText = $row['case_count'] . " 箱";
                } else {
                    $unit = (strpos($row['spec'], '包') !== false) ? '包' : '盒';
                    $qtyText = $row['unit_count'] . " " . $unit;
                }

                $itemBox = FlexBuilder::vbox([
                    FlexBuilder::hbox([
                        FlexBuilder::text($row['name'], ['weight' => 'bold', 'wrap' => true, 'flex' => 3]),
                        FlexBuilder::text($qtyText, ['align' => 'end', 'weight' => 'bold', 'flex' => 2])
                    ]),
                    FlexBuilder::text("效期: " . ($row['expiry_date'] ?? '無'), ['size' => 'xs', 'color' => $expiryColor])
                ], ['margin' => 'md']);
                
                $bodyContents[] = $itemBox;
            }
        }

        $bubble = FlexBuilder::bubble(FlexBuilder::vbox($bodyContents, ['spacing' => 'md']));
        $this->lineBot->replyFlex($replyToken, "{$whName}庫存明細", $bubble);
    }
}