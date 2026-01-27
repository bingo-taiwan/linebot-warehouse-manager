<?php
$file = '/home/lt4.mynet.com.tw/public_html/linebot/admin/index.php';
$content = file_get_contents($file);

$newBot = '    "warehouse" => [
        "path" => "/home/lt4.mynet.com.tw/public_html/linebot/warehouse",
        "webhook" => "https://lt4.mynet.com.tw/linebot/warehouse/webhook.php",
        "config" => "/home/lt4.mynet.com.tw/public_html/linebot/warehouse/config.php",
        "fallback_name" => "倉管小幫手",
        "fallback_icon" => "📦",
        "features" => [
            "inventory" => true,
        ],
        "quiz_subjects" => []
    ],
';

// Insert into $bots array
if (strpos($content, '"warehouse" =>') === false) {
    $content = str_replace('"quiz-suido" => [', $newBot . '    "quiz-suido" => [', $content);
}

// Add to feature names map
$featureMapSearch = "'line_quota' => ' LINE 憿漲',";
$featureMapAdd = "\n                                    'inventory' => '📦 倉儲管理',";

if (strpos($content, "'inventory' =>") === false) {
    $content = str_replace($featureMapSearch, $featureMapSearch . $featureMapAdd, $content);
}

file_put_contents($file, $content);
echo "Dashboard updated successfully.\n";

