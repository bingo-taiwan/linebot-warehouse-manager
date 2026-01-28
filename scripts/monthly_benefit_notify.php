<?php
/**
 * Cron Job: 每月福利品自選通知
 * 執行頻率：建議每日中午 12:00 執行一次，腳本內會判斷日期
 */

require_once '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';
require_once '/home/lt4.mynet.com.tw/linebot_core/LineBot.php';
require_once '/home/lt4.mynet.com.tw/linebot_core/FlexBuilder.php';

$config = require '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';
$db = $config['db']['mysql'];

// 1. 判斷日期：是否為該月倒數第 2 天
$today = (int)date('j');
$lastDay = (int)date('t');
$targetDay = $lastDay - 2;

echo "Today: $today, Last Day: $lastDay, Target Day: $targetDay\n";

if ($today !== $targetDay && (!isset($argv[1]) || $argv[1] !== 'force')) {
    die("Not the notification day. Skip.\n");
}

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    
    // 2. 獲取所有啟用的員工
    $stmt = $pdo->query("SELECT line_user_id, name FROM users WHERE is_active = 1");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        die("No active users found.\n");
    }

    $lineBot = new LineBot($config['line']);
    $nextMonth = date('n', strtotime('first day of next month'));
    $liffUrl = 'https://liff.line.me/2008988832-TPY6jyIR';

    // 3. 建立 Flex Message
    $body = FlexBuilder::vbox([
        FlexBuilder::text("📢 福利品選購通知", ['weight' => 'bold', 'size' => 'xl', 'color' => '#00B900']),
        FlexBuilder::separator(['margin' => 'md']),
        FlexBuilder::text("親愛的同仁您好：\n{$nextMonth} 月份的福利品自選已開放！", ['wrap' => true, 'margin' => 'md']),
        FlexBuilder::text("請於月底前完成選購，額度為 10,000 元。", ['size' => 'sm', 'color' => '#666666', 'margin' => 'sm']),
        FlexBuilder::button(
            "立即前往選擇",
            ['type' => 'uri', 'uri' => $liffUrl],
            'primary'
        )
    ], ['spacing' => 'md']);
    
    $bubble = FlexBuilder::bubble($body);

    // 4. 群發
    foreach ($users as $user) {
        echo "Sending to: {$user['name']} ({$user['line_user_id']})...\n";
        $lineBot->push($user['line_user_id'], [
            ['type' => 'flex', 'altText' => "{$nextMonth}月份福利品自選開始囉！", 'contents' => $bubble]
        ]);
    }

    echo "Done. Total " . count($users) . " users notified.\n";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}