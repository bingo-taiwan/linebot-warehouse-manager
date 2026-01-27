<?php
/**
 * Rich Menu Generator Script
 * 
 * Image Requirements:
 * - Format: JPEG or PNG
 * - Size: 
 *   - Large (Menu A): 2500x1686
 *   - Medium (Menu B): 2500x1686 (Same as Large) or 2500x843
 *   - Small (Menu C): 2500x843
 */

require_once __DIR__ . '/../config.php';

// 定義 Rich Menu 結構
$menus = [
    'ADMIN_WAREHOUSE' => [
        'size' => ['width' => 2500, 'height' => 1686],
        'selected' => true,
        'name' => '雙倉倉管選單',
        'chatBarText' => '開啟倉管功能',
        'areas' => [
            ['bounds' => ['x' => 0, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=DAYUAN', 'label' => '大園庫存']],
            ['bounds' => ['x' => 1250, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=TAIPEI', 'label' => '台北庫存']],
            ['bounds' => ['x' => 0, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=add_stock&wh=DAYUAN', 'label' => '新品入庫']],
            ['bounds' => ['x' => 833, 'y' => 843, 'width' => 833, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=order_dayuan', 'label' => '下單大園']],
            ['bounds' => ['x' => 1666, 'y' => 843, 'width' => 834, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=employee_benefit', 'label' => '福利品']],
        ]
    ],
    'ADMIN_OFFICE' => [
        'size' => ['width' => 2500, 'height' => 1686],
        'selected' => true,
        'name' => '行政倉管選單',
        'chatBarText' => '開啟行政功能',
        'areas' => [
            ['bounds' => ['x' => 0, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_valid_stock', 'label' => '查詢效期品']],
            ['bounds' => ['x' => 1250, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=view_stock&wh=TAIPEI', 'label' => '台北庫存']],
            ['bounds' => ['x' => 0, 'y' => 843, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=order_dayuan', 'label' => '訂補大園貨']],
            ['bounds' => ['x' => 1250, 'y' => 843, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=employee_benefit', 'label' => '福利品自選']],
        ]
    ],
    'SALES_LECTURER' => [
        'size' => ['width' => 2500, 'height' => 843],
        'selected' => true,
        'name' => '業務講師選單',
        'chatBarText' => '開啟福利品',
        'areas' => [
            ['bounds' => ['x' => 0, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=my_benefit_status', 'label' => '我的福利品']],
            ['bounds' => ['x' => 1250, 'y' => 0, 'width' => 1250, 'height' => 843], 'action' => ['type' => 'postback', 'data' => 'action=employee_benefit', 'label' => '自選購物']],
        ]
    ]
];

// 輸出 JSON 供檢查
echo json_encode($menus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// TODO: 實際呼叫 LINE API 建立 Menu 並上傳圖片
// $lineBot->createRichMenu(...);
// $lineBot->uploadRichMenuImage(...);
