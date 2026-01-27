<?php
/**
 * LINE Bot Dashboard - 統一監控介面
 *
 * @package linebot_admin
 * @version 2.0.0
 * @date 2026-01-20
 *
 * 功能：
 * - 以 Bot 為中心的架構，每個 Bot 顯示專屬功能
 * - 從 LINE API 載入 Bot 名稱和頭像
 * - 顯示每個 Bot 的統計、題庫、使用者紀錄
 */

// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

// 載入核心
require_once '/home/lt4.mynet.com.tw/linebot_core/Analytics.php';

$helpersFile = '/home/lt4.mynet.com.tw/linebot_core/helpers.php';
if (file_exists($helpersFile)) {
    require_once $helpersFile;
}

// 快取目錄
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// 處理快取清除請求
if (isset($_GET['refresh_cache'])) {
    $target = $_GET['refresh_cache'];
    if ($target === 'all') {
        array_map('unlink', glob($cacheDir . '/*.json'));
    } else {
        $cacheFile = $cacheDir . '/' . $target . '_info.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/**
 * 從 LINE API 取得 Bot 資訊
 */
function getLineBotInfo($accessToken, $cacheFile) {
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && isset($cacheData['cached_at']) && (time() - $cacheData['cached_at']) < 3600) {
            return $cacheData;
        }
    }

    $ch = curl_init('https://api.line.me/v2/bot/info');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $data['cached_at'] = time();
        $data['api_success'] = true;
        file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $data;
    }

    return ['api_success' => false, 'displayName' => null, 'basicId' => null, 'pictureUrl' => null];
}

/**
 * 取得題庫統計
 */
function getQuizStats($quizDir, $subjects) {
    $stats = [];
    foreach ($subjects as $subjectId => $subjectName) {
        $subjectDir = $quizDir . '/' . $subjectId;
        if (is_dir($subjectDir)) {
            $quizFiles = glob($subjectDir . '/*-quiz.json') ?: [];
            // 支援 subject/book/chapter 結構
            $quizFiles = array_merge($quizFiles, glob($subjectDir . '/*/*-quiz.json') ?: []);
            $totalQuestions = 0;
            foreach ($quizFiles as $file) {
                $quiz = json_decode(file_get_contents($file), true);
                if (isset($quiz['metadata']['total_questions'])) {
                    $totalQuestions += $quiz['metadata']['total_questions'];
                } elseif (isset($quiz['questions'])) {
                    $totalQuestions += count($quiz['questions']);
                }
            }
            $stats[$subjectId] = [
                'name' => $subjectName,
                'chapters' => count($quizFiles),
                'questions' => $totalQuestions,
            ];
        }
    }
    return $stats;
}

// ========== Bot 設定清單（以 Bot 為中心）==========
$bots = [
    'dietitian' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/config.php',
        'fallback_name' => 'Dietitian Dilbert 題庫系統',
        'fallback_icon' => '📚',
        // 該 Bot 擁有的功能
        'features' => [
            'quiz' => true,       // 題庫系統
            'wuxing' => true,     // 五行穿衣
            'elements' => true,   // 元素週期表
        ],
        // 該 Bot 的題庫科目
        'quiz_subjects' => [
            'chemistry' => '普通化學',
            'physiology' => '人體生理學',
            'nutrition' => '營養學',
            'biology' => '普通生物學',
        ],
        'quiz_dir' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz',
    ],
    'lifehacking' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/lifehacking',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/lifehacking/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/lifehacking/config.php',
        'fallback_name' => 'Lifehacking Bot',
        'fallback_icon' => '🎨',
        // 該 Bot 擁有的功能
        'features' => [
            'wuxing' => true,     // 五行穿衣
            'weather' => true,    // 天氣預報
        ],
        // 無題庫
        'quiz_subjects' => [],
        'quiz_dir' => null,
    ],
    'monitor' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/monitor',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/monitor/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/monitor/config.php',
        'fallback_name' => '網路有梗哥（監控）',
        'fallback_icon' => '🖥️',
        // 該 Bot 擁有的功能
        'features' => [
            'system_monitor' => true,  // 系統監控
            'api_usage' => true,       // API 用量追蹤
            'line_quota' => true,      // LINE 額度查詢
        ],
        // 無題庫
        'quiz_subjects' => [],
        'quiz_dir' => null,
    ],
    'quiz-suido' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz-suido',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/quiz-suido/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz-suido/config.php',
        'fallback_name' => '穗稻忠武',
        'fallback_icon' => '📚',
        'features' => [
            'quiz' => true,
        ],
        'quiz_subjects' => [
            'history' => '歷史',
            'geography' => '地理',
            'civics' => '公民',
            'chinese' => '國文',
            'english' => '英語',
            'math' => '數學',
            'science' => '自然',
        ],
        'quiz_dir' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz-suido/quiz',
    ],
    'warehouse' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/warehouse',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/warehouse/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php',
        'fallback_name' => '倉管小幫手',
        'fallback_icon' => '📦',
        'features' => [
            'inventory' => true,
        ],
        'quiz_subjects' => [],
        'quiz_dir' => null,
    ],
];


