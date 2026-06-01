<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';

function getUser() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';
    if (!preg_match('/Bearer\s+(.*)$/i', $auth, $m)) return null;
    $parts = explode('.', $m[1]);
    if (count($parts) !== 3) return null;
    $payload = json_decode(base64_decode(str_replace(['-','_'],['+','/'], $parts[1])), true);
    if (!$payload || $payload['exp'] < time()) return null;
    return $payload['user_id'];
}

$pdo = getDB();
$action = $_GET['action'] ?? '';

if ($action === 'add') {
    $user_id = getUser();
    if (!$user_id) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $content_id = intval($data['content_id'] ?? 0);
    $episode_id = intval($data['episode_id'] ?? 0) ?: null;
    $chapter_id = intval($data['chapter_id'] ?? 0) ?: null;
    if (!$content_id) { http_response_code(400); echo json_encode(['error'=>'content_id required']); exit; }

    $stmt = $pdo->prepare("INSERT INTO user_history (user_id,content_id,episode_id,chapter_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE episode_id=VALUES(episode_id),chapter_id=VALUES(chapter_id),last_watched_at=NOW()");
    $stmt->execute([$user_id, $content_id, $episode_id, $chapter_id]);

    $pdo->prepare("DELETE FROM user_history WHERE user_id=? AND id NOT IN (SELECT id FROM (SELECT id FROM user_history WHERE user_id=? ORDER BY last_watched_at DESC LIMIT 20) t)")->execute([$user_id, $user_id]);

    echo json_encode(['success'=>true]);

} elseif ($action === 'list') {
    $user_id = getUser();
    if (!$user_id) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
    $limit = intval($_GET['limit'] ?? 20);
    $stmt = $pdo->prepare("SELECT h.*, c.title, c.slug, c.type, c.poster_url, c.rating,
        e.episode_number, ch.chapter_number
        FROM user_history h
        JOIN content c ON h.content_id = c.id
        LEFT JOIN episodes e ON h.episode_id = e.id
        LEFT JOIN chapters ch ON h.chapter_id = ch.id
        WHERE h.user_id = ?
        ORDER BY h.last_watched_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    echo json_encode(['history' => $stmt->fetchAll()]);

} else {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid action']);
}
