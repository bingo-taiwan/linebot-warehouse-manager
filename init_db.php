<?php
/**
 * Database Initialization Script
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

    echo "Initializing database...\n";

    // 1. 產品表
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(50), 
        spec VARCHAR(255),    
        unit_per_case INT DEFAULT 1,
        price_retail DECIMAL(10,2),
        price_member DECIMAL(10,2),
        image_url VARCHAR(555),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. 庫存表
    $pdo->exec("CREATE TABLE IF NOT EXISTS stocks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        warehouse_id VARCHAR(50) NOT NULL,
        product_id INT NOT NULL,
        case_count INT DEFAULT 0,
        unit_count INT DEFAULT 0,
        expiry_date DATE,
        production_date DATE,
        is_expired TINYINT(1) DEFAULT 0,
        note TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // 3. 使用者與角色表
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        line_user_id VARCHAR(100) PRIMARY KEY,
        name VARCHAR(100),
        role VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. 訂單表
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        order_type VARCHAR(50) NOT NULL,
        requester_id VARCHAR(100) NOT NULL,
        items_json TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'PENDING',
        ship_date DATE,
        receive_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (requester_id) REFERENCES users(line_user_id)
    )");

    echo "Success: Database tables created.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
