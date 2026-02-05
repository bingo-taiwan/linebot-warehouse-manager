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

        // ... (existing RBAC check) ...

        $type = $event['type'];
        if ($type === 'message') {
            $messageType = $event['message']['type'];
            if ($messageType === 'image') {
                $this->handleImageMessage($event);
            } elseif ($messageType === 'file') {
                // 檢查是否為圖片檔案 (如 HEIC)
                $fileName = $event['message']['fileName'] ?? '';
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (in_array($ext, ['heic', 'heif', 'jpg', 'jpeg', 'png', 'webp'])) {
                    $this->handleImageMessage($event);
                } else {
                    $this->handleMessage($event, $user);
                }
            } else {
                $this->handleMessage($event, $user);
            }
        } elseif ($type === 'postback') {
            $this->handlePostback($event, $user);
        } elseif ($type === 'follow') {
            $this->handleFollow($event);
        }
    }

    private function getUser($lineUserId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
        $stmt->execute([$lineUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // 返回預設用戶資料（未註冊用戶）
            return [
                'line_user_id' => $lineUserId,
                'name' => '訪客',
                'role' => 'guest',
                'is_active' => 0
            ];
        }

        return $user;
    }

    private function handleMessage($event, $user) {
        $text = trim($event['message']['text'] ?? '');
        $userId = $user['line_user_id'];
        
        // 檢查是否有暫存圖片 Session
        $sessionFile = __DIR__ . '/../data/session_' . $userId . '.json';
        if (file_exists($sessionFile)) {
            $session = json_decode(file_get_contents($sessionFile), true);
            
            // 處理 "A 產品名" 指令
            if (isset($session['image_path']) && strtoupper(substr($text, 0, 1)) === 'A') {
                $productName = trim(substr($text, 1));
                $this->processProductImage($event, $session['image_path'], $productName);
                unlink($sessionFile); // 清除 Session
                return;
            }
        }

        if (in_array(strtolower($text), ['menu', '選單', 'help', '?', '幫助'])) {
            $this->replyMenu($event['replyToken'], $user);
        } elseif ($text === '庫存' || $text === '查詢') {
            $this->replyStockSummary($event['replyToken'], $user);
        } else {
            // 預設回覆選單
            $this->replyMenu($event['replyToken'], $user);
        }
    }

    private function replyMenu($replyToken, $user) {
        $role = $user['role'];
        $name = $user['name'];
        $roleName = '訪客';
        $buttons = [];

        // 定義連結
        $liffBase = "https://lt4.mynet.com.tw/linebot/warehouse/liff";
        $adminBase = "https://lt4.mynet.com.tw/linebot/warehouse/admin";

        if ($role === 'ADMIN_WAREHOUSE') {
            $roleName = '倉管人員';
            $buttons[] = FlexBuilder::button('📊 庫存查詢', FlexBuilder::postbackAction('庫存查詢', 'action=view_stock'), 'primary');
            $buttons[] = FlexBuilder::button('🚚 大園補貨 (調撥)', FlexBuilder::uriAction('大園補貨', "$liffBase/restock_dayuan.php"), 'secondary');
            $buttons[] = FlexBuilder::button('🏭 台北入庫 (新品)', FlexBuilder::uriAction('台北入庫', "$liffBase/add_stock.php"), 'secondary');
            $buttons[] = FlexBuilder::button('📦 訂單/後台管理', FlexBuilder::uriAction('後台管理', "$adminBase/"), 'secondary');
        } elseif ($role === 'ADMIN_OFFICE') {
            $roleName = '行政人員';
            $buttons[] = FlexBuilder::button('📊 台北庫存查詢', FlexBuilder::postbackAction('台北庫存', 'action=view_stock&wh=TAIPEI'), 'primary');
            $buttons[] = FlexBuilder::button('🚚 申請調撥', FlexBuilder::uriAction('申請調撥', "$liffBase/restock_dayuan.php"), 'secondary');
            $buttons[] = FlexBuilder::button('🎁 福利品下單', FlexBuilder::uriAction('福利品下單', "$liffBase/benefit_cart.php"), 'secondary');
            $buttons[] = FlexBuilder::button('👥 用戶權限管理', FlexBuilder::uriAction('用戶權限', "$adminBase/users.php?bot=warehouse"), 'secondary');
        } elseif ($role === 'SALES_LECTURER') {
            $roleName = '業務講師';
            $buttons[] = FlexBuilder::button('🎁 福利品專區', FlexBuilder::uriAction('福利品專區', "$liffBase/benefit_cart.php"), 'primary');
            // $buttons[] = FlexBuilder::button('🛒 我的訂單', FlexBuilder::uriAction('我的訂單', "$liffBase/my_orders.php"), 'secondary');
        } else {
            $buttons[] = FlexBuilder::text("您目前為訪客身份，請聯繫管理員開通權限。", ['align' => 'center', 'color' => '#666666']);
        }

        $body = FlexBuilder::vbox(array_merge([
            FlexBuilder::text("👤 {$name} ({$roleName})", ['weight' => 'bold', 'size' => 'md', 'color' => '#1DB446']),
            FlexBuilder::separator('md'),
            FlexBuilder::text("請選擇功能：", ['size' => 'xs', 'color' => '#aaaaaa', 'margin' => 'md'])
        ], $buttons), ['spacing' => 'md']);

        $bubble = FlexBuilder::bubble($body);
        $this->lineBot->replyFlex($replyToken, "功能選單", $bubble);
    }

    private function handleImageMessage($event) {
        $messageId = $event['message']['id'];
        $url = "https://api-data.line.me/v2/bot/message/{$messageId}/content";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->config['line']['access_token']
        ]);
        $imageData = curl_exec($ch);
        curl_close($ch);

        if ($imageData) {
            // 存到暫存區
            $filename = time() . "_{$messageId}.jpg";
            $savePath = __DIR__ . '/../data/temp_' . $filename;
            file_put_contents($savePath, $imageData);
            
            // 寫入 Session
            $sessionData = ['image_path' => $savePath, 'timestamp' => time()];
            file_put_contents(__DIR__ . '/../data/session_' . $event['source']['userId'] . '.json', json_encode($sessionData));
            
            $debugMsg = "📸 圖片已接收！\n";
            $debugMsg .= "ID: {$messageId}\n";
            $debugMsg .= "File: {$filename}\n\n";
            $debugMsg .= "請輸入指令指定用途：\n『A 產品名稱』(更新產品圖片)";
            
            $this->lineBot->replyText($event['replyToken'], $debugMsg);
        } else {
            $this->lineBot->replyText($event['replyToken'], "⚠️ 圖片下載失敗 (ID: {$messageId})");
        }
    }

    private function processProductImage($event, $tempPath, $productName) {
        // 1. 檢查產品是否存在
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE name = ?");
        $stmt->execute([$productName]);
        $pid = $stmt->fetchColumn();

        if (!$pid) {
            $this->lineBot->replyText($event['replyToken'], "❌ 找不到產品：{$productName}\n請確認名稱是否正確。");
            return;
        }

        // 2. 呼叫 Python 處理圖片
        $outputDir = __DIR__ . '/../liff/images'; // 假設圖片放在這裡
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        
        $baseName = "prod_{$pid}_" . time();
        $script = __DIR__ . '/../scripts/process_image.py';
        
        $cmd = "python3 " . escapeshellarg($script) . " " . escapeshellarg($tempPath) . " " . escapeshellarg($outputDir) . " " . escapeshellarg($baseName);
        $output = shell_exec($cmd);
        
        if (strpos($output, 'SUCCESS') !== false) {
            list($status, $main, $thumb) = explode('|', trim($output));
            
            // 3. 更新資料庫
            $publicUrl = "https://lt4.mynet.com.tw/linebot/warehouse/liff/images/{$main}";
            $update = $this->pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?");
            $update->execute([$publicUrl, $pid]);
            
            // 刪除暫存檔
            @unlink($tempPath);
            
            $this->lineBot->replyText($event['replyToken'], "✅ 更新成功！\n{$productName} 的圖片已更新。\n\n網址：{$publicUrl}");
        } else {
            $this->lineBot->replyText($event['replyToken'], "⚠️ 圖片處理失敗：\n" . $output);
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
        } elseif ($action === 'ship_order') {
            $this->handleShipOrder($event, $user, $query['order_id']);
        } elseif ($action === 'confirm_receipt') {
            $this->handleConfirmReceipt($event, $user, $query['order_id']);
        }
    }

    private function handleShipOrder($event, $user, $orderId) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) throw new Exception("訂單不存在");
            if ($order['status'] !== 'PENDING') {
                $this->lineBot->replyText($event['replyToken'], "此訂單狀態非待處理 ({$order['status']})，無法出貨。");
                $this->pdo->rollBack();
                return;
            }

            $items = json_decode($order['items_json'], true);

            // 1. 扣除大園庫存
            $items = $this->deductDayuanStock($items);
            
            // 更新 items_json (包含使用的批次資訊)
            $json = json_encode($items, JSON_UNESCAPED_UNICODE);
            $u = $this->pdo->prepare("UPDATE orders SET items_json = ?, status = 'SHIPPED', ship_date = CURDATE() WHERE id = ?");
            $u->execute([$json, $orderId]);

            $this->pdo->commit();

            // 2. 回覆操作者
            $this->lineBot->replyText($event['replyToken'], "✅ 訂單 #{$orderId} 已出貨！\n大園庫存已扣除。正在通知台北倉簽收...");

            // 3. 通知台北倉 (ADMIN_OFFICE & ADMIN_WAREHOUSE)
            // 這裡發送給所有相關管理員
            $adminStmt = $this->pdo->prepare("SELECT line_user_id FROM users WHERE role IN ('ADMIN_WAREHOUSE', 'ADMIN_OFFICE') AND is_active = 1");
            $adminStmt->execute();
            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

            $body = FlexBuilder::vbox([
                FlexBuilder::text("🚚 貨物運送中 #{$orderId}", ['weight' => 'bold', 'size' => 'lg', 'color' => '#F57C00']),
                FlexBuilder::separator('md'),
                FlexBuilder::text("大園倉已出貨，收到貨物後請點擊簽收。", ['wrap' => true, 'size' => 'sm']),
                FlexBuilder::button(
                    "📥 確認簽收 (入台北庫存)",
                    ['type' => 'postback', 'data' => "action=confirm_receipt&order_id={$orderId}", 'displayText' => "訂單 #{$orderId} 確認簽收"],
                    'primary'
                )
            ], ['spacing' => 'md']);

            $pushMessages = [['type' => 'flex', 'altText' => "貨物運送通知 #{$orderId}", 'contents' => FlexBuilder::bubble($body)]];
            
            foreach ($adminIds as $targetId) {
                $this->lineBot->push($targetId, $pushMessages);
            }

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->lineBot->replyText($event['replyToken'], "❌ 出貨失敗：\n" . $e->getMessage());
        }
    }

    private function handleConfirmReceipt($event, $user, $orderId) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) throw new Exception("訂單不存在");
            if ($order['status'] === 'RECEIVED') {
                $this->lineBot->replyText($event['replyToken'], "此訂單已簽收過。");
                $this->pdo->rollBack();
                return;
            }
            if ($order['status'] !== 'SHIPPED') {
                $this->lineBot->replyText($event['replyToken'], "訂單尚未出貨，無法簽收 (狀態: {$order['status']})。");
                $this->pdo->rollBack();
                return;
            }

            $items = json_decode($order['items_json'], true);

            // 1. 增加台北庫存 (Dayuan already deducted in ship step)
            $this->addTaipeiStock($items);

            // 2. Update Status
            $upd = $this->pdo->prepare("UPDATE orders SET status = 'RECEIVED', receive_date = CURDATE(), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$orderId]);

            $this->pdo->commit();

            $this->lineBot->replyText($event['replyToken'], "✅ 訂單 #{$orderId} 已確認簽收！\n庫存已加入台北倉。");

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->lineBot->replyText($event['replyToken'], "❌ 簽收失敗：\n" . $e->getMessage());
        }
    }

    private function deductDayuanStock($items) {
        foreach ($items as &$item) {
            $pid = $item['product_id'];
            $qtyCases = $item['quantity'];
            $item['batches_used'] = [];

            $stmt = $this->pdo->prepare("SELECT id, case_count, expiry_date FROM stocks WHERE product_id = ? AND warehouse_id = 'DAYUAN' AND case_count > 0 ORDER BY expiry_date ASC");
            $stmt->execute([$pid]);
            $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $remaining = $qtyCases;
            foreach ($stocks as $stock) {
                if ($remaining <= 0) break;
                $deduct = min($stock['case_count'], $remaining);
                
                $upd = $this->pdo->prepare("UPDATE stocks SET case_count = case_count - ? WHERE id = ?");
                $upd->execute([$deduct, $stock['id']]);

                $item['batches_used'][] = ['expiry' => $stock['expiry_date'], 'cases' => $deduct];
                $remaining -= $deduct;
            }

            if ($remaining > 0) throw new Exception("大園倉庫存不足 (ID: $pid)");
        }
        return $items;
    }

    private function addTaipeiStock($items) {
        foreach ($items as $item) {
            $pid = $item['product_id'];
            $pStmt = $this->pdo->prepare("SELECT unit_per_case FROM products WHERE id = ?");
            $pStmt->execute([$pid]);
            $unitPerCase = $pStmt->fetchColumn() ?: 1;

            $batches = $item['batches_used'] ?? [];
            if (empty($batches)) {
                // Fallback: No batch info found (Legacy or direct update)
                // Assume 1 large batch with NO expiry
                $qtyCases = $item['quantity'];
                $totalUnits = $qtyCases * $unitPerCase;
                $this->addStockEntry('TAIPEI', $pid, $totalUnits, null);
            } else {
                foreach ($batches as $batch) {
                    $units = $batch['cases'] * $unitPerCase;
                    $this->addStockEntry('TAIPEI', $pid, $units, $batch['expiry']);
                }
            }
        }
    }

    private function addStockEntry($warehouse, $pid, $units, $expiry) {
        $sql = "SELECT id FROM stocks WHERE product_id = ? AND warehouse_id = ? AND ";
        $params = [$pid, $warehouse];
        if ($expiry === null) {
            $sql .= "expiry_date IS NULL";
        } else {
            $sql .= "expiry_date = ?";
            $params[] = $expiry;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stockId = $stmt->fetchColumn();

        if ($stockId) {
            $upd = $this->pdo->prepare("UPDATE stocks SET unit_count = unit_count + ? WHERE id = ?");
            $upd->execute([$units, $stockId]);
        } else {
            $ins = $this->pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date, case_count) VALUES (?, ?, ?, ?, 0)");
            $ins->execute([$warehouse, $pid, $units, $expiry]);
        }
    }

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