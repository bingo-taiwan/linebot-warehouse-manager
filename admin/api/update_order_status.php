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
    $status = $input['status'] ?? null;

    if (!$orderId || !$status) {
        throw new Exception("缺少參數");
    }

    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$status, $orderId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
