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
    }

    private function initDB() {
        $db = $this->config['db'];
        
        if ($db['driver'] === 'sqlite') {
            $dsn = "sqlite:" . $db['sqlite']['path'];
            $this->pdo = new PDO($dsn);
        } else {
            // MySQL
            $dsn = "mysql:host={$db['mysql']['host']};dbname={$db['mysql']['database']};charset={$db['mysql']['charset']}";
            $this->pdo = new PDO($dsn, $db['mysql']['username'], $db['mysql']['password']);
        }
        
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function handle($event) {
        $type = $event['type'];
        
        if ($type === 'message') {
            $this->handleMessage($event);
        } elseif ($type === 'postback') {
            $this->handlePostback($event);
        } elseif ($type === 'follow') {
            $this->handleFollow($event);
        }
    }

    private function handleMessage($event) {
        $userId = $event['source']['userId'];
        $text = $event['message']['text'] ?? '';

        if ($text === '庫存' || $text === '查詢') {
            $this->replyStockSummary($event['replyToken']);
        } else {
            // 預設回應
            $this->lineBot->reply($event['replyToken'], [
                ['type' => 'text', 'text' => "您好！目前我能幫您查詢庫存。請輸入「庫存」或點選選單。"]
            ]);
        }
    }

    private function handlePostback($event) {
        parse_str($event['postback']['data'], $query);
        $action = $query['action'] ?? '';

        if ($action === 'view_stock') {
            $wh = $query['wh'] ?? 'DAYUAN';
            $this->replyStockDetail($event['replyToken'], $wh);
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

        $msg = "📦 當前庫存概況：\n";
        foreach ($rows as $row) {
            $whName = ($row['warehouse_id'] === 'DAYUAN') ? '大園倉' : '台北倉';
            $msg .= "- {$whName}: {$row['count']} 品項, 共 {$row['total_cases']} 箱\n";
        }

        $this->lineBot->reply($replyToken, [['type' => 'text', 'text' => $msg]]);
    }

    private function replyStockDetail($replyToken, $warehouseId) {
        // 這裡未來可以改用 Flex Message
        $stmt = $this->pdo->prepare("SELECT p.name, s.case_count, s.expiry_date FROM stocks s JOIN products p ON s.product_id = p.id WHERE s.warehouse_id = ?");
        $stmt->execute([$warehouseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $whName = ($warehouseId === 'DAYUAN') ? '大園倉' : '台北倉';
        $msg = "【{$whName} 明細】\n";
        foreach ($rows as $row) {
            $msg .= "• {$row['name']}: {$row['case_count']}箱 (效期: {$row['expiry_date']})\n";
        }

        $this->lineBot->reply($replyToken, [['type' => 'text', 'text' => $msg]]);
    }
}

