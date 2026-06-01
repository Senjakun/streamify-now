<?php
// Full otakudesu.blog anime catalog scraper (1812 titles). Episodes source_url = .blog episode URL.
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$limit = (int)($argv[1] ?? 2000);
$ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n", 'timeout' => 18], 'ssl' => ['verify_peer' => false]]);
function get($u, $ctx) { return @file_get_contents($u, false, $ctx); }
function slugify($s) { return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($s))), '-'); }

$list = get('https://otakudesu.blog/anime-list/', $ctx);
preg_match_all('#href="(https://otakudesu\.blog/anime/[^"]+)"#i', $list, $m);
$urls = array_values(array_unique($m[1]));
echo count($urls) . " anime in catalog\n";

$nNew = 0; $nUpd = 0; $nEp = 0;
foreach (array_slice($urls, 0, $limit) as $idx => $url) {
    $d = get($url, $ctx);
    if (!$d) { usleep(150000); continue; }
    preg_match('#<meta property="og:title" content="([^"]+)"#i', $d, $tm);
    $title = html_entity_decode(trim(preg_replace('/\s*(?:\(Eps|\(Episode|Subtitle Indonesia|Sub Indo|\|\s*Otaku).*$/i', '', $tm[1] ?? '')));
    if (!$title) continue;
    preg_match('#<meta property="og:image" content="([^"]+)"#i', $d, $pm);
    $poster = $pm[1] ?? '';
    preg_match_all('#/genres/[^"]+"[^>]*>([^<]+)</a>#i', $d, $gm);
    $genres = json_encode(array_values(array_unique(array_map('trim', $gm[1] ?? []))));
    $status = preg_match('#<span>Status:\s*</span>\s*([A-Za-z]+)#i', $d, $sm) ? (stripos($sm[1], 'comp') !== false || stripos($sm[1], 'tamat') !== false ? 'completed' : 'ongoing') : (stripos($d, 'Completed') !== false ? 'completed' : 'ongoing');
    $rating = preg_match('#(?:Skor|Rating)[^0-9]*([0-9]\.[0-9]+)#i', $d, $rm) ? (float)$rm[1] : 0;
    $desc = '';
    // Synopsis = <p> plot paragraphs after the info block, excluding info-field labels & footer
    if (preg_match('#infozingle(.*?)(?:episodelist|class="(?:sharing|keying|isi-anime|venser))#is', $d, $chunk)) {
        preg_match_all('#<p[^>]*>(.*?)</p>#is', $chunk[1], $pp);
        $parts = [];
        foreach ($pp[1] as $x) {
            $t = trim(html_entity_decode(strip_tags($x)));
            if (strlen($t) < 25) continue;
            if (preg_match('/^(Judul|Japanese|English|Sinonim|Skor|Produser|Tipe|Status|Total Episode|Durasi|Tanggal Rilis|Studio|Genre)\s*:/i', $t)) continue;
            if (stripos($t, 'Tonton juga') !== false || stripos($t, 'kelanjutan') !== false) continue;
            $parts[] = $t;
        }
        $desc = trim(implode("\n\n", $parts));
    }
    if (!$desc && preg_match('#<meta property="og:description" content="([^"]+)"#i', $d, $om)) $desc = html_entity_decode($om[1]);
    $slug = slugify($title);

    $ex = $pdo->prepare("SELECT id FROM content WHERE type='anime' AND (title=? OR slug=?) LIMIT 1");
    $ex->execute([$title, $slug]);
    $cid = $ex->fetchColumn();
    if ($cid) {
        $pdo->prepare("UPDATE content SET poster_url=?, description=IF(?<>'',?,description), genres=?, status=?, rating=? WHERE id=?")
            ->execute([$poster, $desc, $desc, $genres, $status, $rating, $cid]);
        $nUpd++;
    } else {
        try {
            $pdo->prepare("INSERT INTO content (type, slug, title, poster_url, description, genres, status, rating) VALUES ('anime',?,?,?,?,?,?,?)")
                ->execute([$slug, $title, $poster, $desc, $genres, $status, $rating]);
        } catch (Exception $e) {
            $pdo->prepare("INSERT INTO content (type, slug, title, poster_url, description, genres, status, rating) VALUES ('anime',?,?,?,?,?,?,?)")
                ->execute([$slug . '-' . substr(md5($url), 0, 4), $title, $poster, $desc, $genres, $status, $rating]);
        }
        $cid = $pdo->lastInsertId();
        $nNew++;
    }

    preg_match_all('#href="(https://otakudesu\.blog/episode/[^"]*-episode-(\d+)-[^"]+)"#i', $d, $em);
    $seen = [];
    $ins = $pdo->prepare("INSERT INTO episodes (content_id, episode_number, title, source_url) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE source_url=VALUES(source_url)");
    for ($i = 0; $i < count($em[1]); $i++) {
        $n = (int)$em[2][$i];
        if (!$n || isset($seen[$n])) continue;
        $seen[$n] = 1;
        $ins->execute([$cid, $n, "Episode $n", $em[1][$i]]);
        if ($ins->rowCount()) $nEp++;
    }
    usleep(150000);
}
echo "Done. New: $nNew, Updated: $nUpd, Episodes: $nEp\n";
