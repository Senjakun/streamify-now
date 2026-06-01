<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$baseUrl = "https://anichin.moe";
$ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";

function fetchUrl($url, $ua) {
    $ctx = stream_context_create(['http' => [
        'timeout' => 15,
        'header' => "User-Agent: $ua\r\nAccept: text/html\r\n",
        'follow_location' => 1,
    ]]);
    return @file_get_contents($url, false, $ctx);
}

function success($data) { echo json_encode(['success' => true, 'data' => $data]); exit; }
function error($msg)    { echo json_encode(['success' => false, 'error' => $msg]); exit; }

function parseCards($html) {
    $items = [];
    $processed = [];
    preg_match_all('#<article class="bs".*?</article>#s', $html, $cards);
    foreach ($cards[0] as $card) {
        if (!preg_match('#href="/([^/"?]+)/"#', $card, $sm)) continue;
        $slug = $sm[1];
        if (in_array($slug, ['anime','ongoing','completed','drop','bookmark','schedule','upcoming-donghua'])) continue;
        if (in_array($slug, $processed)) continue;
        $processed[] = $slug;

        $title = '';
        if (preg_match('#title="([^"]+)"#', $card, $tm)) $title = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');
        if (!$title) continue;

        $poster = '';
        if (preg_match('#<img[^>]+src="([^"]+)"#', $card, $im)) {
            $poster = preg_replace('#\?resize=\d+,\d+#', '', $im[1]);
        }

        $status = 'ongoing';
        if (preg_match('#<span class="epx">([^<]+)<#', $card, $stm)) {
            $st = strtolower(trim($stm[1]));
            if (strpos($st, 'complet') !== false || strpos($st, 'tamat') !== false) $status = 'completed';
        }

        // Episode terbaru
        $latestEp = '';
        if (preg_match('#<span[^>]*class="[^"]*epl[^"]*"[^>]*>([^<]+)<#i', $card, $ep)) $latestEp = trim($ep[1]);

        $items[] = [
            'slug'       => $slug,
            'title'      => $title,
            'poster_url' => $poster,
            'status'     => $status,
            'type'       => 'donghua',
            'rating'     => 0,
            'latest_ep'  => $latestEp,
        ];
    }
    return $items;
}

$action = $_GET['action'] ?? 'list';

// ── LIST ──────────────────────────────────────────────
if ($action === 'list') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');

    if ($search) {
        $url = "$baseUrl/?s=" . urlencode($search);
    } elseif ($status === 'ongoing') {
        $url = $page > 1 ? "$baseUrl/ongoing/?page=$page" : "$baseUrl/ongoing/";
    } elseif ($status === 'completed') {
        $url = $page > 1 ? "$baseUrl/completed/?page=$page" : "$baseUrl/completed/";
    } else {
        $genre = strtolower(trim($_GET['genre'] ?? ''));
    if ($genre) {
        $genreSlug = preg_replace('/[^a-z0-9]+/', '-', $genre);
        $url = $page > 1
            ? "$baseUrl/genres/$genreSlug/page/$page/"
            : "$baseUrl/genres/$genreSlug/";
    } else {
        $url = $page > 1
            ? "$baseUrl/anime/?page=$page&status=&type=&order=update"
            : "$baseUrl/anime/?status=&type=&order=update";
    }
    }

    $html = fetchUrl($url, $ua);
    if (!$html) error('Gagal fetch anichin');

    $items = parseCards($html);

    // Hitung total pages dari pagination
    $totalPages = 1;
    preg_match_all('#page=(\d+)#', $html, $pm);
    if ($pm[1]) $totalPages = max(array_map('intval', $pm[1]));

    success(['content' => $items, 'pagination' => ['page' => $page, 'pages' => $totalPages]]);
}

// ── LATEST ────────────────────────────────────────────
if ($action === 'latest') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $url = $page > 1
        ? "$baseUrl/anime/?page=$page&status=&type=&order=update"
        : "$baseUrl/anime/?status=&type=&order=update";

    $html = fetchUrl($url, $ua);
    if (!$html) error('Gagal fetch latest');

    $items = parseCards($html);
    $totalPages = 1;
    preg_match_all('#page=(\d+)#', $html, $pm);
    if ($pm[1]) $totalPages = max(array_map('intval', $pm[1]));

    success(['content' => $items, 'pagination' => ['page' => $page, 'pages' => $totalPages]]);
}

