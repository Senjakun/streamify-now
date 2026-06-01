<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../config/auth_helper.php';

$pdo = getDB();
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 10");
    echo json_encode($stmt->fetchAll());
    exit;
}

$user = getCurrentUser();
if (!$user || !$user['is_admin']) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $title = trim($data['title'] ?? '');
    $content = trim($data['content'] ?? '');
    $content_html = $data['content_html'] ?? '';
    $image_url = $data['image_url'] ?? '';
    $type = $data['type'] ?? 'info';
    if (!$title || !$content) { echo json_encode(['error'=>'Title dan content wajib']); exit; }
    $pdo->prepare("INSERT INTO announcements (title, content, content_html, image_url, type, created_by) VALUES (?,?,?,?,?,?)")->execute([$title, $content, $content_html, $image_url, $type, $user['id']]);
    // Blast email jika diminta
    $blast = $data['blast'] ?? false;
    if ($blast) {
        $newId = intval($pdo->lastInsertId());
        $logFile = '/var/log/nginx/email-blast-' . $newId . '.log';
        $blastCmd = "php /var/www/streamify/app/backend-vps/api/email_blast.php " . $newId . " > " . $logFile . " 2>&1 &";
        exec($blastCmd);
    }
    echo json_encode(['success'=>true, 'blast'=>$blast]);
    exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    $pdo->prepare("UPDATE announcements SET is_active=0 WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

http_response_code(400);
echo json_encode(['error'=>'Invalid action']);
