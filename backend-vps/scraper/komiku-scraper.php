<?php
// Komiku manga scraper - replaces Kiryuu (images are server-rendered, easy to scrape)
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

function km_fetch($url) {
    $ctx = stream_context_create([
        'http' => ['header' => "Referer: https://komiku.org/\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n", 'timeout' => 20],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    return @file_get_contents($url, false, $ctx);
}

function km_slugFromUrl($url) {
    $parts = array_values(array_filter(explode('/', $url)));
    // .../manga/{slug}/ -> slug
    $i = array_search('manga', $parts);
    if ($i !== false && isset($parts[$i+1])) return $parts[$i+1];
    return end($parts);
}

$mode = $argv[1] ?? 'latest';
$pages = (int)($argv[2] ?? 3);

if ($mode === 'latest' || $mode === 'hot') {
    $added = 0;
    $startPage = (int)($argv[3] ?? 1);
    for ($p = $startPage; $p < $startPage + $pages; $p++) {
        if ($mode === 'hot') {
            $url = $p === 1 ? "https://api.komiku.org/other/hot/" : "https://api.komiku.org/other/hot/page/$p/";
        } else {
            $url = $p === 1 ? "https://api.komiku.org/manga/" : "https://api.komiku.org/manga/page/$p/";
        }
        $html = km_fetch($url);
        if (!$html) continue;

        // Each card: .bge with link to /manga/{slug}/
        preg_match_all('#<div class="bge">(.*?)</div>\s*</div>#si', $html, $cards);
        // Fallback: extract manga links + titles
        preg_match_all('#href="(https?://komiku\.org/manga/([^/"]+)/)"#i', $html, $m, PREG_SET_ORDER);
        $seen = [];
        foreach ($m as $match) {
            $slug = $match[2];
            if (isset($seen[$slug])) continue;
            $seen[$slug] = true;
            // Scrape detail
            $detailHtml = km_fetch("https://komiku.org/manga/$slug/");
            if (!$detailHtml) { usleep(300000); continue; }

            preg_match('#<h1[^>]*>(.*?)</h1>#si', $detailHtml, $t);
            $title = trim(preg_replace('/^Komik\s+/i','',strip_tags($t[1] ?? $slug)));
            preg_match('#<div class="ims"[^>]*>\s*<img[^>]+src="([^"]+)"#i', $detailHtml, $im);
            if (!$im) preg_match('#<img[^>]+itemprop="image"[^>]+src="([^"]+)"#i', $detailHtml, $im);
            $poster = $im[1] ?? '';
            preg_match('#itemprop="description"[^>]*>(.*?)</#si', $detailHtml, $syn);
            if (!$syn) preg_match('#Sinopsis[^<]*</[^>]+>\s*<p[^>]*>(.*?)</p>#si', $detailHtml, $syn);
            $desc = trim(strip_tags($syn[1] ?? ''));
            // Genres: only from content after header nav (skip site menu genres)
            $body = $detailHtml;
            $hpos = stripos($body, '</header>');
            if ($hpos !== false) $body = substr($body, $hpos);
            preg_match_all('#href="[^"]*/genre/[^"]*"[^>]*>(?:<span>)?([^<]+)#i', $body, $g);
            $genres = json_encode(array_values(array_slice(array_unique(array_map('trim', $g[1] ?? [])), 0, 8)));

            // Insert/update content
            $exist = $pdo->prepare("SELECT id FROM content WHERE slug=? AND type='manga'");
            $exist->execute([$slug]);
            $row = $exist->fetch();
            $useSlug = $slug;
            if (!$row) {
                // Check global slug conflict (other types)
                $g2 = $pdo->prepare("SELECT id FROM content WHERE slug=?");
                $g2->execute([$slug]);
                if ($g2->fetch()) $useSlug = $slug . '-komik';
            }
            if ($row) {
                $cid = $row['id'];
                $pdo->prepare("UPDATE content SET poster_url=?, description=?, genres=?, source_url=?, updated_at=NOW() WHERE id=?")
                    ->execute([$poster, $desc, $genres, "https://komiku.org/manga/$slug/", $cid]);
            } else {
                $pdo->prepare("INSERT INTO content (slug, title, type, status, poster_url, description, genres, source_url) VALUES (?,?,'manga','ongoing',?,?,?,?)")
                    ->execute([$useSlug, $title, $poster, $desc, $genres, "https://komiku.org/manga/$slug/"]);
                $cid = $pdo->lastInsertId();
                $added++;
            }

            // Scrape chapter list + first 3 chapters' images
            if (preg_match('#id="Daftar_Chapter"(.*?)</table>#si', $detailHtml, $sec)) {
                preg_match_all('#href="([^"]+)"#i', $sec[1], $chLinks);
                $chUrls = array_reverse(array_unique($chLinks[1])); // oldest first
                $i = 0;
                foreach ($chUrls as $chUrl) {
                    if (strpos($chUrl, 'http') !== 0) $chUrl = 'https://komiku.org' . $chUrl;
                    preg_match('#chapter-([\d.]+)#i', $chUrl, $cn);
                    $chNum = (float)($cn[1] ?? 0);
                    if ($chNum <= 0) continue;

                    $images = '[]';
                    if ($i < 3) { // scrape images for first 3 chapters now
                        $cp = km_fetch($chUrl);
                        if ($cp) {
                            preg_match_all('#<img[^>]+(?:data-src|src)="(https://img\.komiku[^"]+\.(?:jpg|png|webp|jpeg))"#i', $cp, $ims);
                            $pages_img = array_values(array_filter($ims[1], fn($u) => !preg_match('#komikuplus|logo|banner|asset/img#i', $u)));
                            if ($pages_img) $images = json_encode($pages_img);
                        }
                        usleep(400000);
                    }
                    $pdo->prepare("INSERT IGNORE INTO chapters (content_id, chapter_number, title, images, source_url) VALUES (?,?,?,?,?)")
                        ->execute([$cid, $chNum, "Chapter " . (int)$chNum, $images, $chUrl]);
                    $i++;
                }
            }
            echo "  $title (cid=$cid)\n";
            usleep(300000);
        }
    }
    echo "Done! New manga added: $added\n";
}

// On-demand: scrape images for a specific chapter URL
if ($mode === 'chapter') {
    $chUrl = $argv[2] ?? '';
    $cp = km_fetch($chUrl);
    preg_match_all('#<img[^>]+(?:data-src|src)="(https://img\.komiku[^"]+\.(?:jpg|png|webp|jpeg))"#i', $cp, $ims);
    $pages_img = array_values(array_filter($ims[1], fn($u) => !preg_match('#komikuplus|logo|banner|asset/img#i', $u)));
    echo json_encode($pages_img);
}
