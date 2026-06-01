<?php
// Scrape episode baru dari otakudesu langsung
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

$log = [];
$log[] = date('Y-m-d H:i:s') . ' - Start update episodes';

// Ambil anime yang perlu dicek (ongoing dulu)
$animes = $pdo->query("SELECT id, slug FROM content WHERE type='anime' AND status='ongoing' ORDER BY updated_at ASC LIMIT 50")->fetchAll();

foreach ($animes as $anime) {
    $ctx = stream_context_create(['http'=>[
        'timeout'=>15,
        'header'=>"User-Agent: Mozilla/5.0\r\nReferer: https://otakudesu.blog/\r\n"
    ]]);
    $html = @file_get_contents("https://otakudesu.blog/anime/{$anime['slug']}/", false, $ctx);
    if (!$html) {
        $log[] = "FAILED: {$anime['slug']}";
        continue;
    }
    
    preg_match_all('#href="https://otakudesu\.blog/play/([^/]+)/"#', $html, $m);
    $slugs = array_reverse($m[1]);
    
    $inserted = 0;
    foreach ($slugs as $i => $epSlug) {
        preg_match('/episode[- ](\d+)/i', $epSlug, $num);
        $epNum = intval($num[1] ?? ($i+1));
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO episodes (content_id, episode_number, slug, embed_url) VALUES (?,?,?,?)");
        $stmt->execute([$anime['id'], $epNum, $epSlug, "https://otakudesu.blog/play/$epSlug/"]);
        if ($stmt->rowCount() > 0) $inserted++;
    }
    
    $pdo->prepare("UPDATE content SET updated_at=NOW() WHERE id=?")->execute([$anime['id']]);
    if ($inserted > 0) $log[] = "NEW: {$anime['slug']} - $inserted eps";
    sleep(2); // Jangan spam otakudesu
}

$log[] = date('Y-m-d H:i:s') . ' - Done';
file_put_contents('/var/log/update-episodes.log', implode("\n", $log) . "\n", FILE_APPEND);
echo implode("\n", $log) . "\n";
