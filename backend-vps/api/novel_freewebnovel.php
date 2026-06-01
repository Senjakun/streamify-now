<?php
require_once __DIR__ . '/novel_common.php';

if (!function_exists('nf_http_get')) {
    function nf_http_get(string $url): string {
        if (function_exists('nc_http_get')) {
            return nc_http_get($url);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
            ],
        ]);

        $html = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html === false || $err) {
            throw new RuntimeException('HTTP fetch gagal: ' . $err);
        }

        if ($code >= 400 || trim($html) === '') {
            throw new RuntimeException('HTTP status tidak valid: ' . $code);
        }

        return $html;
    }
}

if (!function_exists('nf_summary_from_html')) {
    function nf_summary_from_html(string $html): string {
        $patterns = [
            '/<div[^>]*class="[^"]*m-desc[^"]*"[^>]*>.*?<h4[^>]*class="[^"]*abstract[^"]*"[^>]*>.*?SUMMARY.*?<\/h4>(.*?)<a[^>]*class="[^"]*more\s+js-show[^"]*"[^>]*>.*?See all/is',
            '/<div[^>]*class="[^"]*m-desc[^"]*"[^>]*>(.*?)<a[^>]*class="[^"]*more\s+js-show[^"]*"[^>]*>.*?See all/is',
            '/<h4[^>]*class="[^"]*abstract[^"]*"[^>]*>.*?SUMMARY.*?<\/h4>(.*?)<a[^>]*class="[^"]*more\s+js-show[^"]*"[^>]*>.*?See all/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $raw = $m[1] ?? '';
                $raw = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $raw);
                $raw = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $raw);
                $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw);
                $raw = strip_tags($raw);
                $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
                $raw = preg_replace('/\s+/u', ' ', $raw);
                $raw = preg_replace('/\s+(See all|Hide)\s*$/i', '', $raw);
                $raw = trim($raw);

                if (mb_strlen($raw, 'UTF-8') >= 220) {
                    return $raw;
                }
            }
        }

        return '';
    }
}


if (!function_exists('nf_full_summary')) {
    function nf_full_summary(DOMXPath $xpath): string {
        $best = '';

        $queries = [
            "//*[contains(@class,'m-desc')]//*[contains(@class,'txt')]",
            "//*[contains(@class,'m-desc')]//*[contains(@class,'inner')]",
            "//h4[contains(@class,'abstract')][contains(translate(normalize-space(.), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 'SUMMARY')]/following-sibling::*",
            "//*[self::h3 or self::h4][contains(translate(normalize-space(.), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 'SUMMARY')]/following-sibling::*",
            "//*[contains(@class,'summary')]",
            "//*[contains(@class,'desc')]",
            "//*[@id='summary']",
        ];

        foreach ($queries as $q) {
            $nodes = @$xpath->query($q);
            if (!$nodes || $nodes->length === 0) continue;

            $parts = [];
            foreach ($nodes as $node) {
                $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5)));
                if ($t === '') continue;

                if (
                    preg_match('/^(See all|Hide|Add to Library|Chapter list|Latest Chapters|Read first)$/i', $t) ||
                    preg_match('/^\d+\s+Latest Chapters/i', $t) ||
                    preg_match('/^Chapter List/i', $t)
                ) {
                    break;
                }

                $parts[] = $t;
            }

            $candidate = trim(implode(" ", $parts));
            $candidate = preg_replace('/\s+(See all|Hide)\s*$/i', '', $candidate);

            if (mb_strlen($candidate, 'UTF-8') > mb_strlen($best, 'UTF-8')) {
                $best = $candidate;
            }
        }

        return trim($best);
    }
}

