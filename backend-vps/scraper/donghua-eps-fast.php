<?php
// Fast donghua episode scraper - episode list only (embed on-demand). For cron.
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$limit = (int)($argv[1] ?? 60);
$ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0\r\n", 'timeout' => 15], 'ssl' => ['verify_peer' => false]]);

// Donghua with 0 episodes first, then update ongoing (new episodes)
$rows = $pdo->query("SELECT c.id, c.slug FROM content c WHERE c.type='donghua' AND c.slug NOT LIKE '% %' AND NOT EXISTS (SELECT 1 FROM episodes e WHERE e.content_id=c.id) ORDER BY c.id LIMIT $limit")->fetchAll();
// Also refresh ongoing for new episodes
$ongoing = $pdo->query("SELECT c.id, c.slug FROM content c WHERE c.type='donghua' AND c.status='ongoing' AND c.slug NOT LIKE '% %' ORDER BY c.updated_at DESC LIMIT 30")->fetchAll();
$rows = array_merge($rows, $ongoing);

$total = 0; $done = 0;
foreach ($rows as $r) {
    $h = @file_get_contents("https://anichin.cafe/seri/{$r['slug']}/", false, $ctx);
    if (!$h) { usleep(200000); continue; }
    preg_match_all('#href="(https://anichin\.cafe/[^"]*episode[^"]+)"#i', $h, $m);
    $eps = array_unique($m[1] ?? []);
    $ins = $pdo->prepare("INSERT IGNORE INTO episodes (content_id, episode_number, title, source_url) VALUES (?,?,?,?)");
    foreach ($eps as $eu) {
        preg_match('/episode[- ]?(\d+)/i', $eu, $en);
        $n = (int)($en[1] ?? 0); if (!$n) continue;
        $ins->execute([$r['id'], $n, "Episode $n", $eu]);
        if ($ins->rowCount()) $total++;
    }
    $done++;
    usleep(250000);
}
echo "Donghua episodes: $done series, $total new eps\n";
