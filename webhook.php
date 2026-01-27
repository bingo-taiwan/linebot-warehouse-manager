<?php
/**
 * LineBot Webhook Entry Point
 */

// 開啟錯誤顯示 (除錯用)
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 設定 Log 檔案
$logFile = __DIR__ . '/data/webhook.log';
$errorLog = __DIR__ . '/data/php_error.log';
ini_set('error_log', $errorLog);

// 記錄原始請求
$content = file_get_contents('php://input');
file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] [START] ' . $content . "\n", FILE_APPEND);

try {
    require_once '/home/lt4.mynet.com.tw/linebot_core/LineBot.php';
    require_once '/home/lt4.mynet.com.tw/linebot_core/Analytics.php';
    require_once '/home/lt4.mynet.com.tw/linebot_core/helpers.php';
    
    $config = require __DIR__ . '/config.php';

    // 1. 初始化
    $lineBot = new LineBot($config['line']);
    $lineBot->setDebug(true, $logFile); // Enable debug logging to the same file
    $analytics = new Analytics($config['bot_id'], __DIR__ . '/data');

    // 2. 解析事件
    $data = json_decode($content, true);
    if (empty($data['events'])) {
        echo 'OK (No events)';
        exit;
    }

    foreach ($data['events'] as $event) {
        // 記錄統計
        $analytics->logWebhook(
            $event['source']['userId'] ?? 'unknown',
            $event['type'],
            ['timestamp' => $event['timestamp']]
        );

        // 載入 MainHandler
        require_once __DIR__ . '/handlers/MainHandler.php';
        $handler = new MainHandler($lineBot, $config);
        $handler->handle($event);
    }

    echo 'OK';

} catch (Throwable $e) {
    // 捕捉所有錯誤並記錄
    file_put_contents($logFile, date('[Y-m-d H:i:s] ERROR: ') . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    http_response_code(500);
    echo 'Error';
}
