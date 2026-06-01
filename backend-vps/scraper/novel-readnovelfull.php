<?php
// readnovelfull scraper - primary novel source (clean HTML, readable text)
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$BASE = 'https://readnovelfull.com';
$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0';

function rnf_fetch($url, $ajax = false) {
    global $UA;
    $h = "User-Agent: $UA\r\nReferer: https://readnovelfull.com/\r\n";
    if ($ajax) $h .= "X-Requested-With: XMLHttpRequest\r\n";
    $ctx = stream_context_create(['http' => ['header' => $h, 'timeout' => 20], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    return @file_get_contents($url, false, $ctx);
}

$mode = $argv[1] ?? 'popular';
$pages = (int)($argv[2] ?? 3);
$startPage = (int)($argv[3] ?? 1);

$listPath = $mode === 'latest' ? 'latest-release-novel' : ($mode === 'completed' ? 'completed-novel' : 'most-popular-novel');
$isGenre = strpos($mode, 'genre:') === 0;
$genreName = $isGenre ? substr($mode, 6) : '';

$added = 0;
for ($p = $startPage; $p < $startPage + $pages; $p++) {
    $listUrl = $isGenre ? "$BASE/genres/$genreName?page=$p" : "$BASE/novel-list/$listPath?page=$p";
    $html = rnf_fetch($listUrl);
    if (!$html) continue;

    // Novel detail links: /{slug}.html
    preg_match_all('#href="(/[a-z0-9][a-z0-9-]+\.html)"#i', $html, $m);
    $slugs = array_values(array_unique($m[1]));

    foreach ($slugs as $path) {
        $slug = trim($path, '/');
        $slug = preg_replace('/\.html$/', '', $slug);

        // Skip if exists
        $ex = $pdo->prepare("SELECT id FROM novels WHERE slug=?");
        $ex->execute([$slug]);
        if ($ex->fetch()) continue;

        $d = rnf_fetch("$BASE$path");
        if (!$d) { usleep(300000); continue; }

        preg_match('#<h3 class="title" itemprop="name">([^<]+)#i', $d, $t);
        $title = trim($t[1] ?? $slug);
        preg_match('#data-novel-id="(\d+)"#', $d, $nid);
        $novelId = $nid[1] ?? '';
        preg_match('#<img[^>]+itemprop="image"[^>]+src="([^"]+)"#i', $d, $im);
        if (!$im) preg_match('#<div class="book"[^>]*>\s*<img[^>]+src="([^"]+)"#i', $d, $im);
        $poster = $im[1] ?? '';
        if ($poster && strpos($poster, 'http') !== 0) $poster = $BASE . $poster;
        preg_match('#class="desc-text"[^>]*>(.*?)</div>#si', $d, $ds);
        $desc = trim(strip_tags($ds[1] ?? ''));
        preg_match('#<h3>Genre[^<]*</h3>(.*?)</li>#si', $d, $gl); preg_match_all('#>([^<]+)</a>#', $gl[1] ?? '', $g);
        $genres = json_encode(array_values(array_slice(array_map('trim', $g[1] ?? []), 0, 8)));
        preg_match('#<a[^>]+href="/status/[^"]*"[^>]*>([^<]+)#i', $d, $st);
        $status = stripos($st[1] ?? '', 'complet') !== false ? 'completed' : 'ongoing';

        // Chapter count via ajax
        $chCount = 0; $firstChapters = [];
        if ($novelId) {
            $arch = rnf_fetch("$BASE/ajax/chapter-archive?novelId=$novelId", true);
            preg_match_all('#href="(/[^"]+)"[^>]*>#i', $arch ?? '', $cm);
            $chUrls = $cm[1] ?? [];
            $chCount = count($chUrls);
            $firstChapters = array_slice($chUrls, 0, 0); // metadata only for now
        }

        try {
            $pdo->prepare("INSERT INTO novels (slug, title, description, poster_url, status, source, source_url, total_chapters, latest_chapter, genres) VALUES (?,?,?,?,?,'readnovelfull',?,?,?,?)")
                ->execute([$slug, $title, $desc, $poster, $status, "$BASE$path", $chCount, $chCount, $genres]);
            $nid2 = $pdo->lastInsertId();
            // Store chapter list (metadata, content fetched on-demand)
            if ($novelId && isset($chUrls)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO novel_chapters (novel_id, chapter_number, title, source_url) VALUES (?,?,?,?)");
                $n = 1;
                foreach ($chUrls as $cu) {
                    $ins->execute([$nid2, $n, "Chapter $n", "$BASE$cu"]);
                    $n++;
                }
            }
            $added++;
            if ($added % 5 == 0) echo "  [$added] $title ($chCount ch)\n";
        } catch (Exception $e) {}
        usleep(400000);
    }
}
echo "Done! Added: $added\n";
