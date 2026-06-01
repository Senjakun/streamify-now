<?php

if (!defined('NOVEL_MTLREADER_LOADED')) {
    define('NOVEL_MTLREADER_LOADED', true);

    function nm_parse_meta(string $html, string $url): array {
        [$xpath, ] = nc_xp($html);

        $title = nc_meta($xpath, 'property', 'og:title') ?: nc_meta($xpath, 'name', 'twitter:title');
        $title = preg_replace('/^MTL Reader\s*\|\s*/i', '', (string)$title);

        if ($title === '') {
            $node = $xpath->query('//h1')->item(0);
            $title = $node ? trim($node->textContent) : '';
        }

        $description = nc_meta($xpath, 'name', 'description');
        if ($description !== '' && $title !== '') {
            $description = preg_replace('/^' . preg_quote($title, '/') . '\s*[-–—]\s*/u', '', $description);
        }

        $cover = nc_meta($xpath, 'property', 'og:image');
        if ($cover === '') {
            $img = $xpath->query('//img[contains(@src,"cover_images") or contains(@src,"/img/frontend/")]')->item(0);
            $cover = $img ? nc_absu($img->getAttribute('src'), 'https://mtlreader.com') : '';
        }

        $text = nc_bodytxt($html);

        preg_match('/(?:^|\n)Author:\s*(.+?)(?:\n|$)/u', $text, $mAuthor);
        preg_match('/(?:^|\n)Chapters:\s*(\d+)(?:\n|$)/u', $text, $mChapters);
        preg_match('/(?:^|\n)Aliases:\s*(.+?)(?:\n|$)/u', $text, $mAliases);

        $maxPages = 1;
        foreach ($xpath->query('//a[@href]') as $a) {
            $txt = trim($a->textContent);
            if (ctype_digit($txt)) {
                $maxPages = max($maxPages, (int)$txt);
            }
        }

        return [
            'title' => nc_norm($title),
            'description' => nc_norm($description),
            'cover' => nc_absu($cover, 'https://mtlreader.com'),
            'author' => nc_norm($mAuthor[1] ?? ''),
            'aliases' => nc_norm($mAliases[1] ?? ''),
            'total' => (int)($mChapters[1] ?? 0),
            'pages' => $maxPages,
            'url' => $url,
        ];
    }

    function nm_parse_index_chapters(string $html): array {
        [$xpath, ] = nc_xp($html);

        $rows = [];
        $seen = [];

        foreach ($xpath->query('//a[@href]') as $a) {
            $href = trim((string)$a->getAttribute('href'));
            $title = nc_norm(trim($a->textContent));

            if ($href === '' || $title === '') {
                continue;
            }

            $full = nc_absu($href, 'https://mtlreader.com');

            if (!preg_match('~https?://(?:www\.)?mtlreader\.com/novels/(\d+)/chapters/(\d+)~i', $full, $m)) {
                continue;
            }

            if (in_array($title, ['Back', 'Table of Contents', 'Next'], true)) {
                continue;
            }

            $chapterId = (int)$m[2];
            if (isset($seen[$chapterId])) {
                continue;
            }
            $seen[$chapterId] = true;

            $rows[] = [
                'source_chapter_id' => $chapterId,
                'source_url' => $full,
                'title' => $title,
            ];
        }

        return $rows;
    }

    function nm_parse_chapter(string $html): array {
        [$xpath, ] = nc_xp($html);

        $pageTitle = '';
        $titleNode = $xpath->query('//title')->item(0);
        if ($titleNode) {
            $pageTitle = nc_norm($titleNode->textContent);
            $pageTitle = preg_replace('/^Read\s+/i', '', $pageTitle);
            if (preg_match('/\|\s*(.+)$/u', $pageTitle, $m)) {
                $pageTitle = trim($m[1]);
            }
        }

        $text = nc_bodytxt($html);

        $stopMarkers = [
            'Adblock Enabled',
            'This site is primarily supported by ads.',
            'DMCA',
            'Terms and Condition',
            'Privacy Policy',
        ];

        $ignoreLines = [
            'MTLREADER',
            'NOVELS',
            'ABOUT',
            'DISCORD',
            'REQUEST',
            'Back',
            'Table of Contents',
            'Next',
            'Close',
            '×',
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

            if (in_array($line, $ignoreLines, true)) {
                continue;
            }

            if (preg_match('/^Read\s+.+\|/u', $line)) {
                continue;
            }

            if (preg_match('/^(Chapter Name Updated|Author:|Chapters:|Aliases:)/u', $line)) {
                continue;
            }

            $halt = false;
            foreach ($stopMarkers as $marker) {
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

    function nm_sync_index(PDO $pdo, array $novel) {
        $detailUrl = trim((string)($novel['source_url'] ?? ''));
        $sourceId = null;

        if ($detailUrl === '' && !empty($novel['source_id'])) {
            $sourceId = (int)$novel['source_id'];
            $detailUrl = 'https://mtlreader.com/novels/' . $sourceId;
        }

        if ($detailUrl === '') {
            nc_fail('Novel MTLReader belum punya source_url');
        }

        if ($sourceId === null && preg_match('~mtlreader\.com/novels/(\d+)~i', $detailUrl, $m)) {
            $sourceId = (int)$m[1];
        }

        [$html, $err] = nc_gethtml($detailUrl, 30);
        if (!$html) {
            nc_fail('Gagal fetch detail MTLReader: ' . $err, 502);
        }

        $meta = nm_parse_meta($html, $detailUrl);
        $allChapters = nm_parse_index_chapters($html);

        for ($page = 2; $page <= max(1, (int)$meta['pages']); $page++) {
            [$pageHtml, $pageErr] = nc_gethtml($detailUrl . '?page=' . $page, 30);
            if ($pageHtml) {
                $allChapters = array_merge($allChapters, nm_parse_index_chapters($pageHtml));
            }
        }

        $uniq = [];
        $chapterRows = [];

        foreach ($allChapters as $row) {
            $key = $row['source_chapter_id'] ?: md5($row['source_url']);
            if (isset($uniq[$key])) {
                continue;
            }
            $uniq[$key] = true;
            $chapterRows[] = $row;
        }

        $title = $meta['title'] ?: ($novel['title'] ?? 'Untitled Novel');
        $slug = nc_slugify($title);

        $saved = nc_save_novel($pdo, [
            'slug' => $slug,
            'title' => $title,
            'source' => 'mtlreader',
            'source_id' => $sourceId,
            'source_url' => $sourceId ? ('https://mtlreader.com/novels/' . $sourceId) : $detailUrl,
            'poster_url' => $meta['cover'],
            'author' => $meta['author'],
            'status' => ($novel['status'] ?? 'ongoing') ?: 'ongoing',
            'genres' => nc_jarr($novel['genres'] ?? '[]'),
            'tags' => nc_jarr($novel['tags'] ?? '[]'),
            'gender' => ($novel['gender'] ?? 'male') ?: 'male',
            'rating' => (float)($novel['rating'] ?? 0),
            'total_chapters' => count($chapterRows) ?: $meta['total'],
            'latest_chapter' => count($chapterRows) ?: $meta['total'],
            'description' => $meta['description'],
            'aliases' => $meta['aliases'],
            'country' => 'jp',
        ]);

        $chapterNumber = 1;
        foreach ($chapterRows as $row) {
            nc_save_chapter($pdo, [
                'novel_id' => (int)$saved['id'],
                'chapter_number' => $chapterNumber,
                'source_chapter_id' => $row['source_chapter_id'] ?? null,
                'title' => $row['title'],
                'source_url' => $row['source_url'],
            ]);
            $chapterNumber++;
        }

        return nc_novel_by_slug($pdo, $saved['slug']) ?: $saved;
    }

    function nm_seed(PDO $pdo, array $query): array {
        $sourceId = trim((string)($query['source_id'] ?? ''));
        $url = trim((string)($query['url'] ?? ''));

        if ($url === '' && $sourceId === '') {
            nc_fail('source_id atau url wajib diisi');
        }

        if ($url === '') {
            $url = 'https://mtlreader.com/novels/' . (int)$sourceId;
        }

        if (!preg_match('~^https?://(?:www\.)?mtlreader\.com/novels/(\d+)(?:\?.*)?$~i', $url, $m)) {
            nc_fail('URL MTLReader tidak valid');
        }

        $sourceId = (int)$m[1];

        $novel = nc_save_novel($pdo, [
            'slug' => 'mtlreader-' . $sourceId,
            'title' => 'MTLReader ' . $sourceId,
            'source' => 'mtlreader',
            'source_id' => $sourceId,
            'source_url' => 'https://mtlreader.com/novels/' . $sourceId,
            'status' => 'ongoing',
            'genres' => [],
            'tags' => [],
            'gender' => 'male',
            'rating' => 0,
            'total_chapters' => 0,
            'latest_chapter' => 0,
            'description' => '',
            'aliases' => '',
            'country' => 'jp',
        ]);

        $synced = nm_sync_index($pdo, $novel);
        $chapters = nc_chapter_rows($pdo, (int)$synced['id']);

        return [
            'id' => (int)$synced['id'],
            'slug' => $synced['slug'],
            'title' => $synced['title'],
            'total_chapters' => count($chapters),
            'latest_chapter' => count($chapters),
            'source_url' => $synced['source_url'] ?? null,
            'source' => 'mtlreader',
        ];
    }

    function nm_sync_detail(PDO $pdo, array $novel): array {
        $synced = nm_sync_index($pdo, $novel);

        return [
            'id' => (int)$synced['id'],
            'slug' => $synced['slug'],
            'title' => $synced['title'],
            'source' => 'mtlreader',
        ];
    }

    function nm_chapter(PDO $pdo, array $novel, int $chapterNumber): array {
        $chapter = nc_find_chapter($pdo, (int)$novel['id'], $chapterNumber);

        if (!$chapter) {
            $novel = nm_sync_index($pdo, $novel);
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

        if ($chapterUrl === '' && !empty($chapter['source_chapter_id']) && !empty($novel['source_url'])) {
            $chapterUrl = rtrim((string)$novel['source_url'], '/') . '/chapters/' . (int)$chapter['source_chapter_id'];
        }

        if ($chapterUrl === '') {
            nc_fail('Source URL chapter tidak ada', 500);
        }

        [$html, $err] = nc_gethtml($chapterUrl, 30);
        if (!$html) {
            nc_fail('Gagal fetch chapter MTLReader: ' . $err, 502);
        }

        $parsed = nm_parse_chapter($html);
        if (trim((string)$parsed['content']) === '') {
            nc_fail('Gagal parse isi chapter MTLReader', 500);
        }

        nc_save_chapter($pdo, [
            'novel_id' => (int)$novel['id'],
            'chapter_number' => $chapterNumber,
            'source_chapter_id' => $chapter['source_chapter_id'] ?? null,
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
