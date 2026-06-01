<?php
// Unified novel chapter content - reads chapter source_url from DB, fetches by domain
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$pdo = getDB();
$slug = $_GET['novel'] ?? $_GET['slug'] ?? '';
$chapter = (int)($_GET['chapter'] ?? 1);
if (!$slug) { echo json_encode(['success' => false, 'error' => 'slug required']); exit; }

// Find novel + chapter
$nv = $pdo->prepare("SELECT id, title FROM novels WHERE slug=?");
$nv->execute([$slug]);
$novel = $nv->fetch();
if (!$novel) { echo json_encode(['success' => false, 'error' => 'novel not found']); exit; }

$ch = $pdo->prepare("SELECT * FROM novel_chapters WHERE novel_id=? AND chapter_number=?");
$ch->execute([$novel['id'], $chapter]);
$chRow = $ch->fetch();
if (!$chRow) { echo json_encode(['success' => false, 'error' => 'chapter not found']); exit; }

// If content already cached in DB, return it
if (!empty($chRow['content'])) {
    echo json_encode(['success' => true, 'data' => ['chapter_number' => $chapter, 'title' => $chRow['title'], 'content' => $chRow['content']]]);
    exit;
}

$url = $chRow['source_url'] ?? '';
$content = '';
if ($url) {
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0\r\nReferer: https://readnovelfull.com/\r\n", 'timeout' => 20], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $html = @file_get_contents($url, false, $ctx);
    if ($html) {
        if (stripos($url, 'readnovelfull') !== false) {
            if (preg_match('#<div id="chr-content"[^>]*>(.*?)</div>\s*(?:<div|<script)#si', $html, $m)) $content = $m[1];
        }
        // generic fallback
        if (!$content && preg_match('#<div[^>]*class="[^"]*(?:chapter-content|content-text|chr-c)[^"]*"[^>]*>(.*?)</div>\s*<#si', $html, $m)) $content = $m[1];
    }
}
$content = preg_replace('#<script[^>]*>.*?</script>#si', '', $content);
$content = preg_replace('#<ins[^>]*>.*?</ins>#si', '', $content);
$content = trim($content);

// Cache it
if ($content) $pdo->prepare("UPDATE novel_chapters SET content=? WHERE id=?")->execute([$content, $chRow['id']]);

// Prev/next
$prev = $pdo->prepare("SELECT chapter_number FROM novel_chapters WHERE novel_id=? AND chapter_number<? ORDER BY chapter_number DESC LIMIT 1");
$prev->execute([$novel['id'], $chapter]);
$next = $pdo->prepare("SELECT chapter_number FROM novel_chapters WHERE novel_id=? AND chapter_number>? ORDER BY chapter_number ASC LIMIT 1");
$next->execute([$novel['id'], $chapter]);

echo json_encode(['success' => true, 'data' => [
    'chapter_number' => $chapter,
    'title' => $chRow['title'],
    'content' => $content ?: '<p>Konten chapter tidak tersedia.</p>',
    'prev' => $prev->fetchColumn() ?: null,
    'next' => $next->fetchColumn() ?: null,
]]);
