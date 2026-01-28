<?php
/**
 * Setup Rich Menu for ADMIN_OFFICE
 */

require_once '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';
$config = require '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';

$userId = 'U2d6b2ccc73c20effa2310a93a00a9b14'; // 行政倉管
$liffRestock = 'https://liff.line.me/2008988832-PuJ7aR9I';
$liffBenefit = 'https://liff.line.me/2008988832-TPY6jyIR';

// 定義選單結構
$menuData = [
    'size' => ['width' => 2500, 'height' => 1686],
    'selected' => true,
    'name' => 'ADMIN_OFFICE_Menu_V1',
    'chatBarText' => '行政功能',
    'areas' => [
        ['bounds' => ['x' => 0, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock', 'label' => '查詢效期品']],
        ['bounds' => ['x' => 1250, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=TAIPEI', 'label' => '台北庫存']],
        ['bounds' => ['x' => 0, 'y' => 843, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffRestock, 'label' => '訂補大園貨']],
        ['bounds' => ['x' => 1250, 'y' => 843, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffBenefit, 'label' => '福利品自選']],
    ]
];

// Helper
function callLine($endpoint, $accessToken, $data = null, $contentType = 'application/json') {
    $ch = curl_init('https://api.line.me/v2/bot/' . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: ' . $contentType,
        'Authorization: Bearer ' . $accessToken
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

// 1. 建立選單
echo "Creating Menu...\n";
$res = callLine('richmenu', $config['line']['access_token'], json_encode($menuData));
if (!isset($res['richMenuId'])) die("Failed: " . print_r($res, true));
$menuId = $res['richMenuId'];
echo "Menu ID: $menuId\n";

// 2. 上傳圖片
echo "Uploading Image...\n";
$imagePath = '/tmp/rich_menu_office.png';
$imageData = file_get_contents($imagePath);
$resImg = callLine("richmenu/{$menuId}/content", $config['line']['access_token'], $imageData, 'image/png');
print_r($resImg);

// 3. 綁定用戶
echo "Linking User...\n";
$resLink = callLine("user/{$userId}/richmenu/{$menuId}", $config['line']['access_token']);
print_r($resLink);

echo "Done.\n";

