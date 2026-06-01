<?php
$baseUrl = "https://mangaserver.playall.me";
$ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36";
$checkPages = 5;

$pdo = new PDO('mysql:host=mysql;dbname=streamify_db;charset=utf8mb4', 'streamify_user', 'rimbamobile2');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->prepare("INSERT IGNORE INTO content (slug, title, type, poster_url, rating, status, genres, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'ongoing', '[]', NOW(), NOW())");

$newCount = 0;
for ($page = 1; $page <= $checkPages; $page++) {
    $url = $page > 1 ? "$baseUrl/manga/page/$page/?orderby=update" : "$baseUrl/manga/?orderby=update";
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: $ua\r\n"]]);
    $html = @file_get_contents($url, false, $ctx);
    if (!$html) { echo "ERROR page $page\n"; sleep(2); continue; }

    $processed = [];
    preg_match_all('#/detail/([^/"?\s]+)/?#', $html, $slugMatches, PREG_OFFSET_CAPTURE);
    foreach ($slugMatches[1] as $match) {
        $slug = $match[0]; $offset = $match[1];
        if (in_array($slug, ['list-mode','page','latest','manga','genres'])) continue;
        if (strpos($slug, '?') !== false || strpos($slug, '/') !== false) continue;
        if (strlen($slug) < 3 || in_array($slug, $processed)) continue;
        $processed[] = $slug;

        $c = substr($html, max(0, $offset - 400), 3200);
        $poster = ''; $title = '';
        if (preg_match('#<img[^>]+src="(https://(?:v1\.kiryuu\.to/wp-content|images\.envira-cdn\.com)[^"]+)"[^>]*(?:alt="([^"]*)")?#i', $c, $im)) {
            $poster = $im[1]; $title = html_entity_decode(trim($im[2] ?? ''), ENT_QUOTES, 'UTF-8');
        }
        if (!$title && preg_match('#<img[^>]+alt="([^"]{3,})"#i', $c, $am)) $title = html_entity_decode(trim($am[1]), ENT_QUOTES, 'UTF-8');
        if (!$title || !$poster || stripos($title, 'manga terlengkap') !== false) continue;

        $type = 'manga';
        if (preg_match('#(manhwa|manhua|manga)\.svg#i', $c, $tm)) $type = strtolower($tm[1]);
        $rating = 0.0;
        if (preg_match('#class="numscore"[^>]*>\s*([\d.]+)\s*<#', $c, $rm)) $rating = min((float)$rm[1], 9.9);

        try {
            $affected = $stmt->execute([$slug, $title, $type, $poster, $rating]);
            if ($stmt->rowCount() > 0) { $newCount++; echo "NEW: $title\n"; }
        } catch (Exception $e) {}
    }
    echo "Page $page/$checkPages done\n"; flush();
    sleep(1);
}
echo "DONE! New manga added: $newCount\n";
