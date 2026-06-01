<?php
/**
 * Manga Scraper - v5.kiryuu.to (Final + AJAX Chapters + JS Fallback + YuuCDN + Novel Filter + UqniCDN)
 */

require_once __DIR__ . '/../config/database.php';

class MangaScraper {
    private $pdo;
    private $baseUrl = 'https://v5.kiryuu.to';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    private function fetch($url) {
        $context = stream_context_create([
            'http' => [
                'header' => 
                    "User-Agent: {$this->userAgent}\r\n" .
                    "Accept: text/html\r\n" .
                    "Referer: {$this->baseUrl}/\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            throw new Exception("Failed to fetch: $url");
        }
        return $html;
    }
    
    private function parseHTML($html) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        return $dom;
    }
    
    private function xpath($dom, $query, $context = null) {
        $xpath = new DOMXPath($dom);
        return $xpath->query($query, $context);
    }
    
    private function generateSlug($title) {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
    
    /**
     * Ambil data manga dari elemen link, dengan filter novel
     */
    private function extractMangaFromLink($dom, $linkNode) {
        $href = $linkNode->getAttribute('href');
        // Skip jika URL mengandung /novel/
        if (strpos($href, '/novel/') !== false) {
            return null;
        }
        if (strpos($href, 'http') !== 0) {
            $href = $this->baseUrl . $href;
        }
        
        $imgNodes = $this->xpath($dom, ".//img", $linkNode);
        if ($imgNodes->length === 0) return null;
        $img = $imgNodes->item(0);
        
        $title = trim($img->getAttribute('alt'));
        // Skip jika judul mengandung (Novel)
        if (strpos($title, '(Novel)') !== false) {
            return null;
        }
        if (empty($title)) {
            $slug = basename(parse_url($href, PHP_URL_PATH));
            $title = ucwords(str_replace('-', ' ', $slug));
        }
        
        $poster = $img->getAttribute('src');
        if ($poster && strpos($poster, 'http') !== 0) {
            $poster = $this->baseUrl . $poster;
        }
        
        return [
            'title' => $title,
            'poster_url' => $poster,
            'source_url' => $href,
            'status' => 'ongoing'
        ];
    }
    
    public function scrapeList($url) {
        try {
            $html = $this->fetch($url);
            $dom = $this->parseHTML($html);
            
            $links = $this->xpath($dom, "//a[contains(@href, '/manga/')]");
            $found = 0;
            $added = 0;
            $processed = [];
            
            foreach ($links as $link) {
                $data = $this->extractMangaFromLink($dom, $link);
                if (!$data) continue;
                
                if (in_array($data['source_url'], $processed)) continue;
                $processed[] = $data['source_url'];
                
                $found++;
                $contentId = $this->saveManga($data);
                if ($contentId) $added++;
            }
            
            $this->log('MangaScraper', $url, 'success', $found, $added);
            return ['found' => $found, 'added' => $added];
            
        } catch (Exception $e) {
            $this->log('MangaScraper', $url, 'failed', 0, 0, $e->getMessage());
            throw $e;
        }
    }
    
    public function scrapeLatest($page = 1) {
        $url = $page > 1 ? "{$this->baseUrl}/page/{$page}/" : $this->baseUrl;
        return $this->scrapeList($url);
    }
    
    public function scrapeByOrder($orderby = 'update', $page = 1) {
        $url = $page > 1 ? "{$this->baseUrl}/manga/page/{$page}/?orderby={$orderby}" : "{$this->baseUrl}/manga/?orderby={$orderby}";
        return $this->scrapeList($url);
    }
    
    /**
     * Ambil daftar chapter via AJAX
     */
    private function fetchChapters($manga_id) {
        $chapters = [];
        $page = 1;
        do {
            $url = "{$this->baseUrl}/wp-admin/admin-ajax.php?manga_id={$manga_id}&page={$page}&action=chapter_list";
            $html = $this->fetch($url);
            if (empty($html)) break;
            
            $dom = $this->parseHTML($html);
            $xpath = new DOMXPath($dom);
            
            // Cari semua elemen div dengan data-chapter-number
            $chapterDivs = $xpath->query("//div[@data-chapter-number]");
            if ($chapterDivs->length === 0) break;
            
            foreach ($chapterDivs as $div) {
                // Cari link di dalam div
                $link = $xpath->query(".//a", $div)->item(0);
                if (!$link) continue;
                
                $chUrl = $link->getAttribute('href');
                if (strpos($chUrl, 'http') !== 0) {
                    $chUrl = $this->baseUrl . $chUrl;
                }
                
                // Ambil judul dari teks link atau dari atribut
                $chTitle = trim($link->textContent);
                if (empty($chTitle)) {
                    // Coba ambil dari span
                    $titleSpan = $xpath->query(".//span[contains(@class, 'font-medium')]", $div)->item(0);
                    $chTitle = $titleSpan ? trim($titleSpan->textContent) : '';
                }
                
                // Ekstrak nomor chapter
                $chNum = null;
                if (preg_match('/(?:Chapter|Ch\.?)\s*([\d.]+)/i', $chTitle, $matches)) {
                    $chNum = floatval($matches[1]);
                } else {
                    // Fallback dari URL
                    if (preg_match('/chapter[.-]?([\d.]+)/i', $chUrl, $matches)) {
                        $chNum = floatval($matches[1]);
                    }
                }
                
                if ($chNum !== null && !empty($chUrl)) {
                    $chapters[$chUrl] = [
                        'chapter_number' => $chNum,
                        'title' => $chTitle ?: "Chapter " . $chNum,
                        'source_url' => $chUrl
                    ];
                }
            }
            
            // Cek apakah ada link ke halaman berikutnya
            $nextLink = $xpath->query("//a[contains(@class, 'next') or contains(text(), 'Next')]")->item(0);
            if (!$nextLink) break;
            $page++;
        } while (true);
        
        // Urutkan berdasarkan nomor chapter (ascending)
        usort($chapters, function($a, $b) {
            return ($a['chapter_number'] ?? 0) <=> ($b['chapter_number'] ?? 0);
        });
        
        return $chapters;
    }
    
    public function scrapeDetail($url) {
        try {
            $html = $this->fetch($url);
            $dom = $this->parseHTML($html);
            $xpath = new DOMXPath($dom);
            
            // Judul
            $titleNode = $xpath->query("//h1")->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';
            
            // Sinopsis dari meta description
            $metaDesc = $xpath->query("//meta[@property='og:description']")->item(0);
            $description = $metaDesc ? $metaDesc->getAttribute('content') : '';
            
            // Poster dari meta image
            $metaImage = $xpath->query("//meta[@property='og:image']")->item(0);
            $poster = $metaImage ? $metaImage->getAttribute('content') : '';
            
            // Rating
            $rating = 0;
            $ratingMeta = $xpath->query("//meta[@itemprop='ratingValue']")->item(0);
            if ($ratingMeta) {
                $rating = floatval($ratingMeta->getAttribute('content'));
            } else {
                $ratingSpan = $xpath->query("//span[contains(@class, 'text-transparent') and contains(@class, 'bg-clip-text')]")->item(0);
                if ($ratingSpan) {
                    $rating = floatval(trim($ratingSpan->textContent));
                }
            }
            
            // Status, Author, Genre
            $status = '';
            $author = '';
            $genres = [];
            
            $infoBlocks = $xpath->query("//div[contains(@class, 'info') or contains(@class, 'metadata')]//p");
            foreach ($infoBlocks as $block) {
                $text = $block->textContent;
                if (strpos($text, 'Status') !== false) {
                    preg_match('/Status\s*:\s*(.+)/i', $text, $match);
                    $status = $match[1] ?? '';
                } elseif (strpos($text, 'Author') !== false) {
                    preg_match('/Author\s*:\s*(.+)/i', $text, $match);
                    $author = $match[1] ?? '';
                }
            }
            
            $genreLinks = $xpath->query("//a[contains(@href, 'genre')]");
            foreach ($genreLinks as $link) {
                $genres[] = trim($link->textContent);
            }
            
            // Cari manga_id untuk mengambil chapter via AJAX
            $manga_id = null;
            // Coba dari atribut hx-get di div chapter-list
            $chapterDiv = $xpath->query("//div[@id='chapter-list']")->item(0);
            if ($chapterDiv) {
                $hxGet = $chapterDiv->getAttribute('hx-get');
                if (preg_match('/manga_id=(\d+)/', $hxGet, $matches)) {
                    $manga_id = $matches[1];
                }
            }
            if (!$manga_id) {
                // Fallback: cari di data-bookmark-wrapper
                $bookmark = $xpath->query("//div[@data-bookmark-wrapper]")->item(0);
                if ($bookmark) {
                    $hxGet = $bookmark->getAttribute('hx-get');
                    if (preg_match('/manga_id=(\d+)/', $hxGet, $matches)) {
                        $manga_id = $matches[1];
                    }
                }
            }
            
            // Simpan manga
            $contentId = $this->saveManga([
                'title' => $title,
                'description' => $description,
                'poster_url' => $poster,
                'rating' => $rating,
                'status' => $status ? strtolower(trim($status)) : 'ongoing',
                'author' => $author,
                'genres' => $genres,
                'source_url' => $url
            ]);
            
            // Ambil chapters jika manga_id ditemukan
            if ($manga_id) {
                $chapters = $this->fetchChapters($manga_id);
                foreach ($chapters as $ch) {
                    $this->saveChapter($contentId, $ch);
                }
            } else {
                // Fallback: ambil dari HTML (hanya satu chapter)
                $chapterNodes = $xpath->query("//a[contains(@href, '/manga/') and contains(@href, 'chapter')]");
                $chapters = [];
                foreach ($chapterNodes as $node) {
                    $chUrl = $node->getAttribute('href');
                    if (strpos($chUrl, 'http') !== 0) {
                        $chUrl = $this->baseUrl . $chUrl;
                    }
                    $chTitle = trim($node->textContent);
                    if (empty($chTitle)) continue;
                    
                    $chNum = null;
                    if (preg_match('/(?:Chapter|Ch\.?)\s*([\d.]+)/i', $chTitle, $matches)) {
                        $chNum = floatval($matches[1]);
                    } else {
                        if (preg_match('/chapter[.-]?([\d.]+)/i', $chUrl, $matches)) {
                            $chNum = floatval($matches[1]);
                        }
                    }
                    if ($chNum !== null) {
                        $chapters[$chUrl] = [
                            'chapter_number' => $chNum,
                            'title' => $chTitle,
                            'source_url' => $chUrl
                        ];
                    }
                }
                foreach ($chapters as $ch) {
                    $this->saveChapter($contentId, $ch);
                }
            }
            
            return $contentId;
            
        } catch (Exception $e) {
            $this->log('MangaScraper', $url, 'failed', 0, 0, $e->getMessage());
            throw $e;
        }
    }
    
    public function scrapeChapter($url) {
        try {
            $html = $this->fetch($url);
            
            // ========== PERCOBAAN 1: Cari JSON di script tag ==========
            $images = [];
            
            // Pola ts_reader.run (umum di situs WordPress manga)
            if (preg_match('/ts_reader\.run\(\s*(\{.*?\})\s*\)/s', $html, $match)) {
                $data = json_decode($match[1], true);
                if (isset($data['sources'][0]['images']) && is_array($data['sources'][0]['images'])) {
                    $images = $data['sources'][0]['images'];
                }
            }
            
            // Pola images array JSON (alternatif)
            if (empty($images) && preg_match('/"images"\s*:\s*(\[[^\]]+\])/s', $html, $match)) {
                $images = json_decode($match[1], true);
            }
            
            // Filter gambar dari JSON
            if (!empty($images)) {
                $filtered = [];
                $allowed_domains = ['v5.kiryuu.to', 'kiryuu', 'cdnkuma.my.id', 'yuucdn.com', 'yuucdn.net', 'cdn.uqni.net', 'ky.uqni.net', 'ky.uqni.net'];
                $blocked_patterns = [
                    'royalzz', 'galaxy77', 'kiryujulio', 'takyu', 'takyu77', 'royal22',
                    'slot', 'gacor', 'dewa', 'hoki', 'macau', 'kalah', 'depo', 'wede', 'gede',
                    '5000', 'bonus', 'promo', 'sponsor', 'iklan', 'banner', 'logo', 'jud[ií]'
                ];
                
                foreach ($images as $src) {
                    if (strpos($src, 'http') !== 0) continue;
                    
                    $host = parse_url($src, PHP_URL_HOST);
                    $allowed = false;
                    foreach ($allowed_domains as $d) {
                        if (strpos($host, $d) !== false) {
                            $allowed = true;
                            break;
                        }
                    }
                    if (!$allowed) continue;
                    
                    $src_lower = strtolower($src);
                    $blocked = false;
                    foreach ($blocked_patterns as $bad) {
                        if (strpos($src_lower, $bad) !== false) {
                            $blocked = true;
                            break;
                        }
                    }
                    if ($blocked) continue;
                    
                    $filtered[] = $src;
                }
                
                if (!empty($filtered)) {
                    return ['images' => $filtered, 'prev' => '', 'next' => ''];
                }
            }
            
            // ========== PERCOBAAN 2: Ambil dari HTML (DOM) ==========
            $dom = $this->parseHTML($html);
            $xpath = new DOMXPath($dom);
            
            // Cari area reader utama
            $imgNodes = $xpath->query("//div[contains(@id, 'readerarea') or contains(@class, 'reader') or contains(@class, 'reading')]//img");
            
            if ($imgNodes->length === 0) {
                $imgNodes = $xpath->query("//img");
            }
            
            $images = [];
            $allowed_domains = ['v5.kiryuu.to', 'kiryuu', 'cdnkuma.my.id', 'yuucdn.com', 'yuucdn.net', 'cdn.uqni.net', 'ky.uqni.net', 'ky.uqni.net'];
            $blocked_patterns = [
                'royalzz', 'galaxy77', 'kiryujulio', 'takyu', 'takyu77', 'royal22',
                'slot', 'gacor', 'dewa', 'hoki', 'macau', 'kalah', 'depo', 'wede', 'gede',
                '5000', 'bonus', 'promo', 'sponsor', 'iklan', 'banner', 'logo', 'jud[ií]'
            ];
            
            foreach ($imgNodes as $node) {
                $src = $node->getAttribute('src');
                if (!$src) continue;
                
                if (strpos($src, 'http') !== 0) continue;
                
                $host = parse_url($src, PHP_URL_HOST);
                $allowed = false;
                foreach ($allowed_domains as $d) {
                    if (strpos($host, $d) !== false) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) continue;
                
                if (!preg_match('/\.(jpg|jpeg|png|webp|bmp)(\?.*)?$/i', $src)) continue;
                
                $src_lower = strtolower($src);
                $blocked = false;
                foreach ($blocked_patterns as $bad) {
                    if (strpos($src_lower, $bad) !== false) {
                        $blocked = true;
                        break;
                    }
                }
                if ($blocked) continue;
                
                $images[] = $src;
            }
            
            // Navigasi prev/next
            $prev = '';
            $next = '';
            $prevNode = $xpath->query("//a[contains(text(), 'Prev') or contains(@class, 'prev')]")->item(0);
            if ($prevNode) {
                $prev = $prevNode->getAttribute('href');
                if ($prev && strpos($prev, 'http') !== 0) $prev = $this->baseUrl . $prev;
            }
            $nextNode = $xpath->query("//a[contains(text(), 'Next') or contains(@class, 'next')]")->item(0);
            if ($nextNode) {
                $next = $nextNode->getAttribute('href');
                if ($next && strpos($next, 'http') !== 0) $next = $this->baseUrl . $next;
            }
            
            return [
                'images' => $images,
                'prev' => $prev,
                'next' => $next
            ];
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    public function search($query) {
        $urls = [
            "{$this->baseUrl}/?s=" . urlencode($query),
            "{$this->baseUrl}/search/" . urlencode($query),
            "{$this->baseUrl}/manga?s=" . urlencode($query)
        ];
        
        foreach ($urls as $url) {
            try {
                $result = $this->scrapeList($url);
                if ($result['found'] > 0) {
                    return $result;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return ['found' => 0, 'added' => 0];
    }
    
    private function saveManga($data) {
        $slug = $this->generateSlug($data['title']);
        
        $stmt = $this->pdo->prepare("SELECT id FROM content WHERE slug = ? OR source_url = ?");
        $stmt->execute([$slug, $data['source_url'] ?? '']);
        $existing = $stmt->fetch();
        
        $fields = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'poster_url' => $data['poster_url'] ?? null,
            'status' => $data['status'] ?? 'ongoing',
            'author' => $data['author'] ?? null,
            'genres' => json_encode($data['genres'] ?? []),
            'rating' => $data['rating'] ?? 0,
            'source_url' => $data['source_url'] ?? null
        ];
        
        if ($existing) {
            $sql = "UPDATE content SET 
                    title = :title,
                    description = :description,
                    poster_url = :poster_url,
                    status = :status,
                    author = :author,
                    genres = :genres,
                    rating = :rating,
                    source_url = :source_url,
                    updated_at = NOW()
                    WHERE id = :id";
            $fields['id'] = $existing['id'];
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($fields);
            return $existing['id'];
        } else {
            $sql = "INSERT INTO content (slug, title, description, type, status, poster_url, author, genres, rating, source_url)
                    VALUES (:slug, :title, :description, 'manga', :status, :poster_url, :author, :genres, :rating, :source_url)";
            $fields['slug'] = $slug;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($fields);
            return $this->pdo->lastInsertId();
        }
    }
    
    private function saveChapter($contentId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO chapters (content_id, chapter_number, title, images, source_url)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                title = VALUES(title), 
                images = VALUES(images),
                source_url = VALUES(source_url)
        ");
        
        $stmt->execute([
            $contentId,
            $data['chapter_number'] ?? 0,
            $data['title'] ?? "Chapter " . ($data['chapter_number'] ?? ''),
            json_encode($data['images'] ?? []),
            $data['source_url'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function log($sourceName, $sourceUrl, $status, $itemsFound = 0, $itemsAdded = 0, $error = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO scrape_logs (source_name, source_url, status, items_found, items_added, error_message, started_at, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$sourceName, $sourceUrl, $status, $itemsFound, $itemsAdded, $error]);
    }
}

if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'help';
    $scraper = new MangaScraper();
    
    switch ($action) {
        case 'latest':
            $page = (int)($argv[2] ?? 1);
            $result = $scraper->scrapeLatest($page);
            echo "Latest Manga: {$result['found']} found, {$result['added']} added\n";
            break;
            
        case 'popular':
            $page = (int)($argv[2] ?? 1);
            $result = $scraper->scrapeByOrder('popular', $page);
            echo "Popular Manga: {$result['found']} found, {$result['added']} added\n";
            break;
            
        case 'update':
            $page = (int)($argv[2] ?? 1);
            $result = $scraper->scrapeByOrder('update', $page);
            echo "Update Manga: {$result['found']} found, {$result['added']} added\n";
            break;
            
        case 'detail':
            $url = $argv[2] ?? '';
            if ($url) {
                $id = $scraper->scrapeDetail($url);
                echo "Scraped manga detail, ID: $id\n";
            } else {
                echo "Usage: php manga-scraper.php detail <url>\n";
            }
            break;
            
        case 'chapter':
            $url = $argv[2] ?? '';
            if ($url) {
                try {
                    $result = $scraper->scrapeChapter($url);
                    echo "Found " . count($result['images']) . " images\n";
                    echo "Prev: " . ($result['prev'] ?: 'none') . "\n";
                    echo "Next: " . ($result['next'] ?: 'none') . "\n";
                } catch (Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
            } else {
                echo "Usage: php manga-scraper.php chapter <url>\n";
            }
            break;
            
        case 'search':
            $query = $argv[2] ?? '';
            if ($query) {
                $result = $scraper->search($query);
                echo "Search: {$result['found']} found, {$result['added']} added\n";
            } else {
                echo "Usage: php manga-scraper.php search <query>\n";
            }
            break;
            
        default:
            echo "Usage: php manga-scraper.php [latest|popular|update|detail|chapter|search] [page|url|query]\n";
    }
}
