<?php
/**
 * API: 處理 LIFF 提交的新品入庫
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 取得 JSON 輸入
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("無效的輸入資料");
    }

    $pdo->beginTransaction();

    $productId = $input['product_id'] ?? null;

    if (!$productId) {
        // 1. 插入新產品
        $stmt = $pdo->prepare("INSERT INTO products (name, category, spec, unit_per_case) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $input['name'],
            $input['category'],
            $input['spec'],
            $input['unit_per_case'] ?? 1
        ]);
        $productId = $pdo->lastInsertId();
    }

    // 2. 插入大園倉庫存
    $stmt = $pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, case_count, expiry_date) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        'DAYUAN',
        $productId,
        $input['cases'] ?? 0,
        !empty($input['expiry_date']) ? $input['expiry_date'] : null
    ]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => '入庫成功！']);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
