<?php
// Animexin donghua scraper: catalog + episodes (source_url=animexin for multi-server embeds).
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$maxPage = (int)($argv[1] ?? 30);
$ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n", 'timeout' => 18], 'ssl' => ['verify_peer' => false]]);

function get($u, $ctx) { return @file_get_contents($u, false, $ctx); }
function slugify($s) { return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($s))), '-'); }

$seriesUrls = [];
$sm = get('https://animexin.dev/anime-sitemap.xml', $ctx);
if ($sm) {
    preg_match_all('#<loc>([^<]+)</loc>#', $sm, $m);
    foreach ($m[1] as $u) {
        if (strpos($u, '-episode-') !== false) continue; // skip episode pages
        if (rtrim($u, '/') === 'https://animexin.dev' || $u === 'https://animexin.dev/anime/') continue;
        $seriesUrls[] = $u;
    }
}
$seriesUrls = array_unique($seriesUrls);
echo count($seriesUrls) . " series found\n";

$nNew = 0; $nEp = 0;
foreach ($seriesUrls as $su) {
    $d = get($su, $ctx);
    if (!$d) { usleep(200000); continue; }
    if (!preg_match('#<h1[^>]*class="entry-title"[^>]*>([^<]+)</h1>#i', $d, $t)) continue;
    $title = trim(html_entity_decode($t[1]));
    $slug = slugify($title);
    preg_match('#<div class="thumb"[^>]*>.*?<img[^>]+src="([^"]+)"#is', $d, $p);
    $poster = $p[1] ?? '';
    preg_match('#<div class="entry-content"[^>]*>(.*?)</div>#is', $d, $syn);
    $desc = trim(strip_tags($syn[1] ?? ''));
    $genres = '[]';
    if (preg_match('#<div[^>]*class="[^"]*genxed[^"]*"[^>]*>(.*?)</div>#is', $d, $gx)) {
        preg_match_all('#<a[^>]*>([^<]+)</a>#i', $gx[1], $g);
        $genres = json_encode(array_values(array_unique(array_map('trim', $g[1] ?? []))));
    }
    $status = preg_match('#"status[^"]*">\s*([A-Za-z]+)#i', $d, $st) && stripos($st[1], 'comp') !== false ? 'completed' : 'ongoing';

    // Upsert content (match existing donghua by title)
    $ex = $pdo->prepare("SELECT id FROM content WHERE type='donghua' AND title=? LIMIT 1");
    $ex->execute([$title]);
    $cid = $ex->fetchColumn();
    if ($cid) {
        $pdo->prepare("UPDATE content SET poster_url=?, description=?, genres=?, status=? WHERE id=?")
            ->execute([$poster, $desc, $genres, $status, $cid]);
    } else {
        try {
            $pdo->prepare("INSERT INTO content (type, slug, title, poster_url, description, genres, status) VALUES ('donghua',?,?,?,?,?,?)")
                ->execute([$slug, $title, $poster, $desc, $genres, $status]);
            $cid = $pdo->lastInsertId();
            $nNew++;
        } catch (Exception $e) { // dup slug
            $pdo->prepare("INSERT INTO content (type, slug, title, poster_url, description, genres, status) VALUES ('donghua',?,?,?,?,?,?)")
                ->execute([$slug . '-dh', $title, $poster, $desc, $genres, $status]);
            $cid = $pdo->lastInsertId();
            $nNew++;
        }
    }

    // Episodes - set source_url to animexin (for multi-server embeds)
    preg_match_all('#href="(https://animexin\.dev/[^"]*-episode-(\d+)-[^"]+)"#i', $d, $e);
    $seen = [];
    $ins = $pdo->prepare("INSERT INTO episodes (content_id, episode_number, title, source_url) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE source_url=VALUES(source_url)");
    for ($i = 0; $i < count($e[1]); $i++) {
        $n = (int)$e[2][$i];
        if (!$n || isset($seen[$n])) continue;
        $seen[$n] = 1;
        $ins->execute([$cid, $n, "Episode $n", $e[1][$i]]);
        if ($ins->rowCount()) $nEp++;
    }
    // Movie/single (no episode list): player is on the series page itself
    if (empty($seen)) {
        $ins->execute([$cid, 1, "Full Movie", $su]);
        if ($ins->rowCount()) $nEp++;
    }
    usleep(250000);
}
echo "Done. New series: $nNew, episodes upserted: $nEp\n";
