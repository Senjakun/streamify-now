<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

$limit = (int)($argv[1] ?? 50);
$stmt = $pdo->prepare("SELECT id, title, source_url FROM content WHERE type='anime' AND (rating=0 OR description IS NULL OR description='') AND source_url IS NOT NULL AND source_url!='' ORDER BY id LIMIT ?");
$stmt->execute([$limit]);
$animes = $stmt->fetchAll();
echo count($animes) . " anime to scrape detail\n";

$updated = 0;
foreach ($animes as $anime) {
    $url = $anime['source_url'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', CURLOPT_SSL_VERIFYPEER=>false]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$html || $code >= 400) { echo "  SKIP {$anime['title']}: HTTP $code\n"; sleep(1); continue; }

    // Synopsis - otakudesu uses <div class="sinopc"> or <div class="fotoanime">...sinopsisnya
    $desc = '';
    if (preg_match('#<div[^>]*class="[^"]*sinop[^"]*"[^>]*>(.*?)</div>#si', $html, $m)) $desc = trim(strip_tags($m[1]));
    elseif (preg_match('#Sinopsis.*?<p>(.*?)</p>#si', $html, $m)) $desc = trim(strip_tags($m[1]));

    // Rating
    $rating = 0;
    if (preg_match('#Skor\s*:?\s*</span>\s*([0-9.]+)#i', $html, $m)) $rating = (float)$m[1];
    elseif (preg_match('#Score.*?([0-9]+\.[0-9]+)#i', $html, $m)) $rating = (float)$m[1];

    // Genres
    $genres = [];
    if (preg_match_all('#<a[^>]+href="[^"]*genre[^"]*"[^>]*>([^<]+)</a>#i', $html, $m)) $genres = array_map('trim', $m[1]);

    // Year
    $year = null;
    if (preg_match('#Tanggal Rilis.*?(\d{4})#i', $html, $m)) $year = (int)$m[1];
    elseif (preg_match('#(\d{4})</a>#', $html, $m)) $year = (int)$m[1];

    $sets = []; $vals = [];
    if ($desc) { $sets[] = "description=?"; $vals[] = $desc; }
    if ($rating > 0) { $sets[] = "rating=?"; $vals[] = $rating; }
    if ($genres) { $sets[] = "genres=?"; $vals[] = json_encode($genres); }
    if ($year) { $sets[] = "year=?"; $vals[] = $year; }

    if ($sets) {
        $vals[] = $anime['id'];
        $pdo->prepare("UPDATE content SET " . implode(',', $sets) . ",updated_at=NOW() WHERE id=?")->execute($vals);
        $updated++;
        if ($updated % 10 == 0) echo "  [$updated] {$anime['title']} r=$rating g=" . count($genres) . "\n";
    }
    usleep(800000);
}
echo "Done! Updated: $updated\n";
