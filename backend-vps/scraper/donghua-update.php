<?php
$pdo = new PDO('mysql:host=mysql;dbname=streamify_db;charset=utf8mb4', 'streamify_user', 'rimbamobile2');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$baseUrl = "https://anichin.cafe";
$ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36";
$checkPages = 25;

$ins = $pdo->prepare("INSERT IGNORE INTO content (slug, title, type, poster_url, status, rating, genres, created_at, updated_at) VALUES (?, ?, 'donghua', ?, 'ongoing', 0, '[]', NOW(), NOW())");
$insEp = $pdo->prepare("INSERT IGNORE INTO episodes (content_id, episode_number, title, source_url, created_at) VALUES (?, ?, ?, ?, NOW())");

function fetchUrl($url, $ua) {
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: $ua\r\n", 'follow_location' => 1]]);
    return @file_get_contents($url, false, $ctx);
}

$newContent = 0;
$newEps = 0;

for ($page = 1; $page <= $checkPages; $page++) {
    $url = $page === 1 ? "$baseUrl/seri/?status=&type=&order=update" : "$baseUrl/seri/?page=$page&status=&type=&order=update";
    $html = fetchUrl($url, $ua);
    if (!$html) { echo "ERROR page $page\n"; sleep(2); continue; }

    preg_match_all('#<article class="bs".*?</article>#s', $html, $cards);
    if (empty($cards[0])) { preg_match_all('#href="(https://anichin\.cafe/seri/[^"]+)"[^>]*title="([^"]+)"#', $html, $links);
        foreach ($links[1] as $j => $sUrl) {
            $slug = basename(rtrim($sUrl, '/'));
            $title = html_entity_decode(trim($links[2][$j]), ENT_QUOTES, 'UTF-8');
            if (!$title || !$slug) continue;
            $existing = $pdo->query("SELECT id, type FROM content WHERE slug=" . $pdo->quote($slug) . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $useSlug = $slug;
            if ($existing && $existing['type'] !== 'donghua') $useSlug = $slug . '-donghua';
            $ins->execute([$useSlug, $title, '']);
            if ($ins->rowCount() > 0) { $newContent++; echo "NEW: $title\n"; }
        }
        echo "Page $page (alt) done - " . count($links[1]) . " found\n"; sleep(1); continue;
    }
    foreach ($cards[0] as $card) {
        if (!preg_match('#href="https://anichin\.cafe/seri/([^/"?]+)/?\"#', $card, $sm)) continue;
        $slug = $sm[1];
        if (in_array($slug, ['anime','ongoing','completed','drop','bookmark','schedule'])) continue;

        $title = '';
        if (preg_match('#title="([^"]+)"#', $card, $tm)) $title = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');
        if (!$title) continue;

        $poster = '';
        if (preg_match('#<img[^>]+src="([^"]+)"#', $card, $im)) $poster = preg_replace('#\?resize=\d+,\d+#', '', $im[1]);

        $status = 'ongoing';
        if (preg_match('#<span class="epx">([^<]+)<#', $card, $stm)) {
            if (stripos($stm[1], 'complet') !== false) $status = 'completed';
        }

        // Check slug conflict
        $existing = $pdo->query("SELECT id, type FROM content WHERE slug=" . $pdo->quote($slug) . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $useSlug = $slug;
        if ($existing && $existing['type'] !== 'donghua') $useSlug = $slug . '-donghua';

        $ins->execute([$useSlug, $title, $poster]);
        if ($ins->rowCount() > 0) { $newContent++; echo "NEW: $title\n"; }

        // Get content_id
        $row = $pdo->query("SELECT id FROM content WHERE slug=" . $pdo->quote($useSlug) . " AND type='donghua' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;
        $contentId = $row['id'];

        // Fetch latest episodes
        $epHtml = fetchUrl("$baseUrl/seri/$slug/", $ua);
        if (!$epHtml) continue;

        preg_match_all('#<li[^>]*data-index="\d+"[^>]*>\s*<a href="(/[^"]+)"[^>]*>\s*<div class="epl-num">(\d+)</div>#is', $epHtml, $em);
        foreach ($em[1] as $j => $epUrl) {
            $epNum = (int)$em[2][$j];
            $insEp->execute([$contentId, $epNum, "Episode $epNum", "https://anichin.cafe$epUrl"]);
            if ($insEp->rowCount() > 0) $newEps++;
        }
        sleep(1);
    }
    echo "Page $page done\n";
    flush();
    sleep(1);
}

echo "DONE! New content: $newContent | New eps: $newEps\n";
