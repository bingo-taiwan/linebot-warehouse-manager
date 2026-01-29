<?php
/**
 * Clear Business Data Script
 * 清空業務數據 (訂單、庫存、產品)，保留使用者資料
 */

require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$dbConfig = $config['db'];

try {
    if ($dbConfig['driver'] === 'sqlite') {
        $pdo = new PDO("sqlite:" . $dbConfig['sqlite']['path']);
    } else {
        $dsn = "mysql:host={$dbConfig['mysql']['host']};dbname={$dbConfig['mysql']['database']};charset={$dbConfig['mysql']['charset']}";
        $pdo = new PDO($dsn, $dbConfig['mysql']['username'], $dbConfig['mysql']['password']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "⚠️  警告：即將清空所有 訂單、庫存 與 產品 資料！(使用者資料將保留)\n";
    echo "確認執行？(y/n): ";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    if(trim($line) != 'y'){
        echo "已取消。\n";
        exit;
    }

    // 關閉外鍵檢查 (MySQL)
    if ($dbConfig['driver'] === 'mysql') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    }

    echo "正在清空 orders...\n";
    $pdo->exec("TRUNCATE TABLE orders");

    echo "正在清空 stocks...\n";
    $pdo->exec("TRUNCATE TABLE stocks");

    echo "正在清空 products...\n";
    $pdo->exec("TRUNCATE TABLE products");

    // 開啟外鍵檢查 (MySQL)
    if ($dbConfig['driver'] === 'mysql') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    echo "✅ 資料已清空，使用者資料已保留。\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}