if (!function_exists('nf_parse_detail')) {
    function nf_parse_detail(string $html, string $url, bool $includeChapters = true): array {
        [$dom, $xpath] = nc_dom($html);

        $title = nc_meta($xpath, 'property', 'og:title');
        if ($title === '') {
            $title = trim($xpath->evaluate("string(//title)"));
            $title = preg_replace('/\s+Novel\s*\|\s*Free Web Novel$/i', '', $title ?? '');
        }

        $description = nf_full_summary($xpath);
        if ($description === '') {
            $description = nc_meta($xpath, 'property', 'og:description');
        }
        if ($description === '') {
            $description = nc_meta($xpath, 'name', 'description');
        }

        $poster = nc_meta($xpath, 'property', 'og:image');
        $author = nc_meta($xpath, 'property', 'og:novel:author');
        $status = nc_meta($xpath, 'property', 'og:novel:status');
        $genreMeta = nc_meta($xpath, 'property', 'og:novel:genre');
        $genres = [];

        if ($genreMeta !== '') {
            foreach (preg_split('/\s*,\s*/', $genreMeta) as $g) {
                $g = nc_norm($g);
                if ($g !== '') $genres[] = $g;
            }
        }

        $country = 'cn';
        $category = nc_meta($xpath, 'property', 'og:novel:category');
        $cat = strtolower($category);
        if (strpos($cat, 'japanese') !== false) $country = 'jp';
        elseif (strpos($cat, 'korean') !== false) $country = 'kr';
        elseif (strpos($cat, 'chinese') !== false) $country = 'cn';

        $latestUrl = nc_meta($xpath, 'property', 'og:novel:lastest_chapter_url');
        $latestChapter = 0;
        if (preg_match('~/chapter-(\d+)$~i', $latestUrl, $m)) {
            $latestChapter = (int)$m[1];
        }

        $chapters = [];

        if ($includeChapters) {
            $nodes = @$xpath->query("//a[contains(@href,'/chapter-')]");
            if ($nodes) {
                $seen = [];
                foreach ($nodes as $a) {
                    $href = trim($a->getAttribute('href'));
                    if ($href === '') continue;

                    $full = nc_abs_url($href, $url);
                    if (!preg_match('~/chapter-(\d+)$~i', $full, $m)) continue;

                    $num = (int)$m[1];
                    if ($num < 1) continue;
                    if (isset($seen[$num])) continue;
                    $seen[$num] = true;

                    $chapters[] = [
                        'chapter_number' => $num,
                        'title' => nc_norm($a->textContent ?: ("Chapter " . $num)),
                        'source_url' => $full,
                    ];
                }
            }

            usort($chapters, fn($a, $b) => $a['chapter_number'] <=> $b['chapter_number']);

            if ($latestChapter < 1 && $chapters) {
                $latestChapter = (int)max(array_column($chapters, 'chapter_number'));
            }
        }

        return [
            'title' => nc_norm($title),
            'description' => nc_norm($description),
            'poster_url' => nc_norm($poster),
            'author' => nc_norm($author),
            'status' => strtolower(nc_norm($status)) === 'ongoing' ? 'ongoing' : 'completed',
            'genres' => array_values(array_unique($genres)),
            'country' => $country,
            'latest_chapter' => $latestChapter,
            'total_chapters' => $latestChapter > 0 ? $latestChapter : count($chapters),
            'chapters' => $chapters,
        ];
    }
}

if (!function_exists('nf_seed')) {
    function nf_seed(PDO $pdo, array $req): array {
        $url = trim((string)($req['url'] ?? ''));
        if ($url === '') nc_fail('url wajib diisi');

        $html = nf_http_get($url);
        $meta = nf_parse_detail($html, $url);

        $slug = nc_slug_from_url($url);
        $novelId = nc_upsert_novel($pdo, [
            'slug' => $slug,
            'title' => $meta['title'],
            'source' => 'freewebnovel',
            'source_id' => null,
            'source_url' => $url,
            'country' => $meta['country'],
            'poster_url' => $meta['poster_url'],
            'description' => $meta['description'],
            'aliases' => '',
            'author' => $meta['author'],
            'status' => $meta['status'],
            'genres' => $meta['genres'],
            'tags' => [],
            'gender' => 'male',
            'rating' => 0,
            'total_chapters' => $meta['total_chapters'],
            'latest_chapter' => $meta['latest_chapter'],
        ]);

        if (!empty($meta['chapters'])) {
            nc_replace_chapters($pdo, $novelId, $meta['chapters']);
        }

        return [
            'id' => $novelId,
            'slug' => $slug,
            'title' => $meta['title'],
            'total_chapters' => $meta['total_chapters'],
            'latest_chapter' => $meta['latest_chapter'],
            'indexed_chapters' => count($meta['chapters']),
            'source_url' => $url,
            'source' => 'freewebnovel',
        ];
    }
}

