<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

$limit = (int)($argv[1] ?? 50);
// Get donghua with 0 episodes
$stmt = $pdo->prepare("SELECT c.id, c.slug, c.title FROM content c LEFT JOIN episodes e ON e.content_id=c.id WHERE c.type='donghua' GROUP BY c.id HAVING COUNT(e.id)=0 ORDER BY c.id LIMIT ?");
$stmt->execute([$limit]);
$items = $stmt->fetchAll();
echo count($items) . " donghua need episodes\n";

$total = 0;
foreach ($items as $item) {
    $url = "https://anichin.cafe/seri/{$item['slug']}/";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_USERAGENT=>'Mozilla/5.0', CURLOPT_SSL_VERIFYPEER=>false]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$html || $code >= 400) { usleep(300000); continue; }

    // Find episode links
    preg_match_all('#href="(https://anichin\.cafe/[^"]*episode[^"]+)"#i', $html, $eps);
    $epUrls = array_unique($eps[1] ?? []);
    if (empty($epUrls)) { usleep(300000); continue; }

    $added = 0;
    foreach ($epUrls as $epUrl) {
        preg_match('/episode[- ]?(\d+)/i', $epUrl, $en);
        $epNum = (int)($en[1] ?? 0);
        if (!$epNum) continue;

        // Fetch episode page for embed
        $ech = curl_init($epUrl);
        curl_setopt_array($ech, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>10, CURLOPT_USERAGENT=>'Mozilla/5.0', CURLOPT_SSL_VERIFYPEER=>false]);
        $epHtml = curl_exec($ech);
        curl_close($ech);

        $embedUrl = '';
        if ($epHtml && preg_match('#<iframe[^>]*src=["\']([^"\']*anichin\.stream[^"\']*)["\']#i', $epHtml, $em)) {
            $embedUrl = $em[1];
        }

        $pdo->prepare("INSERT INTO episodes (content_id, episode_number, title, video_url, source_url) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE video_url=VALUES(video_url)")
            ->execute([$item['id'], $epNum, "Episode $epNum", $embedUrl, $epUrl]);
        $added++;
        $total++;
        usleep(300000);
    }
    echo "  {$item['title']}: $added eps\n";
    sleep(1);
}
echo "Done! Total episodes added: $total\n";
