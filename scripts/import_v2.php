<?php
/**
 * Import Data V2.1 - Robust Parser
 */
$config = require __DIR__ . '/config.php';
$dbConfig = $config['db']['mysql'];

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dataDir = __DIR__ . '/unstructured-data';
    $dayuanFile = $dataDir . '/dayuan.txt';
    $taipeiFile = $dataDir . '/taipei.txt';

    echo "Starting Import V2.1...\n";

    if (file_exists($dayuanFile)) {
        processFileV2($pdo, $dayuanFile, 'DAYUAN');
    }
    if (file_exists($taipeiFile)) {
        processFileV2($pdo, $taipeiFile, 'TAIPEI', true);
    }

    echo "Import completed.\n";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

function processFileV2($pdo, $filepath, $warehouseId, $isTaipei = false) {
    $content = file_get_contents($filepath);
    $content = @iconv('CP950', 'UTF-8//IGNORE', $content);
    
    // 使用正則表達式抓取可能的品項
    // 假設格式： 品名 (可能包含空格) 規格/單位 數量 效期
    // 或是 品名：... 數量：...
    
    // 先把所有換行符號統一，並去除多餘空格
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // 根據觀察，資料可能是以「品名」或特定的標記開始。
    // 這裡我們嘗試用「數量」作為錨點，因為數量通常是數字。
    
    // 分割成段落
    $items = explode("\n\n", $content);
    $count = 0;

    foreach ($items as $item) {
        $item = trim($item);
        if (empty($item)) continue;

        // 在段落中尋找資訊
        // 名稱：通常在最前面
        // 單位：通常跟著數字或是規格
        // 數量：純數字
        // 效期：YYYY.MM.DD
        
        $lines = explode("\n", $item);
        $name = "";
        $spec = "件";
        $qty = 0;
        $expiry = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // 抓取日期
            if (preg_match('/\d{4}[\.\-\/]\d{2}[\.\-\/]\d{2}/', $line, $matches)) {
                $expiry = str_replace('.', '-', $matches[0]);
            }
            if (preg_match('/\d{4}[\.\-\/]\d{2}/', $line, $matches) && !$expiry) {
                $expiry = str_replace('.', '-', $matches[0]) . "-01";
            }

            // 抓取數量 (尋找「數量」關鍵字或後面的數字)
            if (preg_match('/(\d+)\s*(件|個|瓶|罐|包|盒|條|支|組|捆|桶)/u', $line, $matches)) {
                $qty = (int)$matches[1];
                $spec = $matches[2];
            } elseif (preg_match('/(\d+)$/', $line, $matches)) {
                 if ($qty == 0) $qty = (int)$matches[1];
            }

            // 名稱 (通常是第一行或是包含特定前綴)
            if (empty($name)) {
                $name = preg_replace('/^(\?|品名|名稱|項次)[:：\s]*/u', '', $line);
                $name = trim(explode(' ', $name)[0]); // 取第一個詞
            }
        }

        if ($qty <= 0 || empty($name)) continue;

        if ($isTaipei) {
            if ($spec === '捆') continue;
            
            $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
            $stmt->execute([$name]);
            $product = $stmt->fetch();
            if (!$product) continue;
            $productId = $product['id'];
        } else {
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

        $stmt = $pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$warehouseId, $productId, $qty, $expiry]);
        $count++;
    }
    echo "  $warehouseId: Processed $count items.\n";
}