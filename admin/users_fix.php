<?php
/**
 * LINE Bot Dashboard - 使用者紀錄 (支援權限修改與多種 Config 格式)
 */
date_default_timezone_set('Asia/Taipei');
require_once '/home/lt4.mynet.com.tw/linebot_core/Analytics.php';

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

$botId = $_GET['bot'] ?? 'dietitian';

$botConfigs = [
    'dietitian' => [
        'name' => 'Dietitian Dilbert',
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/config.php',
    ],
    'lifehacking' => [
        'name' => '五行穿衣 Bot',
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/lifehacking',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/lifehacking/config.php',
    ],
    'monitor' => [
        'name' => '網路有梗哥（監控）',
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/monitor',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/monitor/config.php',
    ],
    'quiz-suido' => [
        'name' => '穗稻忠武題庫',
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz-suido',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz-suido/config.php',
    ],
    'warehouse' => [
        'name' => '倉管小幫手',
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/warehouse',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php',
        'has_rbac' => true,
    ],
];

if (!isset($botConfigs[$botId])) die('Invalid bot');
$botConfig = $botConfigs[$botId];

// 讀取設定與 Access Token
$accessToken = null;
$dbConfig = null;
if (file_exists($botConfig['config'])) {
    // 使用全域變數以獲取 config.php 內可能定義的變數
    global $LINE_BOTS;
    $res = include $botConfig['config'];
    
    // 優先從返回的陣列讀取 (Warehouse 模式)
    if (is_array($res)) {
        $accessToken = $res['line']['access_token'] ?? null;
        $dbConfig = $res['db']['mysql'] ?? null;
    }
    
    // 其次從常數讀取 (Dietitian 模式)
    if (!$accessToken && defined('LINE_CHANNEL_ACCESS_TOKEN')) {
        $accessToken = LINE_CHANNEL_ACCESS_TOKEN;
    }
    
    // 再次從全域變數 $LINE_BOTS 讀取 (多 Bot 模式)
    if (!$accessToken && isset($LINE_BOTS[$botId]['token'])) {
        $accessToken = $LINE_BOTS[$botId]['token'];
    }
}

// 取得資料庫角色 (如果是 warehouse)
$dbRoles = [];
if ($botId === 'warehouse' && $dbConfig) {
    try {
        $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}", $dbConfig['username'], $dbConfig['password']);
        $stmt = $pdo->query("SELECT line_user_id, name as db_name, role, is_active FROM users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbRoles[$row['line_user_id']] = $row;
        }
    } catch (Exception $e) {}
}

$analytics = new Analytics($botId, $botConfig['path'] . '/data');
$users = $analytics->getAllUsers();

function getLineProfile($userId, $accessToken, $cacheDir) {
    if (!$accessToken) return null;
    $cacheFile = $cacheDir . '/profile_' . substr($userId, 0, 16) . '.json';
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && (time() - $cacheData['cached_at']) < 86400) return $cacheData;
    }
    $ch = curl_init('https://api.line.me/v2/bot/profile/' . $userId);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken], CURLOPT_TIMEOUT => 5]);
    $response = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
        $data = json_decode($response, true);
        $data['cached_at'] = time();
        file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }
    return null;
}

