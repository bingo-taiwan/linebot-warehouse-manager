<?php
require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$dbConfig = $config['db'];

try {
    $dsn = "mysql:host={$dbConfig['mysql']['host']};dbname={$dbConfig['mysql']['database']};charset={$dbConfig['mysql']['charset']}";
    $pdo = new PDO($dsn, $dbConfig['mysql']['username'], $dbConfig['mysql']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $updates = [
        "UPDATE stocks s JOIN products p ON s.product_id = p.id SET s.expiry_date = '2027-08-17' WHERE p.name LIKE '%泰享瘦咖啡盒裝%' AND s.case_count = 24 AND s.expiry_date IS NULL",
        "UPDATE stocks s JOIN products p ON s.product_id = p.id SET s.expiry_date = '2027-12-04' WHERE p.name LIKE '%泰享瘦咖啡盒裝%' AND s.case_count = 18 AND s.expiry_date IS NULL",
        "UPDATE stocks s JOIN products p ON s.product_id = p.id SET s.expiry_date = '2027-03-27' WHERE p.name LIKE '%靚顏悅色膠囊%' AND p.unit_per_case = 193 AND s.expiry_date IS NULL",
        "UPDATE stocks s JOIN products p ON s.product_id = p.id SET s.expiry_date = '2027-03-27' WHERE p.name LIKE '%靚顏悅色膠囊%' AND p.unit_per_case = 300 AND s.expiry_date IS NULL",
        "UPDATE stocks s JOIN products p ON s.product_id = p.id SET s.expiry_date = '2027-07-27' WHERE p.name LIKE '%肌優骨%' AND s.case_count = 2 AND s.expiry_date IS NULL",
        "UPDATE stocks s JOIN products p ON s.product_id = p.id SET s.expiry_date = '2027-11-19' WHERE p.name LIKE '%泰纖身%' AND s.case_count = 2 AND s.expiry_date IS NULL"
    ];

    foreach ($updates as $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        echo "Executed: $sql\nRows affected: " . $stmt->rowCount() . "\n\n";
    }

    echo "Done.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
