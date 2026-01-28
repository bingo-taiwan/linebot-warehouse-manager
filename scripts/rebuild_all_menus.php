<?php
/**
 * Global Rich Menu Rebuild (V6)
 * Points 'Welfare Product' to the Dashboard LIFF for all roles.
 */

require_once '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';
$config = require '/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php';

$token = $config['line']['access_token'];

// LIFF URLs
$liffAdd = 'https://liff.line.me/2008988832-qQ0xjwL8';
$liffRestock = 'https://liff.line.me/2008988832-PuJ7aR9I';
$liffBenefitDashboard = 'https://liff.line.me/2008988832-4ZdyYI38';
$liffPR = 'https://liff.line.me/2008988832-Xbi6ryWE';

// Helper
function callLine($endpoint, $accessToken, $data = null, $method = 'POST') {
    $ch = curl_init('https://api.line.me/v2/bot/' . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') curl_setopt($ch, CURLOPT_POST, true);
    if ($method === 'DELETE') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

// ---------------------------------------------------------
// 1. 建立標準倉管選單 (V6)
// ---------------------------------------------------------
echo "Creating Admin Menu V6...\n";
$adminMenu = [
    'size' => ['width' => 2500, 'height' => 1686],
    'selected' => true,
    'name' => 'Warehouse_Admin_Menu_V6',
    'chatBarText' => '選單',
    'areas' => [
        ['bounds' => ['x' => 0, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=DAYUAN', 'label' => '大園庫存']],
        ['bounds' => ['x' => 1250, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=TAIPEI', 'label' => '台北庫存']],
        ['bounds' => ['x' => 0, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffAdd, 'label' => '新品入庫']],
        ['bounds' => ['x' => 833, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffRestock, 'label' => '下單大園']],
        ['bounds' => ['x' => 1666, 'y' => 843, 'width' => 834, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffBenefitDashboard, 'label' => '福利品']],
    ]
];
$resAdmin = callLine('richmenu', $token, $adminMenu);
$adminMenuId = $resAdmin['richMenuId'];
echo "Admin Menu ID: $adminMenuId\n";

// ---------------------------------------------------------
// 2. 建立 VIP 倉管選單 (V6)
// ---------------------------------------------------------
echo "Creating VIP Menu V6...\n";
$vipMenu = [
    'size' => ['width' => 2500, 'height' => 1686],
    'selected' => true,
    'name' => 'Warehouse_VIP_Menu_V6',
    'chatBarText' => 'VIP選單',
    'areas' => [
        ['bounds' => ['x' => 0, 'y' => 0, 'width' => 833, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=DAYUAN', 'label' => '大園庫存']],
        ['bounds' => ['x' => 833, 'y' => 0, 'width' => 833, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=TAIPEI', 'label' => '台北庫存']],
        ['bounds' => ['x' => 1666, 'y' => 0, 'width' => 834, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffPR, 'label' => '公關取貨']],
        ['bounds' => ['x' => 0, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffAdd, 'label' => '新品入庫']],
        ['bounds' => ['x' => 833, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffRestock, 'label' => '下單大園']],
        ['bounds' => ['x' => 1666, 'y' => 843, 'width' => 834, 'height' => 843], 'action' => ['type' => 'uri', 'uri' => $liffBenefitDashboard, 'label' => '福利品']],
    ]
];
$resVip = callLine('richmenu', $token, $vipMenu);
$vipMenuId = $resVip['richMenuId'];
echo "VIP Menu ID: $vipMenuId\n";

// ---------------------------------------------------------
// 3. 上傳圖片並綁定
// ---------------------------------------------------------
// Admin 圖片 (重複使用之前產生的)
system("curl -X POST https://api-data.line.me/v2/bot/richmenu/{$adminMenuId}/content -H 'Authorization: Bearer {$token}' -H 'Content-Type: image/png' --data-binary @/tmp/rich_menu_admin.png");
// VIP 圖片
system("curl -X POST https://api-data.line.me/v2/bot/richmenu/{$vipMenuId}/content -H 'Authorization: Bearer {$token}' -H 'Content-Type: image/png' --data-binary @/tmp/rich_menu_vip.png");

// 綁定用戶
$standardUsers = ['U004f8cad542e37c7834a3920e60d1077', 'Uc7e3c9f4150a2e682af1fb98badf1b31']; // 您與另一位倉管
foreach ($standardUsers as $uid) {
    callLine("user/{$uid}/richmenu/{$adminMenuId}", $token);
}
// 綁定 VIP
callLine("user/Ud73b84a2f6219421f13c59202121c13f/richmenu/{$vipMenuId}", $token);

echo "All menus updated and linked.\n";

