<?php
/**
 * Setup Rich Menu V5 for VIP (Ud73b84a2f6219421f13c59202121c13f)
 */

require_once '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';
$config = require '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';

$userId = 'Ud73b84a2f6219421f13c59202121c13f'; // VIP 用戶

// LIFF URLs
$liffAdd = 'https://liff.line.me/2008988832-qQ0xjwL8';
$liffRestock = 'https://liff.line.me/2008988832-PuJ7aR9I';
$liffBenefit = 'https://liff.line.me/2008988832-TPY6jyIR';
$liffPR = 'https://liff.line.me/2008988832-Xbi6ryWE';

// 定義選單結構 (3x2)
$menuData = [
    'size' => ['width' => 2500, 'height' => 1686],
    'selected' => true,
    'name' => 'Warehouse_VIP_Menu_V5',
    'chatBarText' => 'VIP選單',
    'areas' => [
        // 上排
        ['bounds' => ['x' => 0, 'y' => 0, 'width' => 833, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=DAYUAN', 'label' => '大園庫存']],
        ['bounds' => ['x' => 833, 'y' => 0, 'width' => 833, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=TAIPEI', 'label' => '台北庫存']],
        ['bounds' => ['x' => 1666, 'y' => 0, 'width' => 834, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffPR, 'label' => '公關取貨']],
        // 下排
        ['bounds' => ['x' => 0, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffAdd, 'label' => '新品入庫']],
        ['bounds' => ['x' => 833, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffRestock, 'label' => '下單大園']],
        ['bounds' => ['x' => 1666, 'y' => 843, 'width' => 834, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffBenefit, 'label' => '福利品']],
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

// 2. 上傳圖片 (使用 system curl 以避免 memory limit)
echo "Uploading Image...\n";
$cmd = "curl -v -X POST https://api-data.line.me/v2/bot/richmenu/{$menuId}/content \
    -H 'Authorization: Bearer {$config['line']['access_token']}' \
    -H 'Content-Type: image/png' \
    --data-binary @/tmp/rich_menu_vip.png";
system($cmd);

// 3. 綁定用戶
echo "\nLinking User...\n";
$resLink = callLine("user/{$userId}/richmenu/{$menuId}", $config['line']['access_token']);
print_r($resLink);

echo "Done.\n";

