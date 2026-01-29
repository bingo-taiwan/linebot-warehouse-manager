<?php
require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';

$channelAccessToken = $config['line']['access_token'];
$url = "https://api.line.me/v2/bot/richmenu/list";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$channelAccessToken}"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

echo $result;
