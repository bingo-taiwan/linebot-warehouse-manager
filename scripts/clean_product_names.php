<?php
require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$db = $config['db']['mysql'];

try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}", $db['username'], $db['password']);
    
    // 找出所有帶有 (xx入) 的產品
    $stmt = $pdo->query("SELECT id, name FROM products WHERE name REGEXP '\\([0-9]+入\\)'");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $p) {
        $newName = preg_replace('/\s*\(\d+入\)/u', '', $p['name']);
        if ($newName !== $p['name']) {
            echo "Renaming: {$p['name']} -> {$newName}\n";
            $update = $pdo->prepare("UPDATE products SET name = ? WHERE id = ?");
            $update->execute([$newName, $p['id']]);
        }
    }
    echo "Done.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