$userProfiles = [];
foreach ($users as $userId => $userStats) {
    $profile = getLineProfile($userId, $accessToken, $cacheDir);
    $roleInfo = $dbRoles[$userId] ?? ['role' => 'guest', 'is_active' => 0, 'db_name' => ''];
    $userProfiles[$userId] = [
        'stats' => $userStats,
        'profile' => $profile,
        'role' => $roleInfo['role'],
        'db_name' => $roleInfo['db_name'],
        'is_active' => $roleInfo['is_active']
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>使用者紀錄 - <?= htmlspecialchars($botConfig['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        header { background: #00B900; color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .back-link { display: block; margin-bottom: 15px; color: #00B900; text-decoration: none; font-weight: bold; }
        .bot-tabs { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .bot-tab { padding: 8px 15px; border-radius: 8px; text-decoration: none; color: #666; background: white; border: 1px solid #ddd; white-space: nowrap; }
        .bot-tab.active { background: #00B900; color: white; border-color: #00B900; }
        .user-card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #00B900; }
        .user-info { flex: 1; min-width: 0; }
        .user-name { font-weight: bold; font-size: 16px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .user-id { font-size: 10px; color: #999; font-family: monospace; }
        .user-role-box { padding: 10px; background: #f9f9f9; border-radius: 8px; min-width: 150px; }
        .role-select { width: 100%; padding: 5px; border-radius: 4px; border: 1px solid #ddd; font-size: 13px; }
        .user-stats { display: flex; gap: 15px; text-align: center; margin-left: auto; }
        .stat-item .value { font-weight: bold; color: #00B900; }
        .stat-item .label { font-size: 10px; color: #666; }
        .detail-link { color: #00B900; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 10px; }
        
        @media (max-width: 768px) {
            .user-card { flex-direction: column; align-items: flex-start; }
            .user-stats { width: 100%; justify-content: space-around; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
            .user-role-box { width: 100%; margin-top: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">&larr; 返回 Dashboard</a>
        <header>
            <h1>👥 使用者紀錄: <?= htmlspecialchars($botConfig['name']) ?></h1>
        </header>

        <div class="bot-tabs">
            <?php foreach ($botConfigs as $id => $cfg): ?>
            <a href="?bot=<?= $id ?>" class="bot-tab <?= $id === $botId ? 'active' : '' ?>"><?= htmlspecialchars($cfg['name']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php foreach ($userProfiles as $userId => $data): 
            $displayName = $data['profile']['displayName'] ?? $data['db_name'] ?? '未知使用者';
            $pictureUrl = $data['profile']['pictureUrl'] ?? null;
        ?>
        <div class="user-card">
            <?php if ($pictureUrl): ?>
                <img src="<?= htmlspecialchars($pictureUrl) ?>" class="user-avatar">
            <?php else: ?>
                <div style="width:50px; height:50px; background:#eee; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#999;">👤</div>
            <?php endif; ?>

            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($displayName) ?></div>
                <div class="user-id"><?= $userId ?></div>
                <a href="user_detail.php?bot=<?= $botId ?>&user=<?= urlencode($userId) ?>" class="detail-link">🔍 詳細紀錄</a>
            </div>

            <?php if (isset($botConfig['has_rbac'])): ?>
            <div class="user-role-box">
                <div style="font-size: 11px; color:#666; margin-bottom:4px;">權限角色:</div>
                <select class="role-select" onchange="updateRole('<?= $userId ?>', this.value)">
                    <option value="ADMIN_WAREHOUSE" <?= $data['role'] === 'ADMIN_WAREHOUSE' ? 'selected' : '' ?>>📦 倉管</option>
                    <option value="ADMIN_OFFICE" <?= $data['role'] === 'ADMIN_OFFICE' ? 'selected' : '' ?>>🏢 行政</option>
                    <option value="SALES_LECTURER" <?= $data['role'] === 'SALES_LECTURER' ? 'selected' : '' ?>>👨‍🏫 業務講師</option>
                    <option value="guest" <?= ($data['role'] === 'guest' || !$data['role']) ? 'selected' : '' ?>>👤 訪客</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="user-stats">
                <div class="stat-item">
                    <div class="value"><?= $data['stats']['total_requests'] ?></div>
                    <div class="label">總請求</div>
                </div>
                <div class="stat-item">
                    <div class="value"><?= $data['stats']['active_days'] ?></div>
                    <div class="label">天數</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    function updateRole(userId, newRole) {
        if (!confirm('確定要更改此用戶的權限嗎？')) return;
        
        fetch('api/update_user_role.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                bot: '<?= $botId ?>',
                user_id: userId,
                role: newRole
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('權限已更新');
                location.reload();
            } else {
                alert('更新失敗: ' + data.message);
            }
        });
    }
    </script>
</body>
</html>
