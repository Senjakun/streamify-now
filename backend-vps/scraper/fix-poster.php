<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

// Ambil semua anime yang poster kosong
$stmt = $pdo->query("SELECT id, title, slug FROM content WHERE type='anime' AND (poster_url IS NULL OR poster_url='') "); // Test 10 dulu
$animes = $stmt->fetchAll();

echo "Total anime tanpa poster: " . count($animes) . "\n";

foreach ($animes as $i => $anime) {
    // Clean title: hapus "Sub Indo", "Subtitle Indonesia", dll
    $title = preg_replace('/\s*(?:Sub\s*Indo|Subtitle\s*Indonesia|Season\s*\d+).*$/i', '', $anime['title']);
    $title = trim($title);
    
    $url = "https://api.jikan.moe/v4/anime?q=" . urlencode($title) . "&limit=1";
    $ctx = stream_context_create(['http'=>['timeout'=>15]]);
    $response = @file_get_contents($url, false, $ctx);
    
    if (!$response) {
        echo "[".($i+1)."] FAILED FETCH: {$anime['title']}\n";
        sleep(1);
        continue;
    }
    
    $data = json_decode($response, true);
    $poster = $data['data'][0]['images']['jpg']['image_url'] ?? '';
    
    if ($poster) {
        $pdo->prepare("UPDATE content SET poster_url=? WHERE id=?")->execute([$poster, $anime['id']]);
        echo "[".($i+1)."] OK: {$anime['title']} -> " . basename($poster) . "\n";
    } else {
        echo "[".($i+1)."] NO POSTER FOUND: {$anime['title']}\n";
    }
    
    sleep(2); // Rate limit Jikan API
}
echo "SELESAI!\n";
