<?php
/**
 * Update Rich Menu to open LIFF for 'Add Stock'
 */

require_once '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';
$config = require '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';

$menuId = 'richmenu-cf6f66a525c156eeb3dd326dc3dfcd21'; // Current V3 ID to delete
$liffUrlAddStock = 'https://liff.line.me/2008988832-qQ0xjwL8';
$liffUrlBenefit = 'https://liff.line.me/2008988832-TPY6jyIR';
$liffUrlRestock = 'https://liff.line.me/2008988832-PuJ7aR9I';

// 定義新的 ADMIN_WAREHOUSE 選單結構
$updatedMenu = [
    'size' => ['width' => 2500, 'height' => 1686],
    'selected' => true,
    'name' => 'Warehouse_Admin_Menu_V4',
    'chatBarText' => '選單',
    'areas' => [
        ['bounds' => ['x' => 0, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=DAYUAN', 'label' => '大園庫存']],
        ['bounds' => ['x' => 1250, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=TAIPEI', 'label' => '台北庫存']],
        ['bounds' => ['x' => 0, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffUrlAddStock, 'label' => '新品入庫']],
        ['bounds' => ['x' => 833, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffUrlRestock, 'label' => '下單大園']],
        ['bounds' => ['x' => 1666, 'y' => 843, 'width' => 834, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffUrlBenefit, 'label' => '福利品']],
    ]
];

// 1. 建立新選單
function callLine($endpoint, $accessToken, $data) {
    $ch = curl_init('https://api.line.me/v2/bot/' . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

echo "Step 1: Creating new Rich Menu...\n";
$res = callLine('richmenu', $config['line']['access_token'], $updatedMenu);
if (!isset($res['richMenuId'])) {
    die("Failed to create menu: " . print_r($res, true));
}
$newId = $res['richMenuId'];
echo "New ID: $newId\n";

// 2. 上傳舊圖 (暫時用本地那張)
echo "Step 2: Please use CLI to upload image and link user.\n";
echo "Command 1: curl -v -X POST https://api-data.line.me/v2/bot/richmenu/{$newId}/content -H 'Authorization: Bearer {$config['line']['access_token']}' -H 'Content-Type: image/png' --data-binary @/tmp/rich_menu_admin.png\n";
echo "Command 2: curl -v -X POST https://api.line.me/v2/bot/user/U004f8cad542e37c7834a3920e60d1077/richmenu/{$newId} -H 'Authorization: Bearer {$config['line']['access_token']}'\n";
echo "Command 3: curl -v -X DELETE https://api.line.me/v2/bot/richmenu/{$menuId} -H 'Authorization: Bearer {$config['line']['access_token']}'\n";