// ── DETAIL ────────────────────────────────────────────
if ($action === 'detail') {
    $slug = $_GET['slug'] ?? '';
    if (!$slug) error('Slug required');

    $html = fetchUrl("$baseUrl/$slug/", $ua);
    if (!$html) error('Gagal fetch detail');

    // Title
    $title = '';
    if (preg_match('#<h1[^>]*class="[^"]*entry-title[^"]*"[^>]*>([^<]+)<#i', $html, $tm)) $title = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');

    // Poster
    $poster = '';
    if (preg_match('#<div class="thumb"[^>]*>.*?<img[^>]+src="([^"]+)"#is', $html, $im)) $poster = preg_replace('#\?resize=\d+,\d+#', '', $im[1]);

    // Synopsis
    $desc = '';
    if (preg_match('#<div class="entry-content[^"]*"[^>]*>(.*?)</div>#is', $html, $dm)) {
        $desc = html_entity_decode(trim(strip_tags($dm[1])), ENT_QUOTES, "UTF-8");
    }
    if (!$desc && preg_match('#itemprop="description"[^>]*>(.*?)</\w+>#is', $html, $dm2)) {
        $desc = trim(strip_tags($dm2[1]));
    }

    // Status
    $status = 'ongoing';
    if (preg_match('#<span[^>]*class="[^"]*status[^"]*"[^>]*>([^<]+)<#i', $html, $stm)) {
        $st = strtolower(trim($stm[1]));
        if (strpos($st, 'complet') !== false || strpos($st, 'tamat') !== false) $status = 'completed';
    }

    // Genres
    $genres = [];
    preg_match_all('#<a[^>]+href="[^"]*genre[^"]*"[^>]*>([^<]+)<#i', $html, $gm);
    foreach ($gm[1] as $g) { $gt = trim($g); if ($gt) $genres[] = $gt; }

    // Episodes list dari eplister
    $episodes = [];
    preg_match_all('#<li[^>]*data-index="\d+"[^>]*>\s*<a href="(/[^"]+)"[^>]*>\s*<div class="epl-num">(\d+)</div>\s*<div class="epl-title">([^<]+)</div>#is', $html, $em);
    foreach ($em[1] as $i => $epUrl) {
        $epNum = (int)$em[2][$i];
        $epTitle = trim($em[3][$i]);
        $epSlug = trim($epUrl, '/');
        $episodes[] = ['number' => $epNum, 'title' => $epTitle, 'slug' => $epSlug, 'url' => "https://anichin.moe$epUrl"];
    }
    usort($episodes, fn($a,$b) => $b['number'] - $a['number']);

    success([
        'slug'        => $slug,
        'title'       => $title,
        'poster_url'  => $poster,
        'description' => $desc,
        'status'      => $status,
        'genres'      => array_values(array_unique($genres)),
        'type'        => 'donghua',
        'episodes'    => $episodes,
    ]);
}

// ── EMBED ─────────────────────────────────────────────
if ($action === 'embed') {
    $slug = $_GET['slug'] ?? '';
    if (!$slug) error('Slug required');

    $html = fetchUrl("$baseUrl/$slug/", $ua);
    if (!$html) error('Gagal fetch episode');

    // Cari iframe embed (ok.ru, etc)
    $embed = '';
    if (preg_match('#<iframe[^>]+src="([^"]+)"#i', $html, $em)) $embed = $em[1];
    // Cari dari data-src juga
    if (!$embed && preg_match('#data-src="([^"]+(?:ok\.ru|video)[^"]+)"#i', $html, $em2)) $embed = $em2[1];
    // Cari dari script base64
    if (!$embed && preg_match('#Base64\.decode\("([^"]+)"#', $html, $b64)) {
        $decoded = base64_decode($b64[1]);
        if (preg_match('#src="([^"]+)"#', $decoded, $dem)) $embed = $dem[1];
    }

    if (!$embed) error('Embed tidak ditemukan');
    success(['embed_url' => $embed]);
}


if ($action === 'register') {
    $slug  = $_GET['slug']  ?? '';
    $title = $_GET['title'] ?? '';
    $poster= $_GET['poster'] ?? '';
    if (!$slug) error('Slug required');

    $pdo = new PDO('mysql:host=mysql;dbname=streamify_db;charset=utf8mb4', 'streamify_user', 'rimbamobile2');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Insert jika belum ada
    // Cek by slug+type dulu
    $row = $pdo->query("SELECT id FROM content WHERE slug=" . $pdo->quote($slug) . " AND type='donghua' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$row && $title) {
        // Cek by title+type
        $row = $pdo->prepare("SELECT id FROM content WHERE title=? AND type='donghua' LIMIT 1");
        $row->execute([$title]);
        $row = $row->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) {
        // Insert baru — kalau slug conflict, pakai slug-donghua
        $useSlug = $slug;
        $checkSlug = $pdo->query("SELECT id FROM content WHERE slug=" . $pdo->quote($slug) . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($checkSlug) $useSlug = $slug . '-donghua';
        $ins = $pdo->prepare("INSERT IGNORE INTO content (slug, title, type, poster_url, status, rating, genres, created_at, updated_at) VALUES (?, ?, 'donghua', ?, 'ongoing', 0, '[]', NOW(), NOW())");
        $ins->execute([$useSlug, $title ?: $slug, $poster]);
        $row = $pdo->query("SELECT id FROM content WHERE slug=" . $pdo->quote($useSlug) . " AND type='donghua' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) error('Register gagal');

    success(['id' => (int)$row['id']]);
}

error('Invalid action');
