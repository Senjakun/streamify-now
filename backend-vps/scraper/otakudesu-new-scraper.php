<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$proxyBase = 'http://143.198.93.61';

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}

function parseDetail($html, $proxyBase) {
    $data = [];

    // Title
    preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $m);
    $data['title'] = trim(strip_tags($m[1] ?? ''));
    $data['title'] = preg_replace('/\s*\(Episode.*$/i', '', $data['title']);
    $data['title'] = preg_replace('/Subtitle Indonesia.*$/i', '', $data['title']);
    $data['title'] = html_entity_decode($data['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $data['title'] = trim($data['title']);

    // Poster
    preg_match('#class=['"]{1}fotoanime['"]{1}[^>]*>.*?<img[^>]+src=['"]{1}([^'">]+)['"]{1}#s', $html, $m);
    $data['poster'] = $m[1] ?? '';

    // Info fields
    $fields = ['Judul','Skor','Status','Studio','Genre','Total Episode','Tipe','Tanggal Rilis'];
    foreach ($fields as $f) {
        preg_match('#<b>'.$f.'</b>:\s*(.*?)</span>#s', $html, $m);
        $val = trim(strip_tags($m[1] ?? ''));
        $data[strtolower(str_replace(' ','_',$f))] = $val;
    }

    // Genres
    preg_match_all('#/genres/[^/]+/" rel="tag"[^>]*>([^<]+)<#', $html, $gm);
    $data['genres'] = $gm[1] ?? [];

    // Synopsis
    preg_match('#class=["\']sinopc["\'][^>]*>(.*?)</div>#s', $html, $m);
    $data['synopsis'] = trim(strip_tags($m[1] ?? ''));

    // Episode list
    preg_match_all('#href="'.$proxyBase.'/play/([^/]+)/"[^>]*>([^<]+)<#', $html, $em);
    $episodes = [];
    foreach ($em[1] as $i => $slug) {
        $title = trim($em[2][$i]);
        // Extract episode number
        preg_match('/Episode\s+(\d+)/i', $title, $num);
        $episodes[] = [
            'slug' => $slug,
            'title' => $title,
            'number' => intval($num[1] ?? ($i+1)),
            'play_url' => $proxyBase.'/play/'.$slug.'/'
        ];
    }
    $data['episodes'] = array_reverse($episodes);

    return $data;
}

// Step 1: Ambil semua slug dari anime-list
echo "Mengambil daftar anime...\n";
$html = fetchUrl($proxyBase . '/anime-list.php');
preg_match_all('#href="'.$proxyBase.'/episode/([^/]+)/"[^"]*title="([^"]+)"#', $html, $matches);

$animeList = [];
foreach ($matches[1] as $i => $slug) {
    $animeList[] = ['slug' => $slug, 'title' => html_entity_decode($matches[2][$i])];
}

echo "Total anime ditemukan: " . count($animeList) . "\n";

$success = 0;
$failed = 0;

foreach ($animeList as $idx => $anime) {
    $slug = $anime['slug'];
    echo "[".($idx+1)."/".count($animeList)."] Scraping: $slug ... ";

    // Cek sudah ada di DB
    $check = $pdo->prepare("SELECT id FROM content WHERE slug=? AND type='anime'");
    $check->execute([$slug]);
    if ($check->fetch()) {
        echo "SKIP (sudah ada)\n";
        continue;
    }

    // Fetch detail
    $detailHtml = fetchUrl($proxyBase . '/lengkap/' . $slug . '/');
    if (!$detailHtml || strlen($detailHtml) < 1000) {
        echo "GAGAL (konten kosong)\n";
        $failed++;
        continue;
    }

    $detail = parseDetail($detailHtml, $proxyBase);

    if (empty($detail['title'])) {
        echo "GAGAL (tidak ada judul)\n";
        $failed++;
        continue;
    }

    // Rating
    $rating = floatval($detail['skor'] ?? 0);

    // Status
    $status = stripos($detail['status'] ?? '', 'ongoing') !== false ? 'ongoing' : 'completed';

    // Genres JSON
    $genres = json_encode($detail['genres'] ?? []);

    // Insert content
    try {
        $stmt = $pdo->prepare("INSERT INTO content (slug, title, type, poster_url, description, genres, rating, status, studio, year)  VALUES (?,?,?,?,?,?,?,?,?,?)");
        $year = preg_match('/(\d{4})/', $detail['tanggal_rilis'] ?? '', $ym) ? intval($ym[1]) : null;
        $stmt->execute([
            $slug,
            $detail['title'],
            'anime',
            $detail['poster'],
            $detail['synopsis'],
            $genres,
            $rating,
            $status,
            $detail['studio'] ?? '',
            $year
        ]);
        $contentId = $pdo->lastInsertId();

        // Insert episodes
        foreach ($detail['episodes'] as $ep) {
            $epStmt = $pdo->prepare("INSERT INTO episodes (content_id, episode_number, title, source_url) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title)");
            $epStmt->execute([
                $contentId,
                $ep['number'],
                $ep['title'],
                $ep['play_url']
            ]);
        }

        echo "OK ({$detail['title']}) - " . count($detail['episodes']) . " eps\n";
        $success++;
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failed++;
    }

    // Jeda 1 detik per anime
    sleep(1);
}

echo "\n=== SELESAI ===\n";
echo "Berhasil: $success\n";
echo "Gagal: $failed\n";
