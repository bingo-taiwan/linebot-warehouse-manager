<?php
/**
 * 台北倉資料匯入腳本 (PHP 版) - 穩定版
 */
require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $file = __DIR__ . '/../unstructured-data/台北倉庫20260126盤點.txt';
    if (!file_exists($file)) die("File not found: $file\n");

    $content = file_get_contents($file);
    $items = explode('品名：', $content);
    array_shift($items); 

    $imported = 0;
    foreach ($items as $itemStr) {
        $lines = explode("\n", trim($itemStr));
        $name = trim($lines[0]);
        // Remove whitespace
        $name = preg_replace('/\s+/u', '', $name);
        // Remove potential checkmark or other special chars (generic approach)
        $name = preg_replace('/[^\x{4e00}-\x{9fa5}A-Za-z0-9\(\)（）\+]+/u', '', $name);
        
        $spec = "";
        $qtyStr = "";
        $expiry = null;

        foreach ($lines as $line) {
            if (strpos($line, '規格：') !== false) $spec = trim(str_replace('規格：', '', $line));
            if (strpos($line, '數量：') !== false) $qtyStr = trim(str_replace('數量：', '', $line));
            if (strpos($line, '效期：') !== false || strpos($line, '到期日：') !== false) {
                if (preg_match('/\d{4}[\.\-/]\d{2}[\.\-/]?\d{0,2}/', $line, $matches)) {
                    $expiry = str_replace('.', '-', $matches[0]);
                    if (strlen($expiry) == 7) $expiry .= "-01";
                }
            }
        }

        preg_match('/(\d+\.?\d*)/', $qtyStr, $qMatches);
        $qty = isset($qMatches[1]) ? floatval($qMatches[1]) : 0;
        
        $unit = "個";
        if (mb_strpos($qtyStr, '盒') !== false) $unit = "盒";
        elseif (mb_strpos($qtyStr, '包') !== false) $unit = "包";
        elseif (mb_strpos($qtyStr, '瓶') !== false) $unit = "瓶";
        elseif (mb_strpos($qtyStr, '捆') !== false) $unit = "捆";
        elseif (mb_strpos($qtyStr, '箱') !== false) $unit = "箱";

        $cleanName = trim(preg_replace('/[\(\（].*[\)\）]/u', '', $name));
        $stmt = $pdo->prepare("SELECT id, unit_per_case FROM products WHERE name LIKE ?");
        $stmt->execute(array("%$cleanName%"));
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prod) {
            $productId = $prod['id'];
            $upc = $prod['unit_per_case'] ? $prod['unit_per_case'] : 1;
        } else {
            $cat = (strpos($name, '外盒') !== false) ? '包材' : '產品';
            $ins = $pdo->prepare("INSERT INTO products (name, category, spec, unit_per_case) VALUES (?, ?, ?, 1)");
            $ins->execute(array($name, $cat, $spec));
            $productId = $pdo->lastInsertId();
            $upc = 1;
        }

        $unitCount = $qty;
        $caseCount = 0;
        if ($unit === '箱') {
            $unitCount = $qty * $upc;
            $caseCount = $qty;
        }

        $stk = $pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, case_count, unit_count, expiry_date) VALUES ('TAIPEI', ?, ?, ?, ?)");
        $stk->execute(array($productId, $caseCount, $unitCount, $expiry));
        $imported++;
    }

    echo "Imported $imported items to TAIPEI warehouse.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}