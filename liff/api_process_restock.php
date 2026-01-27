<?php
/**
 * API: 執行大園 -> 台北調撥
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['items'])) {
        throw new Exception("無調撥內容");
    }

    $pdo->beginTransaction();

    foreach ($input['items'] as $item) {
        $pid = $item['product_id'];
        $qty = $item['quantity']; // 箱數

        // 1. 取得產品換算率
        $prodStmt = $pdo->prepare("SELECT unit_per_case FROM products WHERE id = ?");
        $prodStmt->execute([$pid]);
        $unitPerCase = $prodStmt->fetchColumn();

        // 2. 扣除大園庫存 (FIFO)
        $stockStmt = $pdo->prepare("SELECT id, case_count, expiry_date, production_date FROM stocks WHERE product_id = ? AND warehouse_id = 'DAYUAN' AND case_count > 0 ORDER BY expiry_date ASC");
        $stockStmt->execute([$pid]);
        $batches = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

        $remainingToDeduct = $qty;
        
        foreach ($batches as $batch) {
            if ($remainingToDeduct <= 0) break;

            $deduct = min($batch['case_count'], $remainingToDeduct);
            
            // 更新大園庫存
            $updateSrc = $pdo->prepare("UPDATE stocks SET case_count = case_count - ? WHERE id = ?");
            $updateSrc->execute([$deduct, $batch['id']]);

            // 3. 增加台北庫存 (轉換為散數)
            // 嘗試尋找台北倉相同效期的批次，若有則合併，無則新增
            $destStmt = $pdo->prepare("SELECT id FROM stocks WHERE product_id = ? AND warehouse_id = 'TAIPEI' AND expiry_date = ?");
            $destStmt->execute([$pid, $batch['expiry_date']]);
            $destId = $destStmt->fetchColumn();

            $unitsToAdd = $deduct * $unitPerCase;

            if ($destId) {
                $updateDest = $pdo->prepare("UPDATE stocks SET unit_count = unit_count + ? WHERE id = ?");
                $updateDest->execute([$unitsToAdd, $destId]);
            } else {
                $insertDest = $pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date, production_date, note) VALUES (?, ?, ?, ?, ?, ?)");
                $insertDest->execute(['TAIPEI', $pid, $unitsToAdd, $batch['expiry_date'], $batch['production_date'], '大園調撥']);
            }

            $remainingToDeduct -= $deduct;
        }

        if ($remainingToDeduct > 0) {
            throw new Exception("大園倉產品 ID {$pid} 庫存不足，無法完成調撥");
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
