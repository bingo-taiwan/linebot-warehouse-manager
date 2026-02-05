<?php
header('Content-Type: application/json');
require_once '/home/lt4.mynet.com.tw/linebot_core/Analytics.php';

$input = json_decode(file_get_contents('php://input'), true);
$botId = $input['bot'] ?? '';
$userId = $input['user_id'] ?? '';
$role = $input['role'] ?? '';

if (!$botId || !$userId || !$role) {
    echo json_encode(['success' => false, 'message' => '缺少參數']);
    exit;
}

// 目前僅 warehouse 支援 RBAC
if ($botId !== 'warehouse') {
    echo json_encode(['success' => false, 'message' => '此 Bot 不支援權限管理']);
    exit;
}

// 讀取該 Bot 的 config 以獲取 DB 連線
$configPath = "/home/lt4.mynet.com.tw/public_html/linebot/{$botId}/config.php";
if (!file_exists($configPath)) {
    echo json_encode(['success' => false, 'message' => '找不到設定檔']);
    exit;
}

$config = @include $configPath;
if (!is_array($config) || !isset($config['db']['mysql'])) {
    echo json_encode(['success' => false, 'message' => '資料庫設定錯誤']);
    exit;
}

$dbCfg = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$dbCfg['host']};dbname={$dbCfg['database']};charset={$dbCfg['charset']}", $dbCfg['username'], $dbCfg['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 檢查用戶是否已存在於 users 表，若無則插入，若有則更新
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE line_user_id = ?");
    $stmt->execute([$userId]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $stmt = $pdo->prepare("UPDATE users SET role = ?, is_active = 1 WHERE line_user_id = ?");
        $stmt->execute([$role, $userId]);
    } else {
        // 從 LINE 獲取名稱 (選填)
        $stmt = $pdo->prepare("INSERT INTO users (line_user_id, role, is_active, name) VALUES (?, ?, 1, '新使用者')");
        $stmt->execute([$userId, $role]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '資料庫錯誤: ' . $e->getMessage()]);
}
