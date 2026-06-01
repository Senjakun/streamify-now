<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

$limit = (int)($argv[1] ?? 100);
$stmt = $pdo->prepare("SELECT id, slug, title FROM content WHERE type='donghua' AND (description IS NULL OR description='') ORDER BY id LIMIT ?");
$stmt->execute([$limit]);
$items = $stmt->fetchAll();
echo count($items) . " donghua to scrape detail\n";

$updated = 0;
foreach ($items as $item) {
    $url = "https://anichin.cafe/seri/{$item['slug']}/";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_USERAGENT=>'Mozilla/5.0', CURLOPT_SSL_VERIFYPEER=>false]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$html || $code >= 400) { usleep(500000); continue; }

    // Synopsis
    $desc = '';
    if (preg_match('#<div[^>]*class="[^"]*synop[^"]*"[^>]*>(.*?)</div>#si', $html, $m)) $desc = trim(strip_tags($m[1]));
    elseif (preg_match('#<div[^>]*itemprop="description"[^>]*>(.*?)</div>#si', $html, $m)) $desc = trim(strip_tags($m[1]));
    elseif (preg_match('#<span[^>]*class="[^"]*desc[^"]*"[^>]*>(.*?)</span>#si', $html, $m)) $desc = trim(strip_tags($m[1]));

    // Genres
    $genres = [];
    if (preg_match_all('#<a[^>]+href="[^"]*genre[^"]*"[^>]*>([^<]+)</a>#i', $html, $m)) $genres = array_map('trim', $m[1]);

    // Rating
    $rating = 0;
    if (preg_match('#<span[^>]*itemprop="ratingValue"[^>]*>([0-9.]+)#i', $html, $m)) $rating = (float)$m[1];
    elseif (preg_match('#(\d+\.\d+)</span>\s*</div>\s*<div[^>]*class="[^"]*rating#i', $html, $m)) $rating = (float)$m[1];

    $sets = []; $vals = [];
    if ($desc) { $sets[] = "description=?"; $vals[] = $desc; }
    if ($genres) { $sets[] = "genres=?"; $vals[] = json_encode($genres); }
    if ($rating > 0) { $sets[] = "rating=?"; $vals[] = $rating; }
    $sets[] = "source_url=?"; $vals[] = $url;

    if ($sets) {
        $vals[] = $item['id'];
        $pdo->prepare("UPDATE content SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
        $updated++;
        if ($updated % 20 == 0) echo "  [$updated] {$item['title']}\n";
    }
    usleep(300000);
}
echo "Done! Updated: $updated\n";
