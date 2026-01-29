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

        if (strpos($text, '大園') !== false) {
            $this->replyStockSummary($event['replyToken'], '產品', 'DAYUAN');
        } elseif (strpos($text, '台北') !== false) {
            $this->replyStockSummary($event['replyToken'], '產品', 'TAIPEI');
        } elseif ($text === '庫存' || $text === '查詢' || strpos($text, '庫存') !== false) {
            $this->replyStockSummary($event['replyToken'], '產品');
        } else {
            $this->lineBot->reply($event['replyToken'], [
                ['type' => 'text', 'text' => "您好 {$user['name']}！目前我能幫您查詢庫存。"]
            ]);
        }
    }

    private function handlePostback($event, $user) {
        parse_str($event['postback']['data'], $query);
        $action = $query['action'] ?? '';
        $wh = $query['wh'] ?? null;

        if ($action === 'switch_category') {
            $category = $query['category'] ?? '產品';
            $this->replyStockSummary($event['replyToken'], $category, $wh);
        } elseif ($action === 'view_stock') {
            $whParam = $query['wh'] ?? null;
            $this->replyStockSummary($event['replyToken'], '產品', $whParam);
        } elseif ($action === 'confirm_receipt') {
            $this->handleConfirmReceipt($event, $user, $query['order_id']);
        }
    }

    // ... handleConfirmReceipt ... (keep as is)

    private function handleFollow($event) {
        $this->lineBot->reply($event['replyToken'], [
            ['type' => 'text', 'text' => "歡迎使用倉庫管理系統！請待管理員設定您的權限後即可開始使用。"]
        ]);
    }

    private function getUnit($name, $spec) {
        if (strpos($name, '盒') !== false) return '盒';
        if (strpos($name, '包') !== false) return '包';
        if (strpos($name, '瓶') !== false) return '瓶';
        if (strpos($spec, '包') !== false) return '包';
        if (strpos($spec, '盒') !== false) return '盒';
        return '單位';
    }

    private function replyStockSummary($replyToken, $category, $warehouseId = null) {
        // 定義分類與倉庫清單
        $categories = ['產品', '包材', '雜項'];
        $warehouseNames = ['DAYUAN' => '大園倉', 'TAIPEI' => '台北倉'];
        
        $titlePrefix = $warehouseId ? $warehouseNames[$warehouseId] : "全倉";

        // 查詢庫存
        $sql = "SELECT s.warehouse_id, p.name, p.spec, p.unit_per_case, s.case_count, s.unit_count, s.expiry_date 
                FROM stocks s 
                JOIN products p ON s.product_id = p.id 
                WHERE p.category = ? ";
        
        $params = [$category];
        if ($warehouseId) {
            $sql .= " AND s.warehouse_id = ? ";
            $params[] = $warehouseId;
        }
        $sql .= " ORDER BY s.warehouse_id, p.id, s.expiry_date"; // 增加依效期排序
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 資料分組: Warehouse -> Product Name -> Batches
        $groupedData = [];
        foreach ($rows as $row) {
            $wh = $row['warehouse_id'];
            $name = $row['name'];
            if (!isset($groupedData[$wh])) {
                $groupedData[$wh] = [];
            }
            if (!isset($groupedData[$wh][$name])) {
                $groupedData[$wh][$name] = [
                    'spec' => $row['spec'],
                    'unit_per_case' => $row['unit_per_case'],
                    'total_case' => 0,
                    'total_unit' => 0,
                    'batches' => []
                ];
            }
            // 累加總數
            $groupedData[$wh][$name]['total_case'] += $row['case_count'];
            $groupedData[$wh][$name]['total_unit'] += $row['unit_count'];
            // 加入批次
            $groupedData[$wh][$name]['batches'][] = [
                'case' => $row['case_count'],
                'unit' => $row['unit_count'],
                'expiry' => $row['expiry_date']
            ];
        }

        // 建構 Flex Message
        $bodyContents = [
            FlexBuilder::title("📦 {$titlePrefix} - {$category}"),
            FlexBuilder::separator('md'),
            
            // 分類切換按鈕
            FlexBuilder::text("切換分類:", ['size' => 'xs', 'color' => '#aaaaaa', 'margin' => 'md']),
            FlexBuilder::box('horizontal', array_map(function($cat) use ($category, $warehouseId) {
                $style = ($cat === $category) ? 'primary' : 'secondary';
                $whParam = $warehouseId ? "&wh={$warehouseId}" : "";
                return FlexBuilder::button($cat, 
                    FlexBuilder::postbackAction($cat, "action=switch_category&category={$cat}{$whParam}"), 
                    $style, 
                    ['height' => 'sm', 'flex' => 1, 'margin' => 'xs']
                );
            }, $categories), ['margin' => 'sm']),
            
            FlexBuilder::separator('lg'),
        ];

        // 顯示各倉庫存
        $displayWhs = $warehouseId ? [$warehouseId => $warehouseNames[$warehouseId]] : $warehouseNames;

        foreach ($displayWhs as $whId => $whName) {
            // 只有當顯示"全倉"時，才顯示倉庫標題，避免重複
            if (!$warehouseId) {
                $bodyContents[] = FlexBuilder::text($whName, ['weight' => 'bold', 'size' => 'lg', 'margin' => 'lg', 'color' => '#1DB446']);
            }
            
            if (empty($groupedData[$whId])) {
                $bodyContents[] = FlexBuilder::text("無資料", ['size' => 'sm', 'color' => '#aaaaaa', 'margin' => 'sm', 'align' => 'center']);
            } else {
                foreach ($groupedData[$whId] as $prodName => $prodData) {
                    // 1. 總數顯示
                    if ($whId === 'DAYUAN') {
                        $totalText = $prodData['total_case'] . " 箱";
                    } else {
                        $unit = $this->getUnit($prodName, $prodData['spec']);
                        $totalText = $prodData['total_unit'] . " " . $unit;
                    }

                    $itemBoxContents = [
                        FlexBuilder::box('horizontal', [
                            FlexBuilder::text($prodName, ['weight' => 'bold', 'flex' => 7, 'wrap' => true]),
                            FlexBuilder::text($totalText, ['weight' => 'bold', 'flex' => 3, 'align' => 'end', 'color' => '#1DB446'])
                        ])
                    ];

                    // 1.5 顯示規格
                    if ($prodData['spec']) {
                        $unit = $this->getUnit($prodName, $prodData['spec']);
                        $specText = "規格: {$prodData['spec']}";
                        if ($prodData['unit_per_case'] > 1) {
                            $specText .= " ({$prodData['unit_per_case']}{$unit}/箱)";
                        }
                        $itemBoxContents[] = FlexBuilder::text($specText, ['size' => 'xxs', 'color' => '#aaaaaa', 'wrap' => true]);
                    }

                    // 2. 批次詳情 (如果有做效期控管)
                    // 如果只有一個批次且無效期，就不顯示詳情
                    $showDetails = false;
                    foreach ($prodData['batches'] as $batch) {
                        if ($batch['expiry']) {
                            $showDetails = true; 
                            break;
                        }
                    }
                    // 如果有多個批次，即使無效期也顯示(因為可能是不同入庫時間，雖然這裡只有效期)
                    if (count($prodData['batches']) > 1) {
                        $showDetails = true;
                    }

                    if ($showDetails) {
                        $batchRows = [];
                        foreach ($prodData['batches'] as $batch) {
                            // 數量文字
                            if ($whId === 'DAYUAN') {
                                $qty = $batch['case'] . "箱";
                            } else {
                                $qty = $batch['unit']; // 單位上面已顯示
                            }

                            // 效期文字
                            $isExpired = $batch['expiry'] && (strtotime($batch['expiry']) < time());
                            $expiryText = $batch['expiry'] ? $batch['expiry'] : "無效期";
                            $expiryColor = $isExpired ? '#FF0000' : '#aaaaaa';
                            $expiryWeight = $isExpired ? 'bold' : 'regular';
                            
                            $rowContent = [
                                FlexBuilder::text($expiryText, ['size' => 'xs', 'color' => $expiryColor, 'weight' => $expiryWeight, 'flex' => 0]),
                                FlexBuilder::text(" ($qty)", ['size' => 'xs', 'color' => '#666666', 'flex' => 0])
                            ];

                            if ($isExpired) {
                                $rowContent[] = FlexBuilder::text(" ⚠️", ['size' => 'xs', 'flex' => 0]);
                            }

                            // 這裡使用 baseline 排版讓文字對齊
                            $batchRows[] = FlexBuilder::box('horizontal', $rowContent, ['margin' => 'xs', 'spacing' => 'sm']);
                        }
                        // 將批次列表加入 itemBox
                        // 使用 separator 分隔總數與批次
                        $itemBoxContents[] = FlexBuilder::separator('sm');
                        $itemBoxContents[] = FlexBuilder::box('vertical', $batchRows, ['margin' => 'sm']);
                    }

                    $bodyContents[] = FlexBuilder::box('vertical', $itemBoxContents, ['margin' => 'md', 'backgroundColor' => '#f9f9f9', 'cornerRadius' => 'md', 'paddingAll' => 'sm']);
                }
            }
        }

        $bubble = FlexBuilder::bubble(FlexBuilder::box('vertical', $bodyContents));
        $this->lineBot->replyFlex($replyToken, "{$titlePrefix}庫存-{$category}", $bubble);
    }
}