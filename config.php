<?php
/**
 * Warehouse Manager LineBot Configuration
 * 
 * Target: @563aggdt
 */

return [
    'bot_id' => 'warehouse',
    // === LINE Messaging API ===
    'line' => [
        'channel_id' => '2008987597',
        'channel_secret' => '94e023a4765f9d9227591dc8da2c7a3a',
        'access_token' => 'iOsTyK8KuXZCkl0Lt2ZHg2DKcfX1JD3xCBaKUTp6pJo/qTg0XGVNEgeCz3m2YgLzirbMx9lqP9U5dAmB9iSfEP6yXLlWcUDCHpCfeAVBxQ56Lzd4BS+8WpTEf+6thCAvt366j8mSxqJTJvQXR0ri4gdB04t89/1O/w1cDnyilFU=',
    ],

    // === Database Settings ===
    // 開發期使用 SQLite，生產環境請切換為 mysql 並填入正確憑證
    'db' => [
        'driver' => 'mysql', // 選項: 'sqlite', 'mysql'
        'sqlite' => [
            'path' => __DIR__ . '/data/warehouse.sqlite',
        ],
        'mysql' => [
            'host' => 'localhost',
            'database' => 'warehouse',
            'username' => 'linebot_wh',
            'password' => 'warehouse_pass_2026',
            'charset' => 'utf8mb4',
        ],
    ],

    // === Role Definitions ===
    'roles' => [
        'ADMIN_WAREHOUSE' => [
            'name' => '大園倉+台北倉倉管',
            'permissions' => ['all_warehouses', 'edit_inventory', 'order_dayuan', 'employee_benefit'],
            'rich_menu' => 'richmenu-xxxxxxxxxxxx1', // TODO: 建立後填入
        ],
        'ADMIN_OFFICE' => [
            'name' => '行政與台北倉倉管',
            'permissions' => ['view_valid_only', 'taipei_warehouse', 'order_dayuan', 'employee_benefit'],
            'rich_menu' => 'richmenu-xxxxxxxxxxxx2',
        ],
        'SALES_LECTURER' => [
            'name' => '業務講師',
            'permissions' => ['employee_benefit_only'],
            'rich_menu' => 'richmenu-xxxxxxxxxxxx3',
        ],
    ],

    // === Warehouse Settings ===
    'warehouses' => [
        'DAYUAN' => '大園倉',
        'TAIPEI' => '台北倉',
    ],

    // === UI/UX Settings ===
    'ui' => [
        'employee_benefit_quota' => 10000, // 員工福利品額度
    ]
];
