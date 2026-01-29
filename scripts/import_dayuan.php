<?php
require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$dbConfig = $config['db'];

try {
    $dsn = "mysql:host={$dbConfig['mysql']['host']};dbname={$dbConfig['mysql']['database']};charset={$dbConfig['mysql']['charset']}";
    $pdo = new PDO($dsn, $dbConfig['mysql']['username'], $dbConfig['mysql']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage() . "\n");
}

$file = __DIR__ . '/../data/dayuan_stock.txt';
$content = file_get_contents($file);
$lines = explode("\n", $content);

$currentCategory = '產品'; // 預設分類
$buffer = [];

function parseBlock($lines, $category, $pdo) {
    if (empty($lines)) return; 
    
    $raw = implode("\n", $lines);
    // echo "--- Parsing Block ---\n$raw\n";

    // 1. 提取品名 (第一行)
    $name = trim($lines[0]);
    if (strpos($name, '：') !== false) {
        $parts = explode('：', $name);
        $name = trim(end($parts)); // 處理 "數量：甲足飽裸包" 這種怪格式
    }

    // 2. 提取規格
    $spec = '';
    if (preg_match('/(\d+(?:g|mL|克|粒|kg)\/[^\s，
]+)/u', $raw, $m)) {
        $spec = $m[1];
    }

    // 3. 提取箱入數 (Unit Per Case)
    // 優先找 "XX包/箱" 這種格式
    $unitPerCase = 1;
    // 處理多種箱入數的情況 (e.g. 193包/箱*1, 300包/箱*2)
    // 如果發現多種箱入數，需要拆分成不同產品處理
    
    // 先找出所有可能的箱入數與數量組合
    // 模式： 193包/箱*1  或  共23箱  或  2200包/箱*1
    $batches = [];
    
    // 尋找日期
    $date = null;
    $dateType = null; // expiry or production
    if (preg_match('/(到期日|生產日|製造日期)[:\s]*(\d{4}\.\d{2}\.\d{2})/u', $raw, $m)) {
        $date = str_replace('.', '-', $m[2]);
        $dateType = ($m[1] == '到期日') ? 'expiry' : 'production';
    }

    // 尋找 "XX/箱" 的行
    $hasMultiSpec = false;
    foreach ($lines as $line) {
        if (preg_match('/(\d+)[^\d\/]*\/箱(?:\*(\d+))?/', $line, $m)) {
            $u = $m[1];
            $q = isset($m[2]) ? $m[2] : null;
            
            // 如果這行已經包含了數量 (*N)，直接作為一個批次
            if ($q !== null) {
                $batches[] = [
                    'unit_per_case' => $u,
                    'case_count' => $q,
                    'unit_count' => 0
                ];
                $hasMultiSpec = true;
            } else {
                // 只是定義箱入數，數量在別行
                $unitPerCase = $u;
            }
        }
    }

    // 如果沒有多規格批次，則尋找總數量
    if (empty($batches)) {
        $caseCount = 0;
        $unitCount = 0;
        
        // 尋找 "一共23箱" 或 "共1箱"
        if (preg_match('/(?:共|一共)(\d+)箱/u', $raw, $m)) {
            $caseCount = $m[1];
        }
        
        // 尋找 "18箱+1小箱（4盒）"
        if (preg_match('/(\d+)箱\+(\d+)小箱[\（\(](\d+)(?:盒|包|瓶)[\）\)]/u', $raw, $m)) {
            $caseCount = $m[1]; // 主箱數
            // 小箱通常不計入標準箱，而是視為散數
            // 這裡直接取括號內的數字作為散數 unit_count
            $unitCount = $m[3]; 
        }

        if ($caseCount > 0 || $unitCount > 0) {
            $batches[] = [
                'unit_per_case' => $unitPerCase,
                'case_count' => $caseCount,
                'unit_count' => $unitCount
            ];
        }
    }

    if (empty($batches)) {
        // 雜項可能沒有箱入數，只有數量 "1座"
        if ($category === '雜項') {
             if (preg_match('/(\d+)(?:座|個|台|組)/u', $raw, $m)) {
                 $batches[] = [
                     'unit_per_case' => 1,
                     'case_count' => 0,
                     'unit_count' => $m[1]
                 ];
             }
        }
    }

    // 寫入資料庫
    foreach ($batches as $batch) {
        $finalName = $name;
        $finalUnitPerCase = $batch['unit_per_case'];
        
        // 如果是多規格混合(如靚顏)，名稱加上規格以示區別
        if ($hasMultiSpec) {
            $finalName .= " ({$finalUnitPerCase}入)";
        }

        echo "Importing: [$category] $finalName (Spec: $spec, Unit: $finalUnitPerCase) -> {$batch['case_count']}箱 {$batch['unit_count']}散, Date: $date ($dateType)\n";

        // 1. Insert/Get Product
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND unit_per_case = ?");
        $stmt->execute([$finalName, $finalUnitPerCase]);
        $pid = $stmt->fetchColumn();

        if (!$pid) {
            $stmt = $pdo->prepare("INSERT INTO products (name, category, spec, unit_per_case) VALUES (?, ?, ?, ?)");
            $stmt->execute([$finalName, $category, $spec, $finalUnitPerCase]);
            $pid = $pdo->lastInsertId();
        }

        // 2. Insert Stock
        $expiryDate = ($dateType === 'expiry') ? $date : null;
        $prodDate = ($dateType === 'production') ? $date : null;
        
        // 如果是過期品，在備註加註
        $note = (strpos($raw, '過期') !== false) ? '盤點標示過期' : '';

        $stmt = $pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, case_count, unit_count, expiry_date, production_date, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['DAYUAN', $pid, $batch['case_count'], $batch['unit_count'], $expiryDate, $prodDate, $note]);
    }
}

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) {
        if (!empty($buffer)) {
            parseBlock($buffer, $currentCategory, $pdo);
            $buffer = [];
        }
        continue;
    }

    // Check category change
    if (strpos($line, '<產品>') !== false) {
        $currentCategory = '產品';
        continue;
    }
    if (strpos($line, '包材') !== false && strlen($line) < 10) { // 避免誤判包含"包材"的品名
        $currentCategory = '包材';
        continue;
    }
    if (strpos($line, '雜項') !== false && strlen($line) < 10) {
        $currentCategory = '雜項';
        continue;
    }

    $buffer[] = $line;
}

// Flush last buffer
if (!empty($buffer)) {
    parseBlock($buffer, $currentCategory, $pdo);
}

echo "Done.\n";

