<?php
/**
 * Initial Data Import Script
 * Parses sokoban.txt inventory data into Database
 */

$config = require __DIR__ . '/config.php';
$dbConfig = $config['db'];

if ($dbConfig['driver'] === 'sqlite') {
    $pdo = new PDO("sqlite:" . $dbConfig['sqlite']['path']);
} else {
    $dsn = "mysql:host={$dbConfig['mysql']['host']};dbname={$dbConfig['mysql']['database']};charset={$dbConfig['mysql']['charset']}";
    $pdo = new PDO($dsn, $dbConfig['mysql']['username'], $dbConfig['mysql']['password']);
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Importing initial inventory data from sokoban.txt...\n";

// 定義盤點資料 (手動從 sokoban.txt 整理，確保精確)
$inventory = [
    [
        'name' => '甲足飽裸包',
        'category' => '產品',
        'spec' => '20g/包',
        'unit_per_case' => 750,
        'prod_date' => '2025-12-30',
        'expiry_date' => '2027-12-30', // 假設兩年
        'cases' => 23,
        'units' => 0
    ],
    [
        'name' => '甲足飽盒裝',
        'category' => '產品',
        'spec' => '25包/盒, 22盒/箱',
        'unit_per_case' => 22, // 以盒計
        'expiry_date' => '2027-09-11',
        'cases' => 18,
        'units' => 0
    ],
    [
        'name' => '泰享瘦盒裝 (過期)',
        'category' => '產品',
        'spec' => '40包/盒, 22盒/箱',
        'unit_per_case' => 22,
        'expiry_date' => '2025-07-20',
        'cases' => 23,
        'units' => 0
    ],
    [
        'name' => '泰享瘦盒裝',
        'category' => '產品',
        'spec' => '40包/盒, 22盒/箱',
        'unit_per_case' => 22,
        'expiry_date' => '2027-08-17',
        'cases' => 24,
        'units' => 0,
        'price_retail' => 2000,
        'price_member' => 2500
    ],
    [
        'name' => '魔幻奇蹟霜100mL',
        'category' => '產品',
        'spec' => '72瓶/箱',
        'unit_per_case' => 72,
        'expiry_date' => '2028-05-12',
        'cases' => 7,
        'units' => 0
    ],
    [
        'name' => '薰衣草噴霧',
        'category' => '產品',
        'spec' => '125瓶/箱',
        'unit_per_case' => 125,
        'prod_date' => '2021-02-24',
        'expiry_date' => '2024-02-24', // 假設三年，已過期
        'cases' => 64,
        'units' => 0
    ],
    [
        'name' => '貓頭鷹外盒',
        'category' => '包材',
        'spec' => '108盒/箱',
        'unit_per_case' => 108,
        'cases' => 9,
        'units' => 0
    ]
];

foreach ($inventory as $item) {
    // 插入產品
    $stmt = $pdo->prepare("INSERT INTO products (name, category, spec, unit_per_case, price_retail, price_member) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $item['name'],
        $item['category'],
        $item['spec'],
        $item['unit_per_case'],
        $item['price_retail'] ?? 0,
        $item['price_member'] ?? 0
    ]);
    $productId = $pdo->lastInsertId();

    // 插入大園倉初始庫存
    $stmt = $pdo->prepare("INSERT INTO stocks (warehouse_id, product_id, case_count, unit_count, expiry_date, production_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'DAYUAN',
        $productId,
        $item['cases'],
        $item['units'],
        $item['expiry_date'] ?? null,
        $item['prod_date'] ?? null
    ]);

    echo "Imported: {" . $item['name'] . "}\n";
}

echo "Success: Initial inventory imported.\n";

