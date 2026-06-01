<?php

if (!defined('NOVEL_COMMON_LOADED')) {
    define('NOVEL_COMMON_LOADED', true);

    @set_time_limit(180);
    libxml_use_internal_errors(true);

    function nc_db(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $pdo = new PDO(
            'mysql:host=mysql;dbname=streamify_db;charset=utf8mb4',
            'streamify_user',
            'rimbamobile2'
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    function nc_ok($data): void {
        echo json_encode(
            ['success' => true, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    function nc_fail(string $message, int $code = 400): void {
        http_response_code($code);
        echo json_encode(
            ['success' => false, 'error' => $message],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    function nc_cols(PDO $pdo, string $table): array {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $out = [];
        foreach ($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll() as $row) {
            $out[$row['Field']] = true;
        }

        $cache[$table] = $out;
        return $out;
    }

    function nc_hasc(PDO $pdo, string $table, string $column): bool {
        $cols = nc_cols($pdo, $table);
        return isset($cols[$column]);
    }

    function nc_jarr($value): array {
        if (is_array($value)) {
            return array_values($value);
        }
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    function nc_norm($text): string {
        $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);
        return trim((string)$text);
    }

    function nc_absu(string $url, string $base = ''): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if ($base !== '') {
            $base = rtrim($base, '/');
            if (str_starts_with($url, '/')) {
                $parts = parse_url($base);
                if (!empty($parts['scheme']) && !empty($parts['host'])) {
                    return $parts['scheme'] . '://' . $parts['host'] . $url;
                }
            }
            return $base . '/' . ltrim($url, '/');
        }

        return $url;
    }

    function nc_slugify(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = $ascii;
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string)$text, '-');

        return $text !== '' ? $text : 'novel-' . substr(md5((string)microtime(true)), 0, 10);
    }

    function nc_gethtml(string $url, int $timeout = 30): array {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,id;q=0.8',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache',
                ],
            ]);

            $body = curl_exec($ch);
            $error = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $code >= 400 || $code === 0) {
                return [null, $error ?: ('HTTP ' . $code)];
            }

            return [$body, null];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'User-Agent: ' . $ua,
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,id;q=0.8',
                ]),
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        return $body ? [$body, null] : [null, 'fetch failed'];
    }

    function nc_xp(string $html): array {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        return [new DOMXPath($dom), $dom];
    }

    function nc_meta(DOMXPath $xpath, string $attr, string $value): string {
        $node = $xpath->query("//meta[@$attr='$value']")->item(0);
        return $node ? trim((string)$node->getAttribute('content')) : '';
    }

    function nc_bodytxt(string $html): string {
        $html = preg_replace('#<head\b.*?</head>#is', '', $html);
        $html = preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|section|article|li|ul|ol|h1|h2|h3|h4|h5|h6|blockquote|tr)>#i', "\n\n", $html);

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\x{00A0}/u', ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim((string)$text);
    }

    function nc_build_html(string $text): string {
        $parts = preg_split('/\n{2,}/u', trim($text));
        $out = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $part = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out[] = '<p style="margin:0 0 1em 0">' . nl2br($part, false) . '</p>';
        }

        return implode("\n", $out);
    }

    function nc_novel_by_slug(PDO $pdo, string $slug) {
        $stmt = $pdo->prepare('SELECT * FROM novels WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    function nc_novel_by_source(PDO $pdo, string $source, ?int $sourceId = null, ?string $sourceUrl = null) {
        if ($sourceId !== null && nc_hasc($pdo, 'novels', 'source_id')) {
            $stmt = $pdo->prepare('SELECT * FROM novels WHERE source = ? AND source_id = ? LIMIT 1');
            $stmt->execute([$source, $sourceId]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        if ($sourceUrl !== null && $sourceUrl !== '' && nc_hasc($pdo, 'novels', 'source_url')) {
            $stmt = $pdo->prepare('SELECT * FROM novels WHERE source = ? AND source_url = ? LIMIT 1');
            $stmt->execute([$source, $sourceUrl]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    function nc_save_novel(PDO $pdo, array $data) {
        $cols = nc_cols($pdo, 'novels');

        $source = $data['source'] ?? 'mtlreader';
        $slug = $data['slug'] ?? nc_slugify($data['title'] ?? 'novel');
        $sourceId = $data['source_id'] ?? null;
        $sourceUrl = $data['source_url'] ?? null;

        $existing = nc_novel_by_source($pdo, $source, $sourceId, $sourceUrl);
        if (!$existing) {
            $existing = nc_novel_by_slug($pdo, $slug);
        }

        $payload = [
            'slug' => $slug,
            'title' => $data['title'] ?? '',
            'source' => $source,
            'source_id' => $sourceId,
            'source_url' => $sourceUrl,
            'poster_url' => $data['poster_url'] ?? '',
            'author' => $data['author'] ?? '',
            'status' => $data['status'] ?? 'ongoing',
            'genres' => json_encode($data['genres'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tags' => json_encode($data['tags'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'gender' => $data['gender'] ?? 'male',
            'rating' => $data['rating'] ?? 0,
            'total_chapters' => $data['total_chapters'] ?? 0,
            'latest_chapter' => $data['latest_chapter'] ?? 0,
            'description' => $data['description'] ?? '',
            'aliases' => $data['aliases'] ?? '',
            'country' => $data['country'] ?? 'jp',
        ];

        $fields = [];
        foreach ($payload as $k => $v) {
            if (isset($cols[$k])) {
                $fields[$k] = $v;
            }
        }

        if (isset($cols['updated_at'])) {
            $fields['updated_at'] = date('Y-m-d H:i:s');
        }
        if (!$existing && isset($cols['created_at'])) {
            $fields['created_at'] = date('Y-m-d H:i:s');
        }

        if ($existing) {
            $sets = [];
            $vals = [];
            foreach ($fields as $k => $v) {
                $sets[] = "`$k` = ?";
                $vals[] = $v;
            }
            $vals[] = $existing['id'];

            $pdo->prepare('UPDATE novels SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);

            return nc_novel_by_slug($pdo, $slug) ?: array_merge($existing, $fields);
        }

        $keys = array_keys($fields);
        $sql = 'INSERT INTO novels (`' . implode('`,`', $keys) . '`) VALUES (' . implode(', ', array_fill(0, count($keys), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($fields));

        return nc_novel_by_slug($pdo, $slug);
    }

    function nc_save_chapter(PDO $pdo, array $data): void {
        $cols = nc_cols($pdo, 'novel_chapters');
        $existing = null;

        if (isset($cols['source_chapter_id']) && !empty($data['source_chapter_id'])) {
            $stmt = $pdo->prepare('SELECT id FROM novel_chapters WHERE novel_id = ? AND source_chapter_id = ? LIMIT 1');
            $stmt->execute([$data['novel_id'], $data['source_chapter_id']]);
            $existing = $stmt->fetch();
        }

        if (!$existing) {
            $stmt = $pdo->prepare('SELECT id FROM novel_chapters WHERE novel_id = ? AND chapter_number = ? LIMIT 1');
            $stmt->execute([$data['novel_id'], $data['chapter_number']]);
            $existing = $stmt->fetch();
        }

        $payload = [
            'novel_id' => $data['novel_id'],
            'chapter_number' => $data['chapter_number'],
            'source_chapter_id' => $data['source_chapter_id'] ?? null,
            'title' => $data['title'] ?? ('Chapter ' . $data['chapter_number']),
            'content' => $data['content'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'cached_at' => $data['cached_at'] ?? null,
        ];

        $fields = [];
        foreach ($payload as $k => $v) {
            if (isset($cols[$k])) {
                $fields[$k] = $v;
            }
        }

        if ($existing) {
            $sets = [];
            $vals = [];
            foreach ($fields as $k => $v) {
                $sets[] = "`$k` = ?";
                $vals[] = $v;
            }
            $vals[] = $existing['id'];

            $pdo->prepare('UPDATE novel_chapters SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
            return;
        }

        $keys = array_keys($fields);
        $sql = 'INSERT INTO novel_chapters (`' . implode('`,`', $keys) . '`) VALUES (' . implode(', ', array_fill(0, count($keys), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($fields));
    }

    function nc_chapter_rows(PDO $pdo, int $novelId): array {
        $stmt = $pdo->prepare('SELECT id, chapter_number, title, source_url FROM novel_chapters WHERE novel_id = ? ORDER BY chapter_number ASC');
        $stmt->execute([$novelId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['chapter_number'] = (int)$row['chapter_number'];
        }

        return $rows;
    }

    function nc_find_chapter(PDO $pdo, int $novelId, int $chapterNumber) {
        $stmt = $pdo->prepare('SELECT * FROM novel_chapters WHERE novel_id = ? AND chapter_number = ? LIMIT 1');
        $stmt->execute([$novelId, $chapterNumber]);
        return $stmt->fetch();
    }

    function nc_prev_next(PDO $pdo, int $novelId, int $chapterNumber): array {
        $prevStmt = $pdo->prepare('SELECT chapter_number FROM novel_chapters WHERE novel_id = ? AND chapter_number < ? ORDER BY chapter_number DESC LIMIT 1');
        $nextStmt = $pdo->prepare('SELECT chapter_number FROM novel_chapters WHERE novel_id = ? AND chapter_number > ? ORDER BY chapter_number ASC LIMIT 1');

        $prevStmt->execute([$novelId, $chapterNumber]);
        $nextStmt->execute([$novelId, $chapterNumber]);

        $prev = $prevStmt->fetchColumn();
        $next = $nextStmt->fetchColumn();

        return [
            'prev' => $prev ? (int)$prev : null,
            'next' => $next ? (int)$next : null,
        ];
    }
}


if (!function_exists('nc_http_get')) {
    function nc_http_get(string $url, int $timeout = 60): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => $timeout,
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
        if ($html === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP GET gagal: ' . $err);
        }

        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            throw new RuntimeException('HTTP GET gagal dengan status ' . $code . ' untuk ' . $url);
        }

        if (!is_string($html) || trim($html) === '') {
            throw new RuntimeException('HTTP GET kosong untuk ' . $url);
        }

        return $html;
    }
}
