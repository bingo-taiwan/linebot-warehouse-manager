<?php
require_once __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$dbConfig = $config['db'];

$userId = 'U70a305bb818ace49a521457b3b28802a';
$role = 'ADMIN_OFFICE';
$richMenuId = $config['roles'][$role]['rich_menu'];

// 1. Database
try {
    $dsn = "mysql:host={$dbConfig['mysql']['host']};dbname={$dbConfig['mysql']['database']};charset={$dbConfig['mysql']['charset']}";
    $pdo = new PDO($dsn, $dbConfig['mysql']['username'], $dbConfig['mysql']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("INSERT INTO users (line_user_id, role, is_active, name) VALUES (?, ?, 1, 'New User') ON DUPLICATE KEY UPDATE role = ?, is_active = 1");
    $stmt->execute([$userId, $role, $role]);
    echo "DB Updated.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}

// 2. Link Rich Menu
$channelAccessToken = $config['line']['access_token'];
$url = "https://api.line.me/v2/bot/user/{$userId}/richmenu/{$richMenuId}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$channelAccessToken}",
    "Content-Length: 0"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Link Rich Menu Result: HTTP $httpCode\n$result\n";

