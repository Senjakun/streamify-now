<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/manga-scraper.php';
$pdo = getDB();
$scraper = new MangaScraper();

$limit = (int)($argv[1] ?? 30);
$stmt = $pdo->prepare("SELECT c.id, c.slug, c.source_url, c.title FROM content c WHERE c.type='manga' AND c.source_url IS NOT NULL AND c.id NOT IN (SELECT DISTINCT content_id FROM chapters) ORDER BY c.id LIMIT ?");
$stmt->execute([$limit]);
$mangas = $stmt->fetchAll();
echo count($mangas) . " manga to scrape\n";

$totalCh = 0;
foreach ($mangas as $manga) {
    $html = @file_get_contents($manga['source_url'], false, stream_context_create(['http'=>['timeout'=>15,'header'=>"User-Agent: Mozilla/5.0\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
    if (!$html) { echo "  SKIP {$manga['title']}\n"; sleep(1); continue; }

    // Get chapter URLs via AJAX (kiryuu pattern)
    preg_match('#data-id="(\d+)"#', $html, $mid);
    $mangaId = $mid[1] ?? '';
    
    $chapterUrls = [];
    if ($mangaId) {
        $ajaxUrl = "https://v5.kiryuu.to/wp-admin/admin-ajax.php?manga_id={$mangaId}&page=1&action=chapter_list";
        $ajaxHtml = @file_get_contents($ajaxUrl, false, stream_context_create(['http'=>['timeout'=>15,'header'=>"User-Agent: Mozilla/5.0\r\n"],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
        if ($ajaxHtml) {
            preg_match_all('#href="(https://[^"]+)"#i', $ajaxHtml, $links);
            $chapterUrls = $links[1] ?? [];
        }
    }
    
    // Fallback: get from page
    if (empty($chapterUrls)) {
        preg_match_all('#<a[^>]+href="(https://v5\.kiryuu\.to/[^"]*chapter[^"]*)"#i', $html, $links);
        $chapterUrls = array_unique($links[1] ?? []);
    }

    echo "  {$manga['title']}: " . count($chapterUrls) . " ch";

    // Scrape first 3 chapters with images
    $scraped = 0;
    foreach (array_slice(array_reverse($chapterUrls), 0, 3) as $chUrl) {
        preg_match('#chapter[- ]?(\d+(?:\.\d+)?)#i', $chUrl, $cn);
        $chNum = (float)($cn[1] ?? 0);
        if ($chNum <= 0) continue;

        try {
            $result = $scraper->scrapeChapter($chUrl);
            $images = $result['images'] ?? [];
            // Filter: skip first image if it's from kiryuu domain (poster)
            if ($images && strpos($images[0], 'kiryuu') !== false) array_shift($images);
            
            if ($images) {
                $pdo->prepare("INSERT IGNORE INTO chapters (content_id, chapter_number, title, images, source_url) VALUES (?,?,?,?,?)")
                    ->execute([$manga['id'], $chNum, "Chapter " . (int)$chNum, json_encode($images), $chUrl]);
                $scraped++;
                $totalCh++;
            }
        } catch (Exception $e) {}
        usleep(800000);
    }

    // Insert remaining chapters as metadata (no images yet, will be fetched on-demand)
    foreach ($chapterUrls as $chUrl) {
        preg_match('#chapter[- ]?(\d+(?:\.\d+)?)#i', $chUrl, $cn);
        $chNum = (float)($cn[1] ?? 0);
        if ($chNum <= 0) continue;
        $pdo->prepare("INSERT IGNORE INTO chapters (content_id, chapter_number, title, images, source_url) VALUES (?,?,?,?,?)")
            ->execute([$manga['id'], $chNum, "Chapter " . (int)$chNum, '[]', $chUrl]);
        $totalCh++;
    }

    echo " → scraped $scraped with images\n";
    sleep(1);
}
echo "Done! Total chapters: $totalCh\n";
