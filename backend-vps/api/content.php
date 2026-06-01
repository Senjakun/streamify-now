<?php
/**
 * Content API
 * Endpoints: /api/content.php?action=list|detail|search|trending
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $type = $_GET['type'] ?? null; // anime, movie, manga
        $status = $_GET['status'] ?? null;
        $genre = $_GET['genre'] ?? null;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $orderBy = $_GET['order_by'] ?? 'updated_at';
        $orderDir = strtoupper($_GET['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        $allowedOrderBy = ['updated_at', 'created_at', 'rating', 'title', 'year'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'updated_at';
        }
        
        $pdo = getDB();
        
        $where = "1=1";
        $params = [];
        
        if ($type) {
            $where .= " AND type = ?";
            $params[] = $type;
        }
        
        if ($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        if ($genre) {
            $where .= " AND JSON_CONTAINS(genres, ?)";
            $params[] = json_encode($genre);
        }
        
        if ($year) {
            $where .= " AND year = ?";
            $params[] = $year;
        }
        
        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM content WHERE $where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        // Get content
        $sql = "SELECT * FROM content WHERE $where ORDER BY $orderBy $orderDir LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $content = $stmt->fetchAll();
        
        // Parse JSON genres
        foreach ($content as &$item) { $item['rating'] = (float) $item['rating'];
            $item['genres'] = json_decode($item['genres'] ?? '[]', true);
        }
        
        echo json_encode([
            'content' => $content,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    case 'detail':
        $slug = $_GET['slug'] ?? '';
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$slug && !$id) {
            http_response_code(400);
            echo json_encode(['error' => 'slug or id is required']);
            exit;
        }
        
        $pdo = getDB();
        
        $type_filter = $_GET['type'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($type_filter) {
            $stmt = $pdo->prepare("SELECT * FROM content WHERE slug = ? AND type = ?");
            $stmt->execute([$slug, $type_filter]);
            $content_check = $stmt->fetch();
            if (!$content_check) {
                // Fallback: coba slug+'-donghua'
                $stmt = $pdo->prepare("SELECT * FROM content WHERE slug = ? AND type = ?");
                $stmt->execute([$slug . '-' . $type_filter, $type_filter]);
            } else {
                // Reset untuk fetch ulang di bawah
                $stmt = $pdo->prepare("SELECT * FROM content WHERE slug = ? AND type = ?");
                $stmt->execute([$slug, $type_filter]);
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM content WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        
        $content = $stmt->fetch();
        
        if (!$content) {
            http_response_code(404);
            echo json_encode(['error' => 'Content not found']);
            exit;
        }
        
        $content['genres'] = json_decode($content['genres'] ?? '[]', true);
        $content["description"] = html_entity_decode($content["description"] ?? "", ENT_QUOTES | ENT_HTML5, "UTF-8");
        $content["synopsis"] = $content["description"];
        // Get episodes or chapters based on type
        if ($content['type'] === 'manga') {
            $stmt = $pdo->prepare("SELECT * FROM chapters WHERE content_id = ? ORDER BY chapter_number DESC");
            $stmt->execute([$content['id']]);
            $chapters = $stmt->fetchAll();
            foreach ($chapters as &$chapter) {
                $chapter['images'] = json_decode($chapter['images'] ?? '[]', true);
            }
            $content['chapters'] = $chapters;
        } else {
            $stmt = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY episode_number DESC");
            $stmt->execute([$content['id']]);
            $content['episodes'] = $stmt->fetchAll();
        }
        
        echo json_encode($content);
        break;
        
    case 'search':
        $query = trim($_GET['q'] ?? '');
        $type = $_GET['type'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        if (strlen($query) < 2) {
            http_response_code(400);
            echo json_encode(['error' => 'Query minimal 2 karakter']);
            exit;
        }
        
        $pdo = getDB();
        
        $like = '%' . $query . '%';
        $where = "(MATCH(title, title_alt, description) AGAINST(? IN NATURAL LANGUAGE MODE) OR title LIKE ?)";
        $params = [$query, $like];
        
        if ($type) {
            $where .= " AND type = ?";
            $params[] = $type;
        }
        
        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM content WHERE $where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        // Search: prioritize title (exact > contains) then fulltext relevance
        $sql = "
            SELECT *, MATCH(title, title_alt, description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
            FROM content 
            WHERE $where
            ORDER BY (title = ?) DESC, (title LIKE ?) DESC, relevance DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$query], $params, [$query, $like]));
        $content = $stmt->fetchAll();
        
        foreach ($content as &$item) { $item['rating'] = (float) $item['rating'];
            $item['genres'] = json_decode($item['genres'] ?? '[]', true);
            unset($item['relevance']);
        }
        
        echo json_encode([
            'content' => $content,
            'query' => $query,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    case 'trending':
        $type = $_GET['type'] ?? null;
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
        
        $pdo = getDB();
        
        $where = "1=1";
        $params = [];
        
        if ($type) {
            $where .= " AND type = ?";
            $params[] = $type;
        }
        
        // Get trending based on CAST(rating AS DECIMAL(3,1)) as rating and recent updates
        $sql = "
            SELECT * FROM content 
            WHERE $where
            ORDER BY CAST(rating AS DECIMAL(3,1)) DESC, updated_at DESC
            LIMIT $limit
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $content = $stmt->fetchAll();
        
        foreach ($content as &$item) { $item['rating'] = (float) $item['rating'];
            $item['genres'] = json_decode($item['genres'] ?? '[]', true);
        }
        
        echo json_encode(['trending' => $content]);
        break;
        
    case 'latest_anime':
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 24)));
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $countSql = "SELECT COUNT(DISTINCT c.id) FROM content c INNER JOIN episodes e ON e.content_id = c.id WHERE c.type='anime' AND c.status='ongoing'";
        $total = $pdo->query($countSql)->fetchColumn();
        $sql = "SELECT c.*, MAX(e.created_at) as last_episode_at FROM content c INNER JOIN episodes e ON e.content_id = c.id WHERE c.type='anime' AND c.status='ongoing' GROUP BY c.id ORDER BY last_episode_at DESC LIMIT $limit OFFSET $offset";
        $rows = $pdo->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['rating'] = (float) $row['rating'];
            $row['genres'] = json_decode($row['genres'] ?? '[]', true);
        }
        echo json_encode(['content' => $rows, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => (int)$total, 'pages' => (int)ceil($total / $limit)]]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
}

// ── Episode Servers ──────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'servers') {
    $episodeId = intval($_GET['episode_id'] ?? 0);
    if (!$episodeId) { echo json_encode([]); exit; }
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM episode_servers WHERE episode_id=? ORDER BY type, language DESC, server_name");
    $stmt->execute([$episodeId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

