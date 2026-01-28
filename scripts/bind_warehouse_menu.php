<?php
/**
 * Bind ADMIN_WAREHOUSE menu to specific users
 */
require_once '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';
$config = require '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';

$users = [
    'Ud73b84a2f6219421f13c59202121c13f',
    'Uc7e3c9f4150a2e682af1fb98badf1b31'
];
$menuId = 'richmenu-71952822eda56c6013d9e77a5dffa8df'; // V4 Menu

foreach ($users as $userId) {
    echo "Linking user {$userId}...\n";
    $cmd = "curl -X POST https://api.line.me/v2/bot/user/{$userId}/richmenu/{$menuId} \
        -H 'Authorization: Bearer {$config['line']['access_token']}'";
    system($cmd);
    echo "\n";
}
echo "Done.\n";

