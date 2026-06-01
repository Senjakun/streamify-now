<?php
/**
 * Bookmarks API
 * Endpoints: /api/bookmarks.php?action=list|toggle|check
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../config/auth_helper.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $user = getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Login diperlukan']);
            exit;
        }
        
        $type = $_GET['type'] ?? null; // anime, movie, manga
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $pdo = getDB();
        
        $where = "b.user_id = ?";
        $params = [$user['id']];
        
        if ($type) {
            $where .= " AND c.type = ?";
            $params[] = $type;
        }
        
        // Count total
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM bookmarks b 
            JOIN content c ON b.content_id = c.id 
            WHERE $where
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        // Get bookmarks
        $sql = "
            SELECT c.*, b.created_at as bookmarked_at
            FROM bookmarks b
            JOIN content c ON b.content_id = c.id
            WHERE $where
            ORDER BY b.created_at DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookmarks = $stmt->fetchAll();
        
        // Parse JSON genres
        foreach ($bookmarks as &$bookmark) {
            $bookmark['genres'] = json_decode($bookmark['genres'] ?? '[]', true);
        }
        
        echo json_encode([
            'bookmarks' => $bookmarks,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    case 'toggle':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $user = getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Login diperlukan']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $contentId = (int)($data['content_id'] ?? 0);
        
        if (!$contentId) {
            http_response_code(400);
            echo json_encode(['error' => 'content_id is required']);
            exit;
        }
        
        $pdo = getDB();
        
        // Check if already bookmarked
        $stmt = $pdo->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$user['id'], $contentId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Remove bookmark
            $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ?");
            $stmt->execute([$existing['id']]);
            $bookmarked = false;
        } else {
            // Add bookmark
            $stmt = $pdo->prepare("INSERT INTO bookmarks (user_id, content_id) VALUES (?, ?)");
            $stmt->execute([$user['id'], $contentId]);
            $bookmarked = true;
        }
        
        echo json_encode([
            'success' => true,
            'bookmarked' => $bookmarked
        ]);
        break;
        
    case 'check':
        $user = getCurrentUser();
        if (!$user) {
            echo json_encode(['bookmarked' => false]);
            exit;
        }
        
        $contentId = (int)($_GET['content_id'] ?? 0);
        if (!$contentId) {
            http_response_code(400);
            echo json_encode(['error' => 'content_id is required']);
            exit;
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$user['id'], $contentId]);
        
        echo json_encode(['bookmarked' => (bool)$stmt->fetch()]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
