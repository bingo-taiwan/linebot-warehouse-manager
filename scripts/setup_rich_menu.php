<?php
/**
 * Rich Menu Creator & Linker
 */

require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';

function createRichMenu($config, $menuData) {
    $ch = curl_init('https://api.line.me/v2/bot/richmenu');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['line']['access_token']
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($menuData));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

// 建立 ADMIN_WAREHOUSE 選單
$adminMenu = [
    'size' => ['width' => 2500, 'height' => 1686],
    'selected' => true,
    'name' => 'Warehouse_Admin_Menu',
    'chatBarText' => '選單',
    'areas' => [
        ['bounds' => ['x' => 0, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=DAYUAN', 'label' => '大園庫存']],
        ['bounds' => ['x' => 1250, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=TAIPEI', 'label' => '台北庫存']],
        ['bounds' => ['x' => 0, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=add_stock&wh=DAYUAN', 'label' => '新品入庫']],
        ['bounds' => ['x' => 833, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=order_dayuan', 'label' => '下單大園']],
        ['bounds' => ['x' => 1666, 'y' => 843, 'width' => 834, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=employee_benefit', 'label' => '福利品']],
    ]
];

echo "Creating Rich Menu for ADMIN_WAREHOUSE...\n";
$result = createRichMenu($config, $adminMenu);

if (isset($result['response']['richMenuId'])) {
    $menuId = $result['response']['richMenuId'];
    echo "Success! RichMenuId: " . $menuId . "\n";
    echo "Next steps:\n";
    echo "1. Upload image: curl -v -X POST https://api-data.line.me/v2/bot/richmenu/{$menuId}/content -H 'Authorization: Bearer {$config['line']['access_token']}' -H 'Content-Type: image/png' --data-binary @path_to_image.png\n";
    echo "2. Link to user: curl -v -X POST https://api.line.me/v2/bot/user/U004f8cad542e37c7834a3920e60d1077/richmenu/{$menuId} -H 'Authorization: Bearer {$config['line']['access_token']}'\n";
} else {
    echo "Failed to create rich menu.\n";
    print_r($result);
}

