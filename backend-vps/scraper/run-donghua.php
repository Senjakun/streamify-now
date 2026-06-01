<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$baseUrl = 'https://anichin.cafe';

// Fetch all series URLs
$allSeries = [];
foreach (['ongoing','completed'] as $status) {
    for ($p = 1; $p <= 25; $p++) {
        $url = "{$baseUrl}/seri/?status={$status}&page={$p}";
        $html = @file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>15,'header'=>"User-Agent: Mozilla/5.0\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
        if (!$html) break;
        preg_match_all('|href="(https://anichin\.cafe/seri/[^"]+)"|', $html, $m);
        if (empty($m[1])) break;
        foreach ($m[1] as $u) $allSeries[$u] = $status;
        echo "  [{$status}] page {$p} → " . count($m[1]) . "\n";
    }
}
echo "Total series: " . count($allSeries) . "\n\n";

$i = 0;
foreach ($allSeries as $seriesUrl => $status) {
    $i++;
    try {
        $html = @file_get_contents($seriesUrl, false, stream_context_create(['http'=>['timeout'=>15,'header'=>"User-Agent: Mozilla/5.0\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
        if (!$html) { echo "[{$i}] SKIP (fetch fail): {$seriesUrl}\n"; continue; }

        preg_match('|<h1[^>]*>(.*?)</h1>|si', $html, $t);
        $title = trim(strip_tags($t[1] ?? ''));
        if (!$title) { echo "[{$i}] SKIP (no title): {$seriesUrl}\n"; continue; }

        $slug = basename(rtrim($seriesUrl, '/'));
        preg_match('|<img[^>]*class="[^"]*thumb[^"]*"[^>]*src="([^"]+)"|i', $html, $img);
        $poster = $img[1] ?? '';
        preg_match('|<div[^>]*class="[^"]*synop[^"]*"[^>]*>(.*?)</div>|si', $html, $desc);
        $description = trim(strip_tags($desc[1] ?? ''));
        preg_match_all('|<a[^>]*href="[^"]*genre[^"]*"[^>]*>(.*?)</a>|si', $html, $g);
        $genres = json_encode($g[1] ?? []);

        // Check existing
        $stmt = $pdo->prepare("SELECT id, type FROM content WHERE slug=?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if ($row && $row['type'] !== 'donghua') { $slug .= '-donghua'; $stmt->execute([$slug]); $row = $stmt->fetch(); }

        if ($row) {
            $pdo->prepare("UPDATE content SET title=?,poster_url=?,description=?,genres=?,status=? WHERE id=?")->execute([$title,$poster,$description,$genres,$status,$row['id']]);
            $contentId = $row['id'];
        } else {
            $pdo->prepare("INSERT INTO content (slug,title,type,poster_url,description,genres,status) VALUES (?,?,'donghua',?,?,?,?)")->execute([$slug,$title,$poster,$description,$genres,$status]);
            $contentId = $pdo->lastInsertId();
        }

        // Episodes
        preg_match_all('|href="(https://anichin\.cafe/[^"]*episode[^"]+)"|', $html, $eps);
        $epCount = 0;
        foreach ($eps[1] ?? [] as $epUrl) {
            preg_match('/episode[- ]?(\d+)/i', $epUrl, $en);
            $epNum = (int)($en[1] ?? 0);
            if (!$epNum) continue;
            $epHtml = @file_get_contents($epUrl, false, stream_context_create(['http'=>['timeout'=>10,'header'=>"User-Agent: Mozilla/5.0\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
            if (!$epHtml) continue;
            preg_match('|<iframe[^>]*src="([^"]+)"|i', $epHtml, $emb);
            $embedUrl = $emb[1] ?? '';
            $pdo->prepare("INSERT INTO episodes (content_id,episode_number,title,video_url,source_url) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE video_url=VALUES(video_url),source_url=VALUES(source_url)")
                ->execute([$contentId, $epNum, "Episode {$epNum}", $embedUrl, $epUrl]);
            $epCount++;
        }
        echo "[{$i}/" . count($allSeries) . "] {$title} - {$epCount} eps\n";
    } catch (Exception $e) {
        echo "[{$i}] ERROR: " . $e->getMessage() . "\n";
        continue;
    }
}
echo "\nDone!\n";
