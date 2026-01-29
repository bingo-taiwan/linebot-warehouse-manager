<?php
/**
 * API: 獲取所有產品清單 (供新品入庫使用)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 查詢所有產品，不論庫存
    // 按分類排序
    $sql = "SELECT id, name, category, spec, unit_per_case FROM products ORDER BY category, id DESC";

    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'data' => $products
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
