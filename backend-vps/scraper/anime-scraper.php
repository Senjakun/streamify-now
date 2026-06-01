<?php
require_once __DIR__ . '/../config/database.php';

class AnimeScraper {
    private $pdo;
    private $baseUrl = 'https://otakudesu.live';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function __construct() { $this->pdo = getDB(); }

    private function fetch($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: id-ID,id;q=0.9',
                'Referer: https://www.google.com/',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) throw new Exception("cURL Error: $error");
        if ($httpCode >= 400) throw new Exception("HTTP Error: $httpCode");
        return $response;
    }

    private function generateSlug($title) {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Scrape daftar anime dari halaman list (ongoing/completed)
     */
    private function scrapeList($url, $status) {
        $html = $this->fetch($url);

        // Ambil semua link anime
        preg_match_all('/href="(\/anime\/[^"]+)"/', $html, $linkMatches);
        // Ambil semua gambar
        preg_match_all('/<img[^>]+src="([^"]+otakudesu[^"]+)"[^>]*>/', $html, $imgMatches);
        // Ambil semua judul
        preg_match_all('/class="poster-title"[^>]*>([^<]+)<\/p>/', $html, $titleMatches);

        $links = array_unique($linkMatches[1]);
        $imgs = $imgMatches[1];
        $titles = $titleMatches[1];

        $found = 0; $added = 0;
        foreach ($titles as $i => $title) {
            $title = trim($title);
            $link = isset($links[$i]) ? $this->baseUrl . $links[$i] : null;
            $img = isset($imgs[$i]) ? $imgs[$i] : null;

            if (!$title || !$link) continue;
            $found++;
            $slug = $this->generateSlug($title);

            $stmt = $this->pdo->prepare("SELECT id FROM content WHERE slug = ? OR source_url = ?");
            $stmt->execute([$slug, $link]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update
                $stmt = $this->pdo->prepare("UPDATE content SET title=?, status=?, poster_url=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$title, $status, $img, $existing['id']]);
                $contentId = $existing['id'];
            } else {
                // Insert
                $stmt = $this->pdo->prepare("INSERT INTO content (slug, title, type, status, poster_url, source_url) VALUES (?, ?, 'anime', ?, ?, ?)");
                $stmt->execute([$slug, $title, $status, $img, $link]);
                $contentId = $this->pdo->lastInsertId();
                $added++;
            }

            // Panggil scrapeDetail untuk mengambil informasi tambahan (sinopsis, rating, episode)
            try {
                $this->scrapeDetail($link, $contentId);
            } catch (Exception $e) {
                error_log("Gagal scrape detail untuk $link: " . $e->getMessage());
            }
        }
        return ['found' => $found, 'added' => $added];
    }

    /**
     * Scrape detail anime (sinopsis, rating, episode list)
     */
    public function scrapeDetail($url, $contentId = null) {
        $html = $this->fetch($url);
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        if (!$contentId) {
            $stmt = $this->pdo->prepare("SELECT id FROM content WHERE source_url = ?");
            $stmt->execute([$url]);
            $row = $stmt->fetch();
            if (!$row) throw new Exception("Content not found for URL: $url");
            $contentId = $row['id'];
        }

        // Sinopsis
        $description = '';
        $metaDesc = $xpath->query("//meta[@property='og:description']")->item(0);
        if ($metaDesc) {
            $description = $metaDesc->getAttribute('content');
        } else {
            $sinopsisDiv = $xpath->query("//div[contains(@class, 'sinopsis')]//p")->item(0);
            if ($sinopsisDiv) $description = trim($sinopsisDiv->textContent);
        }

        // Rating
        $rating = 0;
        $ratingMeta = $xpath->query("//meta[@itemprop='ratingValue']")->item(0);
        if ($ratingMeta) {
            $rating = floatval($ratingMeta->getAttribute('content'));
        } else {
            $ratingSpan = $xpath->query("//span[contains(@class, 'rating')]")->item(0);
            if ($ratingSpan) $rating = floatval(trim($ratingSpan->textContent));
        }

        // Genre
        $genres = [];
        $genreLinks = $xpath->query("//a[contains(@href, 'genre')]");
        foreach ($genreLinks as $link) {
            $genres[] = trim($link->textContent);
        }

        // Update content
        $stmt = $this->pdo->prepare("UPDATE content SET description = ?, rating = ?, genres = ? WHERE id = ?");
        $stmt->execute([$description, $rating, json_encode($genres), $contentId]);

        // Ambil daftar episode
        $episodeNodes = $xpath->query("//a[contains(@href, '/episodes/')]");
        $episodes = [];
        foreach ($episodeNodes as $node) {
            $epUrl = $node->getAttribute('href');
            if (strpos($epUrl, 'http') !== 0) $epUrl = $this->baseUrl . $epUrl;
            $epTitle = trim($node->textContent);
            if (empty($epTitle)) continue;

            $epNum = null;
            if (preg_match('/Episode\s*([\d.]+)/i', $epTitle, $matches)) {
                $epNum = floatval($matches[1]);
            } elseif (preg_match('/episode-([\d.]+)/i', $epUrl, $matches)) {
                $epNum = floatval($matches[1]);
            }

            if ($epNum !== null) {
                // Simpan ke tabel episodes (pastikan tabel episodes sudah dibuat)
                $stmtEp = $this->pdo->prepare("
                    INSERT INTO episodes (content_id, episode_number, title, source_url)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE title = VALUES(title)
                ");
                $stmtEp->execute([$contentId, $epNum, $epTitle, $epUrl]);
            }
        }

        return $contentId;
    }

    public function scrapeOngoing($page = 1) {
        $url = "{$this->baseUrl}/anime?status=ongoing&page={$page}";
        try {
            $result = $this->scrapeList($url, 'ongoing');
            $this->log('AnimeScraper', $url, 'success', $result['found'], $result['added']);
            return $result;
        } catch (Exception $e) {
            $this->log('AnimeScraper', $url, 'failed', 0, 0, $e->getMessage());
            throw $e;
        }
    }

    public function scrapeCompleted($page = 1) {
        $url = "{$this->baseUrl}/anime?status=complete&page={$page}";
        try {
            $result = $this->scrapeList($url, 'completed');
            $this->log('AnimeScraper', $url, 'success', $result['found'], $result['added']);
            return $result;
        } catch (Exception $e) {
            $this->log('AnimeScraper', $url, 'failed', 0, 0, $e->getMessage());
            throw $e;
        }
    }

    private function log($source, $url, $status, $found=0, $added=0, $error=null) {
        $stmt = $this->pdo->prepare("INSERT INTO scrape_logs (source_name,source_url,status,items_found,items_added,error_message,started_at,completed_at) VALUES (?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([$source,$url,$status,$found,$added,$error]);
    }
}

if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'help';
    $scraper = new AnimeScraper();
    switch ($action) {
        case 'ongoing':
            $page = (int)($argv[2] ?? 1);
            $result = $scraper->scrapeOngoing($page);
            echo "Ongoing Anime: {$result['found']} found, {$result['added']} added\n";
            break;
        case 'completed':
            $page = (int)($argv[2] ?? 1);
            $result = $scraper->scrapeCompleted($page);
            echo "Completed Anime: {$result['found']} found, {$result['added']} added\n";
            break;
        case 'detail':
            $url = $argv[2] ?? '';
            if ($url) {
                $id = $scraper->scrapeDetail($url);
                echo "Scraped anime detail, ID: $id\n";
            } else {
                echo "Usage: php anime-scraper.php detail <url>\n";
            }
            break;
        default:
            echo "Usage: php anime-scraper.php [ongoing|completed|detail]\n";
    }
}
