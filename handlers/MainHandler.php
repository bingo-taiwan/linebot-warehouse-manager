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
                ['type' => 'text', 'text' => "⚠️ 您的身份尚未核准。\n\n請將以下 ID 提供給管理員：\n" . $userId]
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
            // 檢查權限：只有 ADMIN_WAREHOUSE 或 ADMIN_OFFICE 可以看詳細庫存
            if (in_array($user['role'], ['ADMIN_WAREHOUSE', 'ADMIN_OFFICE'])) {
                $wh = $query['wh'] ?? 'DAYUAN';
                $this->replyStockDetail($event['replyToken'], $wh);
            } else {
                $this->lineBot->reply($event['replyToken'], [
                    ['type' => 'text', 'text' => "抱歉，您沒有權限查看明細。"]
                ]);
            }
        }
    }

    private function handleFollow($event) {
        $this->lineBot->reply($event['replyToken'], [
            ['type' => 'text', 'text' => "歡迎使用倉庫管理系統！請待管理員設定您的權限後即可開始使用。"]
        ]);
    }

    private function replyStockSummary($replyToken) {
        // 從資料庫抓取簡易統計
        $stmt = $this->pdo->query("SELECT warehouse_id, COUNT(*) as count, SUM(case_count) as total_cases FROM stocks GROUP BY warehouse_id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bodyContents = [
            FlexBuilder::title("📦 倉庫庫存概況"),
            FlexBuilder::separator()
        ];

        foreach ($rows as $row) {
            $whName = ($row['warehouse_id'] === 'DAYUAN') ? '大園倉' : '台北倉';
            $bodyContents[] = FlexBuilder::hbox([
                FlexBuilder::text($whName, ['weight' => 'bold', 'flex' => 1]),
                FlexBuilder::text($row['count'] . " 品項", ['align' => 'end', 'color' => '#666666']),
                FlexBuilder::text($row['total_cases'] . " 箱", ['align' => 'end', 'weight' => 'bold', 'flex' => 1])
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
        $stmt = $this->pdo->prepare("SELECT p.name, s.case_count, s.expiry_date FROM stocks s JOIN products p ON s.product_id = p.id WHERE s.warehouse_id = ?");
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
                
                $itemBox = FlexBuilder::vbox([
                    FlexBuilder::hbox([
                        FlexBuilder::text($row['name'], ['weight' => 'bold', 'wrap' => true, 'flex' => 3]),
                        FlexBuilder::text($row['case_count'] . " 箱", ['align' => 'end', 'weight' => 'bold', 'flex' => 1])
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
