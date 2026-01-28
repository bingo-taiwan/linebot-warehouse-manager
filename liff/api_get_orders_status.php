<?php
/**
 * API: 獲取本月福利品訂單狀態看板
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once '/home/lt4.mynet.com.tw/linebot_core/LineBot.php';

$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // TODO: 正式環境應從 Header 解析 userId
    $currentUserId = $_GET['userId'] ?? 'U004f8cad542e37c7834a3920e60d1077'; 

    $lineBot = new LineBot($config['line']);

    // 1. 撈取所有員工及本月訂單狀態
    $sql = "
        SELECT 
            u.line_user_id, 
            u.name, 
            u.avatar_url,
            COALESCE(o.status, 'NONE') as status,
            o.id as order_id,
            o.items_json
        FROM users u
        LEFT JOIN orders o ON u.line_user_id = o.requester_id 
            AND o.order_type = 'BENEFIT_ORDER' 
            AND o.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        WHERE u.is_active = 1
        ORDER BY u.created_at
    ";
    
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 整理回傳資料並同步頭像
    $dashboard = [];
    $myOrder = null;

    foreach ($users as $user) {
        // 如果沒有頭像或名字包含 "倉管" (預設名)，則嘗試更新
        if (empty($user['avatar_url']) || strpos($user['name'], '倉管') !== false || strpos($user['name'], '管理員') !== false) {
            $profile = $lineBot->getProfile($user['line_user_id']);
            if ($profile) {
                $user['name'] = $profile['displayName'];
                $user['avatar_url'] = $profile['pictureUrl'];
                
                // 更新資料庫
                $updateStmt = $pdo->prepare("UPDATE users SET name = ?, avatar_url = ? WHERE line_user_id = ?");
                $updateStmt->execute([$user['name'], $user['avatar_url'], $user['line_user_id']]);
            }
        }

        $statusText = '未下單';
        $statusClass = 'secondary';

        if ($user['status'] === 'PENDING') {
            $statusText = '已下單';
            $statusClass = 'primary';
        } elseif ($user['status'] === 'RECEIVED') {
            $statusText = '已簽收';
            $statusClass = 'success';
        }

        $dashboard[] = [
            'userId' => $user['line_user_id'],
            'name' => $user['name'],
            'avatar' => $user['avatar_url'], // 新增頭像欄位
            'status' => $user['status'],
            'statusText' => $statusText,
            'statusClass' => $statusClass,
            'isMe' => ($user['line_user_id'] === $currentUserId)
        ];

        if ($user['line_user_id'] === $currentUserId && $user['status'] === 'PENDING') {
            $myOrder = [
                'order_id' => $user['order_id'],
                'items' => json_decode($user['items_json'], true)
            ];
        }
    }

    // 將「我」移到第一位
    usort($dashboard, function($a, $b) {
        if ($a['isMe']) return -1;
        if ($b['isMe']) return 1;
        return 0; // 其他人維持原順序
    });

    echo json_encode([
        'success' => true,
        'dashboard' => $dashboard,
        'myOrder' => $myOrder
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
