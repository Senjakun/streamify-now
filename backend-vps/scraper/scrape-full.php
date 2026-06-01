<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function fetchUrl($url, $userAgent) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: id-ID,id;q=0.9',
            'Referer: https://otakudesu.live/',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 400) throw new Exception("HTTP $httpCode");
    return $response;
}

function parseHTML($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    return $dom;
}

// Ambil anime yang belum ada sinopsis ATAU rating 0
$stmt = $pdo->query("SELECT id, title, source_url FROM content WHERE type='anime' AND source_url IS NOT NULL ORDER BY id ASC");
$animes = $stmt->fetchAll();
$total = count($animes);
echo "Total: $total anime\n";

foreach ($animes as $idx => $anime) {
    $num = $idx + 1;
    echo "[$num/$total] {$anime['title']}\n";
    try {
        $html = fetchUrl($anime['source_url'], $userAgent);
        $dom = parseHTML($html);
        $xpath = new DOMXPath($dom);

        // ── Sinopsis dari meta description ──
        $metaNodes = $xpath->query("//meta[@name='description']");
        $synopsis = $metaNodes->length > 0 ? $metaNodes->item(0)->getAttribute('content') : null;

        // ── Rating dari Score + span ──
        preg_match('/Score.*?<span[^>]*>([0-9.]+)<\/span>/is', $html, $ratingMatch);
        $rating = isset($ratingMatch[1]) ? floatval($ratingMatch[1]) : 0;

        // ── Genre ──
        preg_match_all('/href="[^"]*genre[^"]*"[^>]*>([^<]+)<\/a>/i', $html, $genreMatches);
        $genres = array_unique(array_map('trim', $genreMatches[1]));

        // ── Update content ──
        $stmt2 = $pdo->prepare("UPDATE content SET description=?, genres=?, rating=?, updated_at=NOW() WHERE id=?");
        $stmt2->execute([$synopsis, json_encode($genres), $rating, $anime['id']]);

        // ── Episode list + scrape embed URL ──
        preg_match_all('/href="(\/episodes\/[^"]+)"/', $html, $epMatches);
        $epUrls = array_unique($epMatches[1]);

        $epCount = 0;
        foreach ($epUrls as $epUrl) {
            preg_match('/episode-(\d+)/i', $epUrl, $epNumMatch);
            $epNum = isset($epNumMatch[1]) ? (int)$epNumMatch[1] : 0;
            if ($epNum === 0) continue;

            $fullEpUrl = 'https://otakudesu.live' . $epUrl;

            // Scrape embed URL dari halaman episode
            $embedUrl = null;
            try {
                $epHtml = fetchUrl($fullEpUrl, $userAgent);
                preg_match('/<iframe[^>]+src="([^"]+)"/i', $epHtml, $iframeMatch);
                $embedUrl = $iframeMatch[1] ?? null;
            } catch (Exception $e) {
                // skip jika gagal
            }

            $stmtEp = $pdo->prepare("INSERT INTO episodes (content_id, episode_number, title, video_url, source_url) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE video_url=VALUES(video_url), source_url=VALUES(source_url)");
            $stmtEp->execute([$anime['id'], $epNum, "Episode $epNum", $embedUrl, $fullEpUrl]);
            $epCount++;

            sleep(1); // jeda tiap episode
        }

        echo "  OK: synopsis=" . (strlen($synopsis ?? '') > 20 ? 'ya' : 'tidak') . " rating=$rating eps=$epCount\n";

    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
    sleep(rand(2, 4));
}
echo "SELESAI!\n";
