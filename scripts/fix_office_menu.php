<?php
require_once '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';
$config = require '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';

$menuId = 'richmenu-e19794b539c684e099c728587663b41c';
$userId = 'U2d6b2ccc73c20effa2310a93a00a9b14';
$token = $config['line']['access_token'];

echo "Uploading Image via System CURL...\n";
$cmd = "curl -v -X POST https://api-data.line.me/v2/bot/richmenu/{$menuId}/content \
    -H 'Authorization: Bearer {$token}' \
    -H 'Content-Type: image/png' \
    --data-binary @/tmp/rich_menu_office.png";
system($cmd);

echo "\nLinking User...\n";
$cmdLink = "curl -X POST https://api.line.me/v2/bot/user/{$userId}/richmenu/{$menuId} \
    -H 'Authorization: Bearer {$token}'";
system($cmdLink);

echo "\nDone.\n";