if (!function_exists('nf_sync_detail')) {
    function nf_sync_detail(PDO $pdo, array $novel): array {
        $url = trim((string)($novel['source_url'] ?? ''));
        if ($url === '') nc_fail('Novel FreeWebNovel belum punya source_url');

        $forceFullSync = !empty($novel['force_full_sync']);
        $existingTotal = (int)($novel['total_chapters'] ?? 0);
        $includeChapters = $forceFullSync || $existingTotal < 3000;

        $html = nf_http_get($url);
        $meta = nf_parse_detail($html, $url, $includeChapters);

        nc_upsert_novel($pdo, [
            'id' => (int)$novel['id'],
            'slug' => $novel['slug'],
            'title' => $meta['title'],
            'source' => 'freewebnovel',
            'source_id' => null,
            'source_url' => $url,
            'country' => $meta['country'],
            'poster_url' => $meta['poster_url'],
            'description' => $meta['description'],
            'aliases' => '',
            'author' => $meta['author'],
            'status' => $meta['status'],
            'genres' => $meta['genres'],
            'tags' => [],
            'gender' => $novel['gender'] ?? 'male',
            'rating' => $novel['rating'] ?? 0,
            'total_chapters' => $meta['total_chapters'],
            'latest_chapter' => $meta['latest_chapter'],
        ]);

        if ($includeChapters && !empty($meta['chapters'])) {
            nc_replace_chapters($pdo, (int)$novel['id'], $meta['chapters']);
        }

        return [
            'id' => (int)$novel['id'],
            'slug' => $novel['slug'],
            'title' => $meta['title'],
            'description' => $meta['description'],
            'total_chapters' => $meta['total_chapters'],
            'latest_chapter' => $meta['latest_chapter'],
            'indexed_chapters' => count($meta['chapters']),
            'chapter_mode' => $includeChapters ? 'full' : 'meta_only',
        ];
    }
}

if (!function_exists('nf_chapter')) {
    function nf_chapter(PDO $pdo, array $novel, int $chapterNumber): array {
        $row = nc_find_chapter($pdo, (int)$novel['id'], $chapterNumber);

        if (!$row) {
            nf_sync_detail($pdo, $novel);
            $row = nc_find_chapter($pdo, (int)$novel['id'], $chapterNumber);
        }

        if (!$row) nc_fail('Chapter tidak ditemukan', 404);

        $html = nf_http_get($row['source_url']);
        [$dom, $xpath] = nc_dom($html);

        $title = trim($xpath->evaluate("string(//title)"));
        $paras = @$xpath->query("//p");
        $parts = [];

        if ($paras) {
            foreach ($paras as $p) {
                $t = trim(html_entity_decode($p->textContent ?? '', ENT_QUOTES | ENT_HTML5));
                if ($t === '') continue;

                if (
                    preg_match('/^(Previous Chapter|Next Chapter|Chapter List|Latest Chapters|See all|Hide)$/i', $t)
                ) {
                    continue;
                }

                $parts[] = '<p style="margin:0 0 1em 0">' . htmlspecialchars($t, ENT_QUOTES | ENT_HTML5) . '</p>';
            }
        }

        $content = implode("\n", $parts);
        if ($content === '') {
            $content = '<p style="margin:0 0 1em 0">Konten chapter kosong.</p>';
        }

        nc_cache_chapter_content($pdo, (int)$row['id'], $content);

        $prev = nc_prev_chapter_number($pdo, (int)$novel['id'], $chapterNumber);
        $next = nc_next_chapter_number($pdo, (int)$novel['id'], $chapterNumber);

        return [
            'chapter_number' => $chapterNumber,
            'title' => $title !== '' ? $title : ("Chapter " . $chapterNumber),
            'content' => $content,
            'prev' => $prev ? (int)$prev : null,
            'next' => $next ? (int)$next : null,
        ];
    }
}
