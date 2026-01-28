<?php
/**
 * API: 獲取可選購商品清單
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 取得個別用戶額度
    // TODO: 正式環境應從 Header 取得 Authorization Token 解析 userId
    // 這裡暫時透過 GET 參數或預設邏輯判斷 (模擬 VIP 登入)
    $targetUserId = $_GET['userId'] ?? ''; 
    $userQuota = $config['ui']['employee_benefit_quota']; // 預設 10000

    if ($targetUserId) {
        $stmt = $pdo->prepare("SELECT benefit_quota FROM users WHERE line_user_id = ?");
        $stmt->execute([$targetUserId]);
        $quota = $stmt->fetchColumn();
        if ($quota) {
            $userQuota = $quota;
        }
    }

    // 查詢產品及其在庫存表中的總量 (只計算台北倉的散貨)
    $sql = "SELECT 
                p.id, 
                p.name, 
                p.category, 
                p.spec, 
                p.price_member,
                p.image_url,
                SUM(s.unit_count) as total_units
            FROM products p
            LEFT JOIN stocks s ON p.id = s.product_id AND s.warehouse_id = 'TAIPEI'
            WHERE (s.expiry_date IS NULL OR s.expiry_date > CURDATE())
            GROUP BY p.id
            HAVING total_units > 0";

    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'data' => $products,
        'quota_limit' => $userQuota 
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
