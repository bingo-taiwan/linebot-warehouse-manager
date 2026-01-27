<?php
/**
 * LineBot Webhook Entry Point
 */

require_once __DIR__ . '/config.php';
// 暫時還沒有 LineBot SDK，先用原生 PHP 處理

// 1. 取得 POST 資料
$content = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';

// 2. 記錄 Log (方便除錯)
file_put_contents(__DIR__ . '/data/webhook.log', date('[Y-m-d H:i:s] ') . $content . "\n", FILE_APPEND);

// 3. 簡單回應 OK
http_response_code(200);
echo 'OK';

// TODO: 驗證簽名、解析 JSON、分發事件
// $events = json_decode($content, true)['events'];
// foreach ($events as $event) { ... }

