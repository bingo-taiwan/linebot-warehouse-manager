<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$config = require __DIR__ . '/../../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['order_id'] ?? null;
    $newStatus = $input['status'] ?? null;

    if (!$orderId || !$newStatus) {
        throw new Exception("缺少參數");
    }

    $pdo->beginTransaction();

    // 1. Get Order Info
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) throw new Exception("訂單不存在");

    $currentStatus = $order['status'];
    $orderType = $order['order_type'];
    $items = json_decode($order['items_json'], true);

    if ($currentStatus === $newStatus) {
        $pdo->rollBack();
        echo json_encode(['success' => true, 'message' => '狀態未變更']);
        exit;
    }

    // Logic for DAYUAN_ORDER (Restock)
    if ($orderType === 'DAYUAN_ORDER') {
        
        // PENDING -> SHIPPED (Deduct Dayuan)
        if ($currentStatus === 'PENDING' && $newStatus === 'SHIPPED') {
            $items = deductDayuanStock($pdo, $items);
            updateOrderItems($pdo, $orderId, $items);
        }
        
        // SHIPPED -> RECEIVED (Add Taipei)
        elseif ($currentStatus === 'SHIPPED' && $newStatus === 'RECEIVED') {
            addTaipeiStock($pdo, $items);
        }

        // PENDING -> RECEIVED (Skip Ship -> Do Both)
        elseif ($currentStatus === 'PENDING' && $newStatus === 'RECEIVED') {
            $items = deductDayuanStock($pdo, $items);
            updateOrderItems($pdo, $orderId, $items); // Save the deduction info
            addTaipeiStock($pdo, $items);
        }
    }

    // Update Status
    $updateStmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$newStatus, $orderId]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Helpers

function deductDayuanStock($pdo, $items) {
    foreach ($items as &$item) {
        $pid = $item['product_id'];
        $qtyCases = $item['quantity']; // Cases
        $item['batches_used'] = [];

        // FIFO Deduction from Dayuan
        $stmt = $pdo->prepare("SELECT id, case_count, expiry_date FROM stocks WHERE product_id = ? AND warehouse_id = 'DAYUAN' AND case_count > 0 ORDER BY expiry_date ASC");
        $stmt->execute([$pid]);
        $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $remaining = $qtyCases;
        foreach ($stocks as $stock) {
            if ($remaining <= 0) break;
            
            $deduct = min($stock['case_count'], $remaining);
            
            // Update Stock
            $upd = $pdo->prepare("UPDATE stocks SET case_count = case_count - ? WHERE id = ?");
            $upd->execute([$deduct, $stock['id']]);

            // Record Batch
            $item['batches_used'][] = [
                'expiry' => $stock['expiry_date'],
                'cases' => $deduct
            ];

            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            throw new Exception("大園倉庫存不足 (產品ID: $pid)");
        }
    }
    return $items;
}

function addTaipeiStock($pdo, $items) {
    foreach ($items as $item) {
        $pid = $item['product_id'];
        
        // Get Unit Per Case
        $pStmt = $pdo->prepare("SELECT unit_per_case FROM products WHERE id = ?");
        $pStmt->execute([$pid]);
        $unitPerCase = $pStmt->fetchColumn() ?: 1;

        $batches = $item['batches_used'] ?? [];
        
        if (empty($batches)) {
            // Fallback if no batch info (Legacy or Error) -> Assume No Expiry, convert total qty
            $qtyCases = $item['quantity'];
            $totalUnits = $qtyCases * $unitPerCase;
            
            // Add to a general stock entry (Expiry NULL)
            addStockEntry($pdo, 'TAIPEI', $pid, $totalUnits, null);
        } else {
            // Use batch info
            foreach ($batches as $batch) {
                $units = $batch['cases'] * $unitPerCase;
                $expiry = $batch['expiry'];
                addStockEntry($pdo, 'TAIPEI', $pid, $units, $expiry);
            }
        }
    }
}

function addStockEntry($pdo, $warehouse, $pid, $units, $expiry) {
    // Check if exists
    $sql = "SELECT id FROM stocks WHERE product_id = ? AND warehouse_id = ? AND ";
    $params = [$pid, $warehouse];
    
    if ($expiry === null) {
        $sql .= "expiry_date IS NULL";
    } else {
        $sql .= "expiry_date = ?";
        $params[] = $expiry;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stockId = $stmt->fetchColumn();

    if ($stockId) {
        $upd = $pdo->prepare("UPDATE stocks SET unit_count = unit_count + ? WHERE id = ?");
        $upd->execute([$units, $stockId]);
    } else {
        $ins = $pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, unit_count, expiry_date, case_count) VALUES (?, ?, ?, ?, 0)");
        $ins->execute([$warehouse, $pid, $units, $expiry]);
    }
}

function updateOrderItems($pdo, $orderId, $items) {
    $json = json_encode($items, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("UPDATE orders SET items_json = ? WHERE id = ?");
    $stmt->execute([$json, $orderId]);
}