// 敺 Bot  config.php 霈 access_token 銝血敺 LINE 鞈
foreach ($bots as $botId => &$bot) {
    $bot['line_info'] = null;
    $accessToken = null;

    if (file_exists($bot['config'])) {
        $config = @include $bot['config'];

        if (is_array($config) && isset($config['line']['access_token'])) {
            $accessToken = $config['line']['access_token'];
        } elseif (defined('LINE_CHANNEL_ACCESS_TOKEN')) {
            $accessToken = LINE_CHANNEL_ACCESS_TOKEN;
        }

        if ($accessToken) {
            $cacheFile = $cacheDir . '/' . $botId . '_info.json';
            $bot['line_info'] = getLineBotInfo($accessToken, $cacheFile);
        }
    }

    // 設定顯示名稱和圖示
    if ($bot['line_info'] && $bot['line_info']['api_success']) {
        $bot['name'] = $bot['line_info']['displayName'];
        $bot['basic_id'] = $bot['line_info']['basicId'] ?? null;
        $bot['picture_url'] = $bot['line_info']['pictureUrl'] ?? null;
        $bot['icon'] = null;
    } else {
        $bot['name'] = $bot['fallback_name'];
        $bot['basic_id'] = null;
        $bot['picture_url'] = null;
        $bot['icon'] = $bot['fallback_icon'];
    }

    // 取得該 Bot 的題庫統計
    if (!empty($bot['quiz_subjects']) && $bot['quiz_dir']) {
        $bot['quiz_stats'] = getQuizStats($bot['quiz_dir'], $bot['quiz_subjects']);
    } else {
        $bot['quiz_stats'] = [];
    }
}
unset($bot);

// 收集統計資料
$stats = [];
$total60min = 0;
$totalToday = 0;
$totalQuestions = 0;
$totalChapters = 0;

