<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

// Ambil semua anime
$animes = $pdo->query("SELECT id, slug FROM content WHERE type='anime'")->fetchAll();
echo "Total anime: " . count($animes) . "\n";

$updated = 0;
$failed = 0;

foreach ($animes as $idx => $anime) {
    $html = file_get_contents("https://server1.playall.me/episode/{$anime['slug']}/");
    if (!$html) { echo "GAGAL: {$anime['slug']}\n"; $failed++; continue; }
    
    // Ambil semua link play dengan slug yang benar
    preg_match_all('#href="https://server1\.playall\.me/play/([^/]+)/"#', $html, $m);
    $slugs = array_reverse($m[1]); // episode 1 dulu
    
    if (empty($slugs)) { echo "NO EPS: {$anime['slug']}\n"; $failed++; continue; }
    
    // Hapus episode lama
    $pdo->prepare("DELETE FROM episodes WHERE content_id=?")->execute([$anime['id']]);
    
    // Insert episode baru dengan slug yang benar
    foreach ($slugs as $i => $epSlug) {
        preg_match('/episode[- ](\d+)/i', $epSlug, $num);
        $epNum = intval($num[1] ?? ($i+1));
        $pdo->prepare("INSERT IGNORE INTO episodes (content_id, episode_number, title, source_url) VALUES (?,?,?,?)")
            ->execute([$anime['id'], $epNum, "Episode $epNum", "https://server1.playall.me/play/$epSlug/"]);
    }
    
    echo "[".($idx+1)."/".count($animes)."] OK: {$anime['slug']} - " . count($slugs) . " eps\n";
    $updated++;
    sleep(1);
}

echo "\nSelesai! Updated: $updated, Failed: $failed\n";
