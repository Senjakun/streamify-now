<?php
/**
 * Proxy API for Scraping Content
 * Acts as middleware between frontend and scraped sources
 * Supports: Anime, Manga, Movies
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../scraper/anime-scraper.php';
require_once __DIR__ . '/../scraper/manga-scraper.php';

/**
 * Main Proxy Controller
 */
class ProxyAPI {
    private $pdo;
    private $animeScraper;
    private $mangaScraper;
    
    public function __construct() {
        $this->pdo = getDB();
        $this->animeScraper = new AnimeScraper();
        $this->mangaScraper = new MangaScraper();
    }
    
    /**
     * Handle API request
     */
    public function handle() {
        $path = $_GET['path'] ?? '';
        $type = $_GET['type'] ?? 'anime';
        $action = $_GET['action'] ?? 'list';
        
        try {
            switch ($action) {
                case 'list':
                    return $this->getList($type, $_GET);
                    
                case 'detail':
                    return $this->getDetail($type, $_GET['slug'] ?? '');
                    
                case 'watch':
                    return $this->getWatch($type, $_GET);
                    
                case 'search':
                    return $this->search($type, $_GET['q'] ?? '');
                    
                case 'scrape':
                    return $this->triggerScrape($type, $_GET);
                    
                case 'trending':
                    return $this->getTrending($type);
                    
                case 'home':
                    return $this->getHomeData();
                    
                default:
                    return $this->error('Invalid action');
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Get home page data (all categories)
     */
    private function getHomeData() {
        // Get trending from each category
        $animes = $this->queryContent('anime', 8, 'views');
        $donghua = $this->queryContent('donghua', 8, 'views');
        $mangas = $this->queryContent('manga', 8, 'views');
        
        // Get latest updates
        $latestAnime = $this->queryContent('anime', 4, 'updated_at');
        $latestManga = $this->queryContent('manga', 4, 'updated_at');
        $latestDonghua = $this->queryContent('donghua', 4, 'updated_at');
        
        return $this->success([
            'trending' => [
                'anime' => $animes,
                'donghua' => $donghua,
                'manga' => $mangas
            ],
            'latest' => [
                'anime' => $latestAnime,
                'manga' => $latestManga,
                'donghua' => $latestDonghua
            ]
        ]);
    }
    
    /**
     * Get content list
     */
    private function getList($type, $params) {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(50, max(1, (int)($params['limit'] ?? 20)));
        $sort = $params['sort'] ?? 'updated_at';
        $status = $params['status'] ?? null;
        $genre = $params['genre'] ?? null;
        
        $offset = ($page - 1) * $limit;
        
        $where = ['type = ?'];
        $bindings = [$type];
        
        if ($status) {
            $where[] = 'status = ?';
            $bindings[] = $status;
        }
        
        if ($genre) {
            $where[] = 'JSON_CONTAINS(genres, ?)';
            $bindings[] = json_encode($genre);
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $this->sanitizeSort($sort);
        
        // Get total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM content WHERE $whereClause");
        $countStmt->execute($bindings);
        $total = $countStmt->fetchColumn();
        
        // Get items
        $stmt = $this->pdo->prepare("
            SELECT id, slug, title, title_alt, poster_url, rating, status, year, genres, views, updated_at
            FROM content 
            WHERE $whereClause
            ORDER BY $orderBy DESC
            LIMIT ? OFFSET ?
        ");
        
        $bindings[] = $limit;
        $bindings[] = $offset;
        $stmt->execute($bindings);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process items
        foreach ($items as &$item) {
            $item['genres'] = json_decode($item['genres'], true) ?: [];
        }
        
        return $this->success([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * Get content detail
     */
    private function getDetail($type, $slug) {
        if (!$slug) {
            return $this->error('Slug required');
        }
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM content WHERE slug = ? AND type = ?
        ");
        $stmt->execute([$slug, $type]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$content) {
            return $this->error('Content not found', 404);
        }
        
        // Decode JSON fields
        $content['genres'] = json_decode($content['genres'], true) ?: [];
        
        // Get episodes/chapters
        if ($type === 'anime' || $type === 'donghua') {
            $stmt = $this->pdo->prepare("
                SELECT * FROM episodes WHERE content_id = ? ORDER BY episode_number
            ");
            $stmt->execute([$content['id']]);
            $content['episodes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else if ($type === 'manga') {
            $stmt = $this->pdo->prepare("
                SELECT * FROM chapters WHERE content_id = ? ORDER BY chapter_number DESC
            ");
            $stmt->execute([$content['id']]);
            $content['chapters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($content['chapters'] as &$ch) {
                $ch['images'] = json_decode($ch['images'], true) ?: [];
            }
        }
        
        // Increment views
        $this->pdo->prepare("UPDATE content SET views = views + 1 WHERE id = ?")->execute([$content['id']]);
        
        return $this->success($content);
    }
    
    /**
     * Get watch/read data
     */
    private function getWatch($type, $params) {
        $slug = $params['slug'] ?? '';
        $episode = $params['episode'] ?? null;
        $chapter = $params['chapter'] ?? null;
        
        // Get content first
        $stmt = $this->pdo->prepare("SELECT id, source_url FROM content WHERE slug = ?");
        $stmt->execute([$slug]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$content) {
            return $this->error('Content not found');
        }
        
        if ($type === 'anime' && $episode) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM episodes WHERE content_id = ? AND episode_number = ?
            ");
            $stmt->execute([$content['id'], $episode]);
            $ep = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ep && $ep['source_url']) {
                // Scrape streaming links if needed
                try {
                    $streamData = $this->animeScraper->scrapeEpisode($ep['source_url']);
                    $ep['streams'] = $streamData['streams'];
                    $ep['downloads'] = $streamData['downloads'];
                } catch (Exception $e) {
                    $ep['streams'] = [];
                    $ep['downloads'] = [];
                }
            }
            
            return $this->success($ep);
            
        } else if ($type === 'manga' && $chapter) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM chapters WHERE content_id = ? AND chapter_number = ?
            ");
            $stmt->execute([$content['id'], $chapter]);
            $ch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ch) {
                $ch['images'] = json_decode($ch['images'], true) ?: [];
                
                // If images empty, scrape them
                if (empty($ch['images']) && $ch['source_url']) {
                    try {
                        $chapterData = $this->mangaScraper->scrapeChapter($ch['source_url']);
                        $ch['images'] = $chapterData['images'];
                        $ch['prev'] = $chapterData['prev'];
                        $ch['next'] = $chapterData['next'];
                        
                        // Update database
                        $this->pdo->prepare("UPDATE chapters SET images = ? WHERE id = ?")->execute([
                            json_encode($ch['images']),
                            $ch['id']
                        ]);
                    } catch (Exception $e) {
                        // Silent fail
                    }
                }
            }
            
            return $this->success($ch);
            
        } else if ($type === 'donghua') {
            // Get donghua episode embed
            $epNum = (int)($params['episode'] ?? 1);
            $stmt = $this->pdo->prepare("SELECT * FROM episodes WHERE content_id = ? AND episode_number = ?");
            $stmt->execute([$content['id'], $epNum]);
            $ep = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ep) return $this->error('Episode not found');
            return $this->success($ep);
        }
        
        return $this->error('Invalid request');
    }
    
    /**
     * Search content
     */
    private function search($type, $query) {
        if (!$query) {
            return $this->error('Query required');
        }
        
        // Search in database first
        $stmt = $this->pdo->prepare("
            SELECT id, slug, title, title_alt, poster_url, rating, status, year, genres, type
            FROM content 
            WHERE (title LIKE ? OR title_alt LIKE ?) AND (type = ? OR ? = '')
            ORDER BY views DESC
            LIMIT 20
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm, $type, $type]);
        $dbResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process results
        foreach ($dbResults as &$item) {
            $item['genres'] = json_decode($item['genres'], true) ?: [];
        }
        
        // If not enough results, try live search
        $liveResults = [];
        if (count($dbResults) < 5) {
            try {
                switch ($type) {
                    case 'anime':
                        $liveResults = $this->animeScraper->search($query);
                        break;
                    case 'manga':
                        $liveResults = $this->mangaScraper->search($query);
                        break;
                }
            } catch (Exception $e) {
                // Silent fail
            }
        }
        
        return $this->success([
            'database' => $dbResults,
            'live' => $liveResults
        ]);
    }
    
    /**
     * Trigger scrape operation
     */
    private function triggerScrape($type, $params) {
        // This should be admin-only
        $page = (int)($params['page'] ?? 1);
        $mode = $params['mode'] ?? 'latest';
        
        try {
            switch ($type) {
                case 'anime':
                    if ($mode === 'ongoing') {
                        $result = $this->animeScraper->scrapeOngoing($page);
                    } else {
                        $result = $this->animeScraper->scrapeCompleted($page);
                    }
                    break;
                    
                case 'manga':
                    $result = $this->mangaScraper->scrapeLatest($page);
                    break;
                    
                default:
                    return $this->error('Invalid type');
            }
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error('Scrape failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get trending content
     */
    private function getTrending($type) {
        $items = $this->queryContent($type, 10, 'views');
        return $this->success($items);
    }
    
    /**
     * Query content helper
     */
    private function queryContent($type, $limit, $orderBy = 'updated_at') {
        $orderBy = $this->sanitizeSort($orderBy);
        
        $stmt = $this->pdo->prepare("
            SELECT id, slug, title, poster_url, rating, status, year, views
            FROM content 
            WHERE type = ?
            ORDER BY $orderBy DESC
            LIMIT ?
        ");
        $stmt->execute([$type, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Sanitize sort column
     */
    private function sanitizeSort($sort) {
        $allowed = ['updated_at', 'created_at', 'views', 'rating', 'year', 'title'];
        return in_array($sort, $allowed) ? $sort : 'updated_at';
    }
    
    /**
     * Success response
     */
    private function success($data) {
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Error response
     */
    private function error($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Run API
$api = new ProxyAPI();
$api->handle();
