<?php

if (!defined('NOVEL_WOOPREAD_LOADED')) {
    define('NOVEL_WOOPREAD_LOADED', true);

    function nw_parse_meta(string $html, string $url): array {
        [$xpath, ] = nc_xp($html);

        $title = nc_meta($xpath, 'property', 'og:title') ?: nc_meta($xpath, 'name', 'twitter:title');
        $title = preg_replace('/\s*\|\s*WoopRead.*$/i', '', (string)$title);

        if ($title === '') {
            $node = $xpath->query('//h1')->item(0);
            $title = $node ? trim($node->textContent) : '';
        }

        $description = nc_meta($xpath, 'property', 'og:description') ?: nc_meta($xpath, 'name', 'description');
        $cover = nc_meta($xpath, 'property', 'og:image');

        $text = nc_bodytxt($html);

        $author = '';
        $status = 'ongoing';
        $aliases = '';
        $latest = 0;

        if (preg_match('/(?:^|\n)Author\s*:?\s*(.+?)(?:\n|$)/iu', $text, $m)) {
            $author = nc_norm($m[1]);
        }

        if (preg_match('/(?:^|\n)Status\s*:?\s*(.+?)(?:\n|$)/iu', $text, $m)) {
            $st = strtolower(nc_norm($m[1]));
            $status = str_contains($st, 'complete') ? 'completed' : 'ongoing';
        }

        if (preg_match('/(?:^|\n)(?:Alternative|Alternative Title|Alternative Titles|Alt Title|Aliases?)\s*:?\s*(.+?)(?:\n|$)/iu', $text, $m)) {
            $aliases = nc_norm($m[1]);
        }

        foreach ($xpath->query('//a[@href]') as $a) {
            $href = trim((string)$a->getAttribute('href'));
            $full = nc_absu($href, 'https://woopread.com');
            if (preg_match('~https?://(?:www\.)?woopread\.com/series/[^/]+/chapter-([0-9]+)~i', $full, $m)) {
                $latest = max($latest, (int)$m[1]);
            }
        }

        return [
            'title' => nc_norm($title),
            'description' => nc_norm($description),
            'cover' => nc_absu($cover, 'https://woopread.com'),
            'author' => $author,
            'aliases' => $aliases,
            'status' => $status,
            'latest' => $latest,
            'url' => rtrim($url, '/'),
        ];
    }

    function nw_parse_visible_chapters(string $html): array {
        [$xpath, ] = nc_xp($html);

        $rows = [];
        $seen = [];

        foreach ($xpath->query('//a[@href]') as $a) {
            $href = trim((string)$a->getAttribute('href'));
            $title = nc_norm(trim($a->textContent));

            if ($href === '') {
                continue;
            }

            $full = nc_absu($href, 'https://woopread.com');

            if (!preg_match('~https?://(?:www\.)?woopread\.com/series/([^/]+)/chapter-([0-9]+)~i', $full, $m)) {
                continue;
            }

            $chapterNumber = (int)$m[2];
            if ($chapterNumber < 1) {
                continue;
            }

            if (isset($seen[$chapterNumber])) {
                continue;
            }
            $seen[$chapterNumber] = true;

            if ($title === '' || strcasecmp($title, 'Read Now') === 0) {
                $title = 'Chapter ' . $chapterNumber;
            }

            $title = preg_replace('/\s+\d+\s*(months?|days?|hours?|minutes?)\s+ago$/i', '', $title);
            $title = trim((string)$title);

            $rows[$chapterNumber] = [
                'chapter_number' => $chapterNumber,
                'source_url' => $full,
                'title' => $title !== '' ? $title : ('Chapter ' . $chapterNumber),
            ];
        }

        return $rows;
    }

    function nw_build_full_chapters(string $detailUrl, array $visibleRows, int $latest): array {
        $detailUrl = rtrim($detailUrl, '/');
        $rows = [];

        if ($latest < 1) {
            $latest = 1;
        }

        for ($i = 1; $i <= $latest; $i++) {
            $rows[] = [
                'chapter_number' => $i,
                'source_url' => $visibleRows[$i]['source_url'] ?? ($detailUrl . '/chapter-' . $i),
                'title' => $visibleRows[$i]['title'] ?? ('Chapter ' . $i),
            ];
        }

        return $rows;
    }

    function nw_parse_chapter(string $html): array {
        [$xpath, $dom] = nc_xp($html);

        $pageTitle = '';
        $titleNode = $xpath->query('//title')->item(0);
        if ($titleNode) {
            $pageTitle = nc_norm($titleNode->textContent);
            $pageTitle = preg_replace('/\s*\|\s*WoopRead.*$/i', '', $pageTitle);
        }

        $candidateHtml = '';

        $queries = [
            '//*[contains(@class,"entry-content")]',
            '//*[contains(@class,"chapter-content")]',
            '//*[contains(@class,"reading-content")]',
            '//*[contains(@class,"text-left")]',
            '//article',
            '//main',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length) {
                foreach ($nodes as $node) {
                    $htmlPart = '';
                    foreach ($node->childNodes as $child) {
                        $htmlPart .= $dom->saveHTML($child);
                    }

                    $txt = nc_bodytxt($htmlPart);
                    if (mb_strlen($txt, 'UTF-8') > 300) {
                        $candidateHtml = $htmlPart;
                        break 2;
                    }
                }
            }
        }

        if ($candidateHtml === '') {
            $candidateHtml = $html;
        }

        $text = nc_bodytxt($candidateHtml);

        $ignore = [
            'Prev',
            'Next',
            'Previous',
            'Comments',
            'Bookmark',
            'Report',
            'Table of Contents',
            'Home',
            'WoopRead',
        ];

        $stop = [
            'Leave a Reply',
            'Recommended Series',
            'You may also like',
            'Comments',
        ];

        $out = [];
        foreach (preg_split('/\n/u', $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                $out[] = '';
                continue;
            }

            if ($pageTitle !== '' && $line === $pageTitle) {
                continue;
            }

            if (in_array($line, $ignore, true)) {
                continue;
            }

            if (preg_match('/^Chapter\s+\d+$/iu', $line) && $pageTitle !== '' && stripos($pageTitle, $line) !== false) {
                continue;
            }

            $halt = false;
            foreach ($stop as $marker) {
                if (stripos($line, $marker) !== false) {
                    $halt = true;
                    break;
                }
            }
            if ($halt) {
                break;
            }

            $out[] = $line;
        }

        while ($out && trim((string)$out[0]) === '') {
            array_shift($out);
        }
        while ($out && trim((string)$out[count($out) - 1]) === '') {
            array_pop($out);
        }

        $plain = nc_norm(implode("\n", $out));

        if (mb_strlen(strip_tags($plain), 'UTF-8') < 120) {
            return [
                'title' => $pageTitle,
                'content' => '',
            ];
        }

        return [
            'title' => $pageTitle,
            'content' => nc_build_html($plain),
        ];
    }

    function nw_sync_index(PDO $pdo, array $novel) {
        $detailUrl = trim((string)($novel['source_url'] ?? ''));

        if ($detailUrl === '') {
            nc_fail('Novel WoopRead belum punya source_url');
        }

        if (!preg_match('~^https?://(?:www\.)?woopread\.com/series/[^/]+/?$~i', $detailUrl)) {
            nc_fail('URL WoopRead tidak valid');
        }

        [$html, $err] = nc_gethtml($detailUrl, 30);
        if (!$html) {
            nc_fail('Gagal fetch detail WoopRead: ' . $err, 502);
        }

        $meta = nw_parse_meta($html, $detailUrl);
        $visibleRows = nw_parse_visible_chapters($html);

        $latest = (int)$meta['latest'];
        if ($latest < 1 && !empty($visibleRows)) {
            $latest = max(array_keys($visibleRows));
        }

        $chapterRows = nw_build_full_chapters($detailUrl, $visibleRows, $latest);

        $title = $meta['title'] ?: ($novel['title'] ?? 'Untitled Novel');
        $slug = nc_slugify($title);

        $saved = nc_save_novel($pdo, [
            'slug' => $slug,
            'title' => $title,
            'source' => 'woopread',
            'source_url' => rtrim($detailUrl, '/'),
            'poster_url' => $meta['cover'],
            'author' => $meta['author'],
            'status' => $meta['status'],
            'genres' => nc_jarr($novel['genres'] ?? '[]'),
            'tags' => nc_jarr($novel['tags'] ?? '[]'),
            'gender' => ($novel['gender'] ?? 'male') ?: 'male',
            'rating' => (float)($novel['rating'] ?? 0),
            'total_chapters' => $latest,
            'latest_chapter' => $latest,
            'description' => $meta['description'],
            'aliases' => $meta['aliases'],
            'country' => 'kr',
        ]);

        foreach ($chapterRows as $row) {
            nc_save_chapter($pdo, [
                'novel_id' => (int)$saved['id'],
                'chapter_number' => $row['chapter_number'],
                'title' => $row['title'],
                'source_url' => $row['source_url'],
            ]);
        }

        return nc_novel_by_slug($pdo, $saved['slug']) ?: $saved;
    }

    function nw_seed(PDO $pdo, array $query): array {
        $url = trim((string)($query['url'] ?? ''));
        if ($url === '') {
            nc_fail('url wajib diisi');
        }

        if (!preg_match('~^https?://(?:www\.)?woopread\.com/series/[^/]+/?$~i', $url)) {
            nc_fail('URL WoopRead tidak valid');
        }

        $seedSlug = basename(rtrim((string)parse_url($url, PHP_URL_PATH), '/'));

        $novel = nc_save_novel($pdo, [
            'slug' => nc_slugify($seedSlug),
            'title' => 'WoopRead Seed',
            'source' => 'woopread',
            'source_url' => rtrim($url, '/'),
            'status' => 'ongoing',
            'genres' => [],
            'tags' => [],
            'gender' => 'male',
            'rating' => 0,
            'total_chapters' => 0,
            'latest_chapter' => 0,
            'description' => '',
            'aliases' => '',
            'country' => 'kr',
        ]);

        $synced = nw_sync_index($pdo, $novel);
        $chapters = nc_chapter_rows($pdo, (int)$synced['id']);

        return [
            'id' => (int)$synced['id'],
            'slug' => $synced['slug'],
            'title' => $synced['title'],
            'total_chapters' => (int)$synced['total_chapters'],
            'latest_chapter' => (int)$synced['latest_chapter'],
            'indexed_chapters' => count($chapters),
            'source_url' => $synced['source_url'] ?? null,
            'source' => 'woopread',
        ];
    }

    function nw_sync_detail(PDO $pdo, array $novel): array {
        $synced = nw_sync_index($pdo, $novel);

        return [
            'id' => (int)$synced['id'],
            'slug' => $synced['slug'],
            'title' => $synced['title'],
            'source' => 'woopread',
        ];
    }

    function nw_chapter(PDO $pdo, array $novel, int $chapterNumber): array {
        $chapter = nc_find_chapter($pdo, (int)$novel['id'], $chapterNumber);

        if (!$chapter) {
            $novel = nw_sync_index($pdo, $novel);
            $chapter = nc_find_chapter($pdo, (int)$novel['id'], $chapterNumber);
        }

        if (!$chapter) {
            nc_fail('Chapter tidak ditemukan', 404);
        }

        $nav = nc_prev_next($pdo, (int)$novel['id'], $chapterNumber);

        if (!empty($chapter['content'])) {
            return [
                'chapter_number' => $chapterNumber,
                'title' => $chapter['title'] ?: ('Chapter ' . $chapterNumber),
                'content' => $chapter['content'],
                'prev' => $nav['prev'],
                'next' => $nav['next'],
            ];
        }

        $chapterUrl = trim((string)($chapter['source_url'] ?? ''));
        if ($chapterUrl === '') {
            nc_fail('Source URL chapter tidak ada', 500);
        }

        [$html, $err] = nc_gethtml($chapterUrl, 30);
        if (!$html) {
            nc_fail('Gagal fetch chapter WoopRead: ' . $err, 502);
        }

        $parsed = nw_parse_chapter($html);
        if (trim((string)$parsed['content']) === '') {
            nc_fail('Gagal parse isi chapter WoopRead', 500);
        }

        nc_save_chapter($pdo, [
            'novel_id' => (int)$novel['id'],
            'chapter_number' => $chapterNumber,
            'title' => $parsed['title'] ?: ($chapter['title'] ?: ('Chapter ' . $chapterNumber)),
            'source_url' => $chapterUrl,
            'content' => $parsed['content'],
            'cached_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'chapter_number' => $chapterNumber,
            'title' => $parsed['title'] ?: ($chapter['title'] ?: ('Chapter ' . $chapterNumber)),
            'content' => $parsed['content'],
            'prev' => $nav['prev'],
            'next' => $nav['next'],
        ];
    }
}