foreach ($bots as $botId => $bot) {
    $dataDir = $bot['path'] . '/data';
    $analytics = new Analytics($botId, $dataDir);
    $todayStats = $analytics->getToday();

    $todayRequests = 0;
    $todayErrors = 0;
    $debugLog = $bot['path'] . '/debug.log';
    if (file_exists($debugLog)) {
        $logContent = file_get_contents($debugLog);
        $todayPrefix = date('Y-m-d');
        $todayRequests = substr_count($logContent, $todayPrefix . ' ');
        $todayErrors = substr_count($logContent, 'Error') + substr_count($logContent, 'error');
    }

    $recent60min = $todayStats['recent_60min'] ?? 0;
    $todayUsers = $todayStats['unique_users'] ?? 0;
    $webhookCount = $todayStats['webhook_count'] ?? $todayRequests;
    $errors = $todayStats['errors'] ?? min($todayErrors, 99);

    $stats[$botId] = [
        'enabled' => true,
        'webhook_count' => $webhookCount,
        'recent_60min' => $recent60min,
        'today_users' => $todayUsers,
        'errors' => $errors,
    ];

    $total60min += $recent60min;
    $totalToday += $todayUsers;

    // 累計題庫統計
    if (!empty($bot['quiz_stats'])) {
        foreach ($bot['quiz_stats'] as $subject) {
            $totalQuestions += $subject['questions'];
            $totalChapters += $subject['chapters'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LINE Bot Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header {
            background: linear-gradient(135deg, #00B900 0%, #009900 100%);
            color: white;
            padding: 30px 20px;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        header h1 { font-size: 28px; margin-bottom: 5px; }
        header p { opacity: 0.9; font-size: 14px; }
        .timezone-note { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stat-card .icon { font-size: 28px; margin-bottom: 8px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #00B900; }
        .stat-card .label { color: #666; font-size: 13px; margin-top: 5px; }
        .stat-card.highlight { background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%); }
        .stat-card.highlight .value { color: #1565C0; }

        .section-title {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #00B900;
        }

        /* Bot 卡片樣式 */
        .bot-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            overflow: hidden;
        }
        .bot-header {
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .bot-header .icon { font-size: 45px; }
        .bot-header .bot-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #00B900;
        }
        .bot-header .info { flex: 1; }
        .bot-header .info h2 { font-size: 20px; margin-bottom: 3px; color: #333; }
        .bot-header .info .bot-id { color: #00B900; font-size: 14px; font-family: monospace; font-weight: bold; }
        .bot-header .badges { display: flex; gap: 8px; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-badge.enabled { background: #E8F5E9; color: #4CAF50; }
        .status-badge.api-ok { background: #E3F2FD; color: #1565C0; }

        .bot-content { padding: 20px; }

        /* Bot 內部統計 */
        .bot-stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .bot-stat {
            text-align: center;
            padding: 15px 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .bot-stat .value { font-size: 24px; font-weight: bold; color: #333; }
        .bot-stat .label { font-size: 12px; color: #666; margin-top: 3px; }
        .bot-stat.success .value { color: #4CAF50; }
        .bot-stat.primary .value { color: #1565C0; }
        .bot-stat.warning .value { color: #FF9800; }
        .bot-stat.danger .value { color: #F44336; }

        /* 功能區塊 */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .feature-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 18px;
            border-left: 4px solid #00B900;
        }
        .feature-card h4 {
            font-size: 15px;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .feature-card .feature-icon { font-size: 20px; }

        /* 題庫統計表格 */
        .quiz-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .quiz-table th, .quiz-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .quiz-table th {
            background: #e9ecef;
            font-size: 12px;
            color: #666;
        }
        .quiz-table td { font-size: 14px; }
        .quiz-table .num { text-align: center; font-weight: bold; color: #00B900; }

        /* 按鈕 */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary { background: #00B900; color: white; }
        .btn-primary:hover { background: #009900; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-secondary:hover { background: #d0d0d0; }
        .btn-outline { background: transparent; color: #00B900; border: 1px solid #00B900; }
        .btn-outline:hover { background: #E8F5E9; }

        .actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .webhook-url {
            background: #f5f5f5;
            padding: 10px 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            color: #666;
            margin-top: 15px;
        }

        .refresh-info {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 30px;
        }
        .refresh-info a { color: #00B900; }

        .feature-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .feature-tag {
            background: #E8F5E9;
            color: #2E7D32;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 LINE Bot Dashboard</h1>
            <p>lt4.mynet.com.tw 統一監控介面 | <?= date('Y-m-d H:i:s') ?></p>
            <p class="timezone-note">時區：Asia/Taipei (UTC+8) | Bot 資訊來自 LINE API（每小時更新）</p>
        </header>

        <!-- 總覽統計 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">🤖</div>
                <div class="value"><?= count($bots) ?></div>
                <div class="label">運行中的 Bot</div>
            </div>
            <div class="stat-card highlight">
                <div class="icon">⏱️</div>
                <div class="value"><?= $total60min ?></div>
                <div class="label">60分鐘內活躍</div>
            </div>
            <div class="stat-card highlight">
                <div class="icon">👥</div>
                <div class="value"><?= $totalToday ?></div>
                <div class="label">今日活躍使用者</div>
            </div>
            <div class="stat-card">
                <div class="icon">📚</div>
                <div class="value"><?= $totalQuestions ?></div>
                <div class="label">題庫總題數</div>
            </div>
            <div class="stat-card">
                <div class="icon">📖</div>
                <div class="value"><?= $totalChapters ?></div>
                <div class="label">題庫章節數</div>
            </div>
        </div>

        <?php include __DIR__ . "/includes/api_section.php"; ?>

        <!-- 各 Bot 區塊 -->
        <h2 class="section-title">🤖 Bot 管理</h2>

        <?php foreach ($bots as $botId => $bot): ?>
        <div class="bot-section">
            <div class="bot-header">
                <?php if ($bot['picture_url']): ?>
                    <img src="<?= htmlspecialchars($bot['picture_url']) ?>" alt="Bot Avatar" class="bot-avatar">
                <?php else: ?>
                    <div class="icon"><?= $bot['icon'] ?></div>
                <?php endif; ?>
                <div class="info">
                    <h2><?= htmlspecialchars($bot['name']) ?></h2>
                    <?php if ($bot['basic_id']): ?>
                        <div class="bot-id"><?= htmlspecialchars($bot['basic_id']) ?></div>
                    <?php else: ?>
                        <div class="bot-id" style="color: #666;"><?= $botId ?></div>
                    <?php endif; ?>
                    <div class="feature-list">
                        <?php foreach ($bot['features'] as $feature => $enabled): ?>
                            <?php if ($enabled): ?>
                                <?php
                                $featureNames = [
                                    'quiz' => '📝 題庫',
                                    'wuxing' => '🎨 五行穿衣',
                                    'elements' => '⚗️ 元素週期表',
                                    'weather' => '🌤️ 天氣預報',
                                    'system_monitor' => '🖥️ 系統監控',
                                    'api_usage' => ' API 券',
                                    'line_quota' => ' LINE 憿漲',
                                    'inventory' => '📦 倉儲管理',
                                ];
                                ?>
                                <span class="feature-tag"><?= $featureNames[$feature] ?? $feature ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="badges">
                    <span class="status-badge <?= $stats[$botId]['enabled'] ? 'enabled' : 'disabled' ?>">
                        <?= $stats[$botId]['enabled'] ? '運行中' : '已停用' ?>
                    </span>
                    <?php if ($bot['line_info'] && $bot['line_info']['api_success']): ?>
                        <span class="status-badge api-ok">API ✓</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bot-content">
                <!-- Bot 統計數據 -->
                <div class="bot-stats-row">
                    <div class="bot-stat success">
                        <div class="value"><?= $stats[$botId]['webhook_count'] ?></div>
                        <div class="label">今日請求</div>
                    </div>
                    <div class="bot-stat primary">
                        <div class="value"><?= $stats[$botId]['recent_60min'] ?></div>
                        <div class="label">60分鐘活躍</div>
                    </div>
                    <div class="bot-stat warning">
                        <div class="value"><?= $stats[$botId]['today_users'] ?></div>
                        <div class="label">今日使用者</div>
                    </div>
                    <div class="bot-stat <?= $stats[$botId]['errors'] > 0 ? 'danger' : '' ?>">
                        <div class="value"><?= $stats[$botId]['errors'] ?></div>
                        <div class="label">錯誤數</div>
                    </div>
                </div>

                <!-- 功能區塊 -->
                <div class="features-grid">
                    <!-- 題庫統計（如果有的話）-->
                    <?php if (!empty($bot['quiz_stats'])): ?>
                    <div class="feature-card">
                        <h4><span class="feature-icon">📚</span> 題庫統計</h4>
                        <table class="quiz-table">
                            <thead>
                                <tr>
                                    <th>科目</th>
                                    <th style="text-align:center;">章節</th>
                                    <th style="text-align:center;">題數</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bot['quiz_stats'] as $subjectId => $subject): ?>
                                <tr>
                                    <td><?= htmlspecialchars($subject['name']) ?></td>
                                    <td class="num"><?= $subject['chapters'] ?></td>
                                    <td class="num"><?= $subject['questions'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- 使用者紀錄入口 -->
                    <div class="feature-card">
                        <h4><span class="feature-icon">👥</span> 使用者管理</h4>
                        <p style="color: #666; font-size: 13px; margin-bottom: 12px;">
                            查看此 Bot 的使用者活動紀錄與統計
                        </p>
                        <a href="users.php?bot=<?= $botId ?>" class="btn btn-primary">📋 查看使用者紀錄</a>
                    </div>
                </div>

                <!-- Webhook 與操作 -->
                <div class="webhook-url">Webhook: <?= htmlspecialchars($bot['webhook']) ?></div>
                <div class="actions-row">
                    <a href="<?= $bot['webhook'] ?>" target="_blank" class="btn btn-secondary">🔗 測試 Webhook</a>
                    <a href="?refresh_cache=<?= $botId ?>" class="btn btn-outline">🔄 更新 Bot 資訊</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <p class="refresh-info">
            頁面自動刷新間隔：60 秒 |
            <a href="javascript:location.reload()">立即刷新</a> |
            <a href="?refresh_cache=all">清除所有快取</a>
        </p>
    </div>
    <script>
        setTimeout(() => location.reload(), 60000);
    </script>
</body>
</html>
