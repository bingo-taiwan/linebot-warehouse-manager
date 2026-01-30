<?php
/**
 * Reset Data Script - Clears products and stocks tables
 */
$config = require __DIR__ . '/config.php';
$dbConfig = $config['db'];

try {
    if ($dbConfig['driver'] === 'sqlite') {
        $pdo = new PDO("sqlite:" . $dbConfig['sqlite']['path']);
    } else {
        $dsn = "mysql:host={$dbConfig['mysql']['host']};dbname={$dbConfig['mysql']['database']};charset={$dbConfig['mysql']['charset']}";
        $pdo = new PDO($dsn, $dbConfig['mysql']['username'], $dbConfig['mysql']['password']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Resetting data...\n";

    // 
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE stocks");
    $pdo->exec("TRUNCATE TABLE products");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "Success: Data cleared. Ready to restart.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

