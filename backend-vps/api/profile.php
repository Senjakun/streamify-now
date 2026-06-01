<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../config/auth_helper.php';
require_once '../config/badges.php';

$pdo = getDB();
$action = $_GET['action'] ?? '';

// ── GET PUBLIC PROFILE ────────────────────────────────────────
if ($action === 'get') {
    $userId = intval($_GET['user_id'] ?? 0);
    if (!$userId) { http_response_code(400); echo json_encode(['error'=>'user_id required']); exit; }

    $stmt = $pdo->prepare("SELECT id,username,avatar_url,badge,badge_icon,badge_color,rank_label,created_at,
        (SELECT COUNT(*) FROM comments WHERE user_id=u.id) as comment_count,
        (SELECT COUNT(*) FROM bookmarks WHERE user_id=u.id) as bookmark_count
        FROM users u WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) { http_response_code(404); echo json_encode(['error'=>'User not found']); exit; }

    $user['badge_style'] = getBadgeStyle($user['badge'] ?? 'Penduduk Desa');
    echo json_encode($user);
    exit;
}

// ── GET BADGES LIST ───────────────────────────────────────────
if ($action === 'badges') {
    $result = [];
    foreach (BADGES as $name => $data) {
        if ($name === 'Tuhan') continue; // admin only
        $result[] = array_merge(['name'=>$name], $data, getBadgeStyle($name));
    }
    echo json_encode($result);
    exit;
}

// ── UPDATE PROFILE ────────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = getCurrentUser();
    if (!$user) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

    $data = json_decode(file_get_contents('php://input'), true);
    $updates = [];
    $params = [];

    // Update username
    if (!empty($data['username'])) {
        $username = trim($data['username']);
        if (strlen($username) < 3 || strlen($username) > 50) {
            echo json_encode(['error'=>'Username 3-50 karakter']); exit;
        }
        $updates[] = 'username=?';
        $params[] = $username;
    }

    // Update badge (non-admin hanya bisa pilih badge non-Tuhan)
    if (!empty($data['badge'])) {
        $badge = $data['badge'];
        $validBadges = array_keys(BADGES);
        if (!in_array($badge, $validBadges)) {
            echo json_encode(['error'=>'Badge tidak valid']); exit;
        }
        if ($badge === 'Tuhan' && !in_array('admin', $user['roles'] ?? [])) {
            echo json_encode(['error'=>'Badge ini khusus admin']); exit;
        }
        $badgeData = BADGES[$badge];
        $updates[] = 'badge=?'; $params[] = $badge;
        $updates[] = 'badge_icon=?'; $params[] = $badgeData['icon'];
        $updates[] = 'badge_color=?'; $params[] = $badgeData['color'];
    }

    // Update password
    if (!empty($data['new_password'])) {
        if (strlen($data['new_password']) < 8) {
            echo json_encode(['error'=>'Password minimal 8 karakter']); exit;
        }
        // Verify old password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!password_verify($data['old_password'] ?? '', $row['password_hash'])) {
            echo json_encode(['error'=>'Password lama salah']); exit;
        }
        $updates[] = 'password_hash=?';
        $params[] = password_hash($data['new_password'], PASSWORD_BCRYPT);
    }

    if (empty($updates)) { echo json_encode(['error'=>'Tidak ada yang diupdate']); exit; }

    $params[] = $user['id'];
    $pdo->prepare("UPDATE users SET " . implode(',', $updates) . " WHERE id=?")->execute($params);

    // Return updated user
    $stmt = $pdo->prepare("SELECT id,username,avatar_url,badge,badge_icon,badge_color,rank_label FROM users WHERE id=?");
    $stmt->execute([$user['id']]);
    $updated = $stmt->fetch();
    echo json_encode(['success'=>true, 'user'=>$updated]);
    exit;
}

// ── UPLOAD AVATAR ─────────────────────────────────────────────
if ($action === 'avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = getCurrentUser();
    if (!$user) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

    if (!isset($_FILES['avatar'])) { echo json_encode(['error'=>'File tidak ditemukan']); exit; }

    $file = $_FILES['avatar'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];

    if ($file['size'] > $maxSize) { echo json_encode(['error'=>'File maksimal 5MB']); exit; }
    if (!in_array($file['type'], $allowedTypes)) { echo json_encode(['error'=>'Format harus JPG/PNG/GIF/WEBP']); exit; }

    $uploadDir = '/var/www/streamify/public/avatars/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['error'=>'Gagal upload']); exit;
    }

    $avatarUrl = '/avatars/' . $filename;

    $stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$user['id']]);
    $oldAvatar = $stmt->fetchColumn();

    $pdo->prepare("UPDATE users SET avatar_url=? WHERE id=?")->execute([$avatarUrl, $user['id']]);

    if ($oldAvatar && str_starts_with($oldAvatar, '/avatars/')) {
        $oldPath = '/var/www/streamify/public' . $oldAvatar;
        if (is_file($oldPath) && $oldAvatar !== $avatarUrl) {
            @unlink($oldPath);
        }
    }

    echo json_encode(['success'=>true, 'avatar_url'=>$avatarUrl]);
    exit;
}

http_response_code(400);

// ── GET USER COMMENTS ─────────────────────────────────────
if ($action === 'comments') {
    $userId = intval($_GET['user_id'] ?? 0);
    if (!$userId) { echo json_encode([]); exit; }
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $pdo = getDB();
    $total = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id=?");
    $total->execute([$userId]);
    $totalCount = $total->fetchColumn();
    $stmt = $pdo->prepare("
        SELECT c.*, ct.title as content_title, ct.slug as content_slug, ct.type as content_type
        FROM comments c
        LEFT JOIN content ct ON c.content_id = ct.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$userId]);
    echo json_encode([
        'comments' => $stmt->fetchAll(),
        'total' => $totalCount,
        'pages' => ceil($totalCount / $limit)
    ]);
    exit;
}

// ── GET USER HISTORY ──────────────────────────────────────
if ($action === 'history_public') {
    $userId = intval($_GET['user_id'] ?? 0);
    if (!$userId) { echo json_encode([]); exit; }
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT h.*, c.title, c.slug, c.type, c.poster_url
        FROM user_history h
        JOIN content c ON h.content_id = c.id
        WHERE h.user_id = ?
        ORDER BY h.last_watched_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetchAll());
    exit;
}
