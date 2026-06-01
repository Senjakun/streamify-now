<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'list';
$baseUrl = 'https://mangaserver.playall.me';
$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

function fetchUrl($url, $ua) {
    $ctx = stream_context_create(['http' => [
        'timeout' => 30,
        'header' => "User-Agent: $ua\r\nReferer: https://v5.kiryuu.to/\r\n"
    ]]);
    return file_get_contents($url, false, $ctx);
}

function parseDOM($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    return new DOMXPath($dom);
}

function success($data) {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function error($msg) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// LIST
if ($action === 'list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $orderby = $_GET['orderby'] ?? 'update';
    $status = $_GET['status'] ?? '';
    $type   = strtolower(trim($_GET['type'] ?? ''));
    $genre  = strtolower(trim($_GET['genre'] ?? ''));

    // Build URL berdasarkan filter
    if ($genre) {
        $genreSlug = preg_replace('/[^a-z0-9]+/', '-', $genre);
        $url = $page > 1
            ? "$baseUrl/genres/$genreSlug/page/$page/"
            : "$baseUrl/genres/$genreSlug/";
    } elseif ($type && in_array($type, ['manga','manhwa','manhua'])) {
        $url = "$baseUrl/manga/page/$page/?the_type=$type";
    } elseif ($status === 'ongoing') {
        $url = "$baseUrl/manga/page/$page/?status=ongoing";
    } elseif ($status === 'completed') {
        $url = "$baseUrl/manga/page/$page/?status=end";
    } else {
        $url = $page > 1
            ? "$baseUrl/manga/page/$page/?orderby=$orderby"
            : "$baseUrl/manga/?orderby=$orderby";
    }

    $html = fetchUrl($url, $ua);
    if (!$html) error('Gagal fetch kiryuu');

    $items = [];
    $processed = [];

    // type SVG & numscore ada di LUAR <a> tag — pakai regex context window
    preg_match_all('#/detail/([^/"?\s]+)/?#', $html, $slugMatches, PREG_OFFSET_CAPTURE);

    foreach ($slugMatches[1] as $match) {
        $slug = $match[0];
        $offset = $match[1];

        if ($slug === 'list-mode') continue;
        if (strpos($slug, '?') !== false) continue;
        if (strpos($slug, '/') !== false) continue;
        if (in_array($slug, $processed)) continue;
        $processed[] = $slug;

        // Context: 400 char sebelum (ada img) + 1000 sesudah (ada SVG type + numscore)
        $ctx = substr($html, max(0, $offset - 400), 3200);

        // Poster & title dari img kiryuu CDN atau envira-cdn
        $poster = '';
        $title = '';
        if (preg_match('#<img[^>]+src="(https://(?:v1\.kiryuu\.to/wp-content|images\.envira-cdn\.com)[^"]+)"[^>]*(?:alt="([^"]*)")?#i', $ctx, $imgM)) {
            $poster = $imgM[1];
            $title = html_entity_decode(trim($imgM[2] ?? ''), ENT_QUOTES, 'UTF-8');
        }
        // Fallback: cari alt dari img manapun di ctx
        if (!$title) {
            if (preg_match('#<img[^>]+alt="([^"]{3,})"#i', $ctx, $altM)) {
                $title = html_entity_decode(trim($altM[1]), ENT_QUOTES, 'UTF-8');
            }
        }
        if (!$title || !$poster) continue;

        // Type dari SVG filename (manhwa.svg / manhua.svg / manga.svg)
        $typeVal = 'manga';
        if (preg_match('#(manhwa|manhua|manga)\.svg#i', $ctx, $tm)) {
            $typeVal = strtolower($tm[1]);
        } elseif (preg_match('#alt="(manhwa|manhua|manga)"#i', $ctx, $tm2)) {
            $typeVal = strtolower($tm2[1]);
        }

        // Rating dari numscore
        $rating = 0.0;
        if (preg_match('#class="numscore"[^>]*>\s*([\d.]+)\s*<#', $ctx, $rm)) {
            $rating = min((float)$rm[1], 9.9);
        }

        // Status
        $statusVal = 'ongoing';
        if (preg_match('#bg-green[^>]*>[^<]*(?:end|complete|tamat|Tamat)#i', $ctx)) {
            $statusVal = 'completed';
        }

        $items[] = [
            'slug'       => $slug,
            'title'      => $title,
            'poster_url' => $poster,
            'rating'     => $rating,
            'status'     => $statusVal,
            'type'       => $typeVal,
            'genres'     => [],
        ];
    }

    success(['content' => $items, 'pagination' => ['page' => $page, 'pages' => 280]]);
}

// DETAIL
if ($action === 'detail') {
    $slug = $_GET['slug'] ?? '';
    if (!$slug) error('Slug required');

    // Fetch dari kiryuu langsung untuk manga_id & data lengkap
    $kiryuuUrl = "https://v5.kiryuu.to/manga/$slug/";
    $html = fetchUrl($kiryuuUrl, $ua);
    // Fallback ke mangaserver
    if (!$html) $html = fetchUrl("$baseUrl/detail/$slug/", $ua);
    if (!$html) error('Gagal fetch detail');

    $xpath = parseDOM($html);

    // Title
    $title = $xpath->evaluate('string(//h1[@itemprop="name"])');
    if (!$title) $title = $xpath->evaluate('string(//h1)');

    // Poster
    $poster = $xpath->evaluate('string(//img[@itemprop="image"]/@src)')
           ?: $xpath->evaluate('string(//div[contains(@class,"thumb")]//img/@src)');
    if (!$poster) { preg_match('/<meta[^>]*og:image[^>]*content="([^"]+)"/i', $html, $pm); $poster = $pm[1] ?? ''; }

    // Genres — coba berbagai selector
    $genres = [];
    $genreNodes = $xpath->query("//*[@itemprop='genre']//a|//a[contains(@href,'/genres/')]|//a[contains(@href,'/genre/')]");
    foreach ($genreNodes as $g) {
        $gt = trim($g->textContent);
        if ($gt && strlen($gt) < 40) $genres[] = $gt;
    }
    $genres = array_values(array_unique($genres));

    // Desc
    $desc = '';
    $paras = $xpath->query('//p');
    $longest = '';
    foreach ($paras as $p) {
        $text = trim($p->textContent);
        if (strlen($text) > strlen($longest)) $longest = $text;
    }
    $desc = $longest;
    $desc = preg_replace('/[\x{0080}-\x{00A0}\x{2018}-\x{201F}\x{2026}\x{2013}\x{2014}]/u', '', $desc);
    $desc = preg_replace('/\bâ\b|â¦|â|â|Ã¢/u', '', $desc);
    $desc = preg_replace('/\s+/', ' ', trim($desc));
    $desc = html_entity_decode(htmlspecialchars_decode($desc, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
    if (!mb_check_encoding($desc, 'UTF-8')) { $desc = utf8_encode($desc); }
    $desc = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $desc);

    // Rating — coba numscore dulu, lalu itemprop
    $rating = 0.0;
    if (preg_match('#class="numscore"[^>]*>\s*([\d.]+)\s*<#', $html, $rm)) {
        $rating = min((float)$rm[1], 9.9);
    } else {
        $ratingNode = $xpath->query('//*[@itemprop="ratingValue"]')->item(0);
        if ($ratingNode) $rating = min(floatval($ratingNode->textContent), 9.9);
    }

    // Status
    $status_raw = '';
    $statusNode = $xpath->query('//*[contains(@class,"status")]')->item(0);
    if ($statusNode) $status_raw = trim($statusNode->textContent);

    // Author
    $author = '';
    $authorNode = $xpath->query('//*[@itemprop="author"]')->item(0);
    if ($authorNode) $author = trim($authorNode->textContent);

    // Type — coba dari SVG badge, lalu itemprop
    $typeVal = 'manga';
    if (preg_match('#(manhwa|manhua|manga)\.svg#i', $html, $tm)) {
        $typeVal = strtolower($tm[1]);
    } elseif (preg_match('#<[^>]*itemprop="bookFormat"[^>]*>([^<]+)<#i', $html, $tfm)) {
        $t = strtolower(trim($tfm[1]));
        if (in_array($t, ['manhwa','manhua','manga'])) $typeVal = $t;
    }

    // manga_id untuk chapters
    $manga_id = null;
    preg_match('/manga_id=(\d+)/', $html, $mid);
    if ($mid) $manga_id = $mid[1];

    success([
        'slug'        => $slug,
        'title'       => trim($title),
        'poster_url'  => $poster,
        'description' => trim($desc),
        'rating'      => $rating,
        'status'      => (stripos($status_raw, 'end') !== false || stripos($status_raw, 'tamat') !== false) ? 'completed' : 'ongoing',
        'author'      => trim($author),
        'genres'      => $genres,
        'manga_id'    => $manga_id,
        'type'        => $typeVal,
    ]);
}

// CHAPTERS (via AJAX kiryuu)
if ($action === 'chapters') {
    $manga_id = $_GET['manga_id'] ?? '';
    $page = $_GET['page'] ?? 1;
    if (!$manga_id) error('manga_id required');

    $url = "https://v5.kiryuu.to/wp-admin/admin-ajax.php?manga_id=$manga_id&page=$page&action=chapter_list";
    $html = fetchUrl($url, $ua);
    if (!$html) error('Gagal fetch chapters');

    $xpath = parseDOM($html);
    $chapters = [];
    $divs = $xpath->query("//div[@data-chapter-number]");
    foreach ($divs as $div) {
        $num = $div->getAttribute('data-chapter-number');
        $link = $xpath->evaluate('string(.//a/@href)', $div);
        $titleNode = $xpath->query('.//a//div[contains(@class,"flex")][1]//span', $div)->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : 'Chapter ' . $num;
        preg_match('#/manga/[^/]+/([^/]+)/#', $link, $m);
        $chapters[] = [
            'chapter_number' => floatval($num),
            'title' => trim($title),
            'slug' => $m[1] ?? '',
            'source_url' => $link,
        ];
    }

    success($chapters);
}

// CHAPTER IMAGES
if ($action === 'chapter') {
    $chapterUrl = $_GET['url'] ?? '';
    $slug = $_GET['slug'] ?? '';
    if (!$chapterUrl && !$slug) error('Slug or url required');

    if ($chapterUrl) {
        $html = fetchUrl($chapterUrl, $ua);
    } else {
        $html = fetchUrl("https://v5.kiryuu.to/manga/$slug/", $ua);
    }
    if (!$html) error('Gagal fetch chapter');

    $images = [];
    if (preg_match('/ts_reader\.run\((\{.*?\})\)/s', $html, $m)) {
        $data = json_decode($m[1], true);
        $images = $data['sources'][0]['images'] ?? [];
    }

    if (empty($images)) {
        // Coba regex langsung untuk yuucdn/cdn images
        preg_match_all('/src=[\'"](https?:\/\/(?:[^.\'"]+\.)*(?:yuucdn\.com|yuucdn\.net|cdnkuma\.my\.id|uqni\.net|kiryuu\.to)[^\'"]+\.(?:jpg|jpeg|png|webp|gif))[\'"]/i', $html, $imgMatches);
        if (!empty($imgMatches[1])) {
            $imgs = array_unique($imgMatches[1]);
            // Filter out poster/cover images
            $images = array_values(array_filter($imgs, function($url) {
                return strpos($url, '/uploads/images/') !== false || strpos($url, '/chapter') !== false;
            }));
        }
    }
    if (empty($images)) {
        $xpath = parseDOM($html);
        $imgs = $xpath->query("//div[@id='readerarea']//img");
        foreach ($imgs as $img) {
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
            if ($src && strpos($src, 'http') === 0) $images[] = $src;
        }
    }

    success(['images' => $images, 'slug' => $slug]);
}

// SEARCH
if ($action === 'search') {
    $q = $_GET['q'] ?? '';
    if (!$q) error('Query required');

    $html = fetchUrl("$baseUrl/?s=" . urlencode($q), $ua);
    if (!$html) error('Gagal search');

    $items = [];
    $processed = [];

    preg_match_all('#/detail/([^/"?\s]+)/?#', $html, $slugMatches, PREG_OFFSET_CAPTURE);
    foreach ($slugMatches[1] as $match) {
        $slug = $match[0];
        $offset = $match[1];
        if (in_array($slug, ['list-mode','page','latest','manga','genres'])) continue;
        if (strpos($slug, '?') !== false) continue;
        if (strpos($slug, '/') !== false) continue;
        if (strlen($slug) < 3) continue;
        if (in_array($slug, $processed)) continue;
        $processed[] = $slug;

        $ctx = substr($html, max(0, $offset - 400), 3200);

        $poster = '';
        $title = '';
        if (preg_match('#<img[^>]+src="(https://(?:v1\.kiryuu\.to/wp-content|images\.envira-cdn\.com)[^"]+)"[^>]*(?:alt="([^"]*)")?#i', $ctx, $imgM)) {
            $poster = $imgM[1];
            $title = html_entity_decode(trim($imgM[2] ?? ''), ENT_QUOTES, 'UTF-8');
        }
        if (!$title) {
            if (preg_match('#<img[^>]+alt="([^"]{3,})"#i', $ctx, $altM)) {
                $title = html_entity_decode(trim($altM[1]), ENT_QUOTES, 'UTF-8');
            }
        }
        if (!$title || !$poster) continue;
        if (stripos($title, 'manga terlengkap') !== false || stripos($title, 'series page') !== false) continue;

        $typeVal = 'manga';
        if (preg_match('#(manhwa|manhua|manga)\.svg#i', $ctx, $tm)) $typeVal = strtolower($tm[1]);

        $rating = 0.0;
        if (preg_match('#class="numscore"[^>]*>\s*([\d.]+)\s*<#', $ctx, $rm)) $rating = min((float)$rm[1], 9.9);

        $items[] = [
            'slug'       => $slug,
            'title'      => $title,
            'poster_url' => $poster,
            'rating'     => $rating,
            'type'       => $typeVal,
            'genres'     => [],
        ];
    }

    success(['content' => $items]);
}

// REGISTER MANGA KE DB (untuk komentar/bookmark/history)
if ($action === 'register') {
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDB();
    $slug = $_GET['slug'] ?? '';
    $title = $_GET['title'] ?? '';
    $poster = $_GET['poster'] ?? '';
    if (!$slug) error('Slug required');

    // Cek apakah sudah ada
    $stmt = $pdo->prepare("SELECT id FROM content WHERE slug = ? AND type = 'manga'");
    $stmt->execute([$slug]);
    $existing = $stmt->fetch();
    if ($existing) {
        success(['id' => $existing['id']]);
    }

    // Insert baru
    $stmt = $pdo->prepare("INSERT INTO content (slug, title, type, status, poster_url, rating, genres, created_at, updated_at) VALUES (?, ?, 'manga', 'ongoing', ?, 0, '[]', NOW(), NOW())");
    $stmt->execute([$slug, $title, $poster]);
    $id = $pdo->lastInsertId();
    success(['id' => $id]);
}

// IMAGE PROXY
if ($action === 'image') {
    $url = $_GET['url'] ?? '';
    if (!$url) error('URL required');
    
    $ctx = stream_context_create(['http' => [
        'timeout' => 10,
        'header' => "User-Agent: $ua\r\nReferer: https://v5.kiryuu.to/\r\n"
    ]]);
    
    $img = file_get_contents($url, false, $ctx);
    if (!$img) error('Gagal fetch image');
    
    // Detect content type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($img);
    
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    echo $img;
    exit;
}

if ($action === 'latest') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $url = "$baseUrl/latest/?the_page=$page";
    $html = fetchUrl($url, $ua);
    if (!$html) error('Gagal fetch latest');

    $items = [];
    $processed = [];

    preg_match_all('#/detail/([^/"?\s]+)/?#', $html, $slugMatches, PREG_OFFSET_CAPTURE);
    foreach ($slugMatches[1] as $match) {
        $slug = $match[0];
        $offset = $match[1];
        if (in_array($slug, ['list-mode','page','latest','manga','genres'])) continue;
        if (strpos($slug, '?') !== false) continue;
        if (strpos($slug, '/') !== false) continue;
        if (strlen($slug) < 3) continue;
        if (in_array($slug, $processed)) continue;
        $processed[] = $slug;

        $ctx = substr($html, max(0, $offset - 400), 3200);

        $poster = '';
        $title = '';
        if (preg_match('#<img[^>]+src="(https://(?:v1\.kiryuu\.to/wp-content|images\.envira-cdn\.com)[^"]+)"[^>]*(?:alt="([^"]*)")?#i', $ctx, $imgM)) {
            $poster = $imgM[1];
            $title = html_entity_decode(trim($imgM[2] ?? ''), ENT_QUOTES, 'UTF-8');
        }
        if (!$title) {
            if (preg_match('#<img[^>]+alt="([^"]{3,})"#i', $ctx, $altM)) {
                $title = html_entity_decode(trim($altM[1]), ENT_QUOTES, 'UTF-8');
            }
        }
        if (!$title || !$poster) continue;
        if (stripos($title, 'manga terlengkap') !== false || stripos($title, 'series page') !== false) continue;

        $typeVal = 'manga';
        if (preg_match('#(manhwa|manhua|manga)\.svg#i', $ctx, $tm)) $typeVal = strtolower($tm[1]);

        $rating = 0.0;
        if (preg_match('#class="numscore"[^>]*>\s*([\d.]+)\s*<#', $ctx, $rm)) $rating = min((float)$rm[1], 9.9);

        $statusVal = 'ongoing';
        if (preg_match('#bg-green[^>]*>[^<]*(?:end|complete|tamat)#i', $ctx)) $statusVal = 'completed';

        $items[] = [
            'slug'       => $slug,
            'title'      => $title,
            'poster_url' => $poster,
            'rating'     => $rating,
            'status'     => $statusVal,
            'type'       => $typeVal,
            'genres'     => [],
        ];
    }
    success(['content' => $items, 'pagination' => ['page' => $page, 'pages' => 20]]);
}

error('Invalid action');
