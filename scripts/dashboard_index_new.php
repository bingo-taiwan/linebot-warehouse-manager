<?php
/**
 * LINE Bot Dashboard - 蝯曹隞
 *
 * @package linebot_admin
 * @version 2.0.0
 * @date 2026-01-20
 */

// 閮剖啁
date_default_timezone_set('Asia/Taipei');

// 頛詨
require_once '/home/lt4.mynet.com.tw/linebot_core/Analytics.php';

$helpersFile = '/home/lt4.mynet.com.tw/linebot_core/helpers.php';
if (file_exists($helpersFile)) {
    require_once $helpersFile;
}

// 敹怠桅
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// ========== Bot 閮剖皜嚗誑 Bot 箔葉敹==========
$bots = [
    'dietitian' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/config.php',
        'fallback_name' => 'Dietitian Dilbert 憿澈蝟餌絞',
        'fallback_icon' => '',
        'features' => [
            'quiz' => true,
            'wuxing' => true,
            'elements' => true,
        ],
        'quiz_subjects' => [
            'chemistry' => '桅摮',
            'physiology' => '鈭粹摮',
            'nutrition' => '摮',
            'biology' => '桅拙飛',
        ],
        'quiz_dir' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz',
    ],
    'lifehacking' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/lifehacking',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/lifehacking/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/lifehacking/config.php',
        'fallback_name' => 'Lifehacking Bot',
        'fallback_icon' => '',
        'features' => [
            'wuxing' => true,
            'weather' => true,
        ],
        'quiz_subjects' => [],
        'quiz_dir' => null,
    ],
    'monitor' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/monitor',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/monitor/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/monitor/config.php',
        'fallback_name' => '蝬脰楝伐嚗',
        'fallback_icon' => '儭',
        'features' => [
            'system_monitor' => true,
            'api_usage' => true,
            'line_quota' => true,
        ],
        'quiz_subjects' => [],
        'quiz_dir' => null,
    ],
    'quiz-suido' => [
        'path' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz-suido',
        'webhook' => 'https://lt4.mynet.com.tw/linebot/quiz-suido/webhook.php',
        'config' => '/home/lt4.mynet.com.tw/public_html/linebot/quiz-suido/config.php',
        'fallback_name' => '蝛酉敹郎',
        'fallback_icon' => '',
        'features' => [
            'quiz' => true,
        ],
        'quiz_subjects' => [
            'history' => '甇瑕', 'geography' => '啁', 'civics' => '祆', 'chinese' => '', 'english' => '梯', 'math' => '詨飛', 'science' => '芰',
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

// ... (Rest of the logic from index.php) ...
// (Since reading the whole file might be truncated or long, I will use a more precise sed or patch if possible, 
// but given the requirement to be robust, I will try to read the whole file first to ensure I have it all.)
?>
