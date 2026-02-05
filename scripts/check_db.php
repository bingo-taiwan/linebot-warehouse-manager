<?php
require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "--- Products ---
";
    $stmt = $pdo->query("SELECT id, name, category FROM products");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "[{$row['id']}] {$row['category']} - {$row['name']}
";
    }

    echo "
--- Stocks Summary ---
";
    $stmt = $pdo->query("SELECT warehouse_id, COUNT(*) as count, SUM(case_count) as cases, SUM(unit_count) as units FROM stocks GROUP BY warehouse_id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Warehouse: {$row['warehouse_id']}, Items: {$row['count']}, Total Cases: {$row['cases']}, Total Units: {$row['units']}
";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "
";
}
