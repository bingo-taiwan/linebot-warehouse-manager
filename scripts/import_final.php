<?php
/**
 * Import Final - Robust UTF-8 Parser for lt4
 */
$config = require __DIR__ . '/config.php';
$dbConfig = $config['db']['mysql'];

try {
    // 強制使用 utf8mb4 連線
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dataDir = __DIR__ . '/unstructured-data';
    $dayuanFile = $dataDir . '/dayuan.txt'; // 實際上內容是 20260119 那個
    $taipeiFile = $dataDir . '/taipei.txt'; // 實際上內容是 2026.01.26 那個

    echo "Starting Final Import...\n";

    // --- 1. 大園倉 (基準) ---
    if (file_exists($dayuanFile)) {
        echo "Processing Da Yuan...\n";
        parseAndImport($pdo, $dayuanFile, 'DAYUAN');
    }

    // --- 2. 台北倉 (過濾) ---
    if (file_exists($taipeiFile)) {
        echo "Processing Taipei...\n";
        parseAndImport($pdo, $taipeiFile, 'TAIPEI', true);
    }

    echo "All tasks finished.\n";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

function parseAndImport($pdo, $filepath, $warehouseId, $isTaipei = false) {
    $content = file_get_contents($filepath);
    // 雖然偵測是 UTF-8，但為了保險移除可能的 BOM
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    // 使用「品名：」作為切分標誌
    $segments = explode('品名：', $content);
    $count = 0;

    foreach ($segments as $seg) {
        if (empty(trim($seg))) continue;
        if (strpos($seg, '倉庫') !== false && strlen($seg) < 50) continue; // 跳過標頭

        // 提取資訊
        // 格式通常是：名稱 [空格/換行] 規格：... 數量：... 到期日：...
        $lines = explode("\n", str_replace("\r", "", $seg));
        $name = trim(preg_split('/\s+/', $lines[0])[0]); // 抓取名稱
        
        $spec = "件";
        $qty = 0;
        $expiry = null;

        if (preg_match('/規格：(.*?)\s/u', $seg, $m)) $spec = trim($m[1]);
        if (preg_match('/數量：(.*?)\s/u', $seg, $m)) {
            $qty_str = $m[1];
            if (preg_match('/(\d+)/', $qty_str, $mq)) $qty = (int)$mq[1];
        }
        if (preg_match('/(?:到期日|效期|生產日)：(.*?)(?:\n|$)/u', $seg, $m)) {
            $date_str = trim($m[1]);
            if (preg_match('/(\d{4})[\.\-\/](\d{2})(?:[\.\-\/](\d{2}))?/', $date_str, $md)) {
                $y = $md[1];
                $m = $md[2];
                $d = isset($md[3]) ? $md[3] : "01";
                $expiry = "$y-$m-$d";
            }
        }

        if (empty($name) || $qty < 0) continue;

        if ($isTaipei) {
            // 排除「捆」
            if (strpos($spec, '捆') !== false) continue;
            // 只匯入與大園倉相符的產品 (模糊匹配前幾個字)
            // 由於沒有 mbstring，我們用簡單的 substr 並假設 UTF-8 中文 3 bytes
            $match_name = substr($name, 0, 9); // 約 3 個中文字
            $stmt = $pdo->prepare("SELECT id FROM products WHERE name LIKE ? LIMIT 1");
            $stmt->execute([$match_name . '%']);
            $product = $stmt->fetch();
            if (!$product) continue;
            $productId = $product['id'];
        } else {
            // 大園倉：建立產品
            $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND spec = ?");
            $stmt->execute([$name, $spec]);
            $product = $stmt->fetch();
            if ($product) {
                $productId = $product['id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (name, spec) VALUES (?, ?)");
                $stmt->execute([$name, $spec]);
                $productId = $pdo->lastInsertId();
            }
        }

        // 插入庫存
        $stmt = $pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$warehouseId, $productId, $qty, $expiry]);
        $count++;
    }
    echo "  $warehouseId: Imported $count items.\n";
}
