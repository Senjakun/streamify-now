<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../config/auth_helper.php';

$user = getCurrentUser();
if (!$user || !in_array('admin', $user['roles'] ?? [])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pdo = getDB();
$action = $_GET['action'] ?? '';

function json_out($data) {
    echo json_encode($data);
    exit;
}

// ── STATS ─────────────────────────────────────────────────
if ($action === 'stats') {
    $stats = [];
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['comments'] = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
    $stats['anime'] = $pdo->query("SELECT COUNT(*) FROM content WHERE type='anime'")->fetchColumn();
    $stats['donghua'] = $pdo->query("SELECT COUNT(*) FROM content WHERE type='donghua'")->fetchColumn();
    $stats['manga'] = $pdo->query("SELECT COUNT(*) FROM content WHERE type='manga'")->fetchColumn();
    $stats['episodes'] = $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn();
    json_out($stats);
}

// ── COMMENTS ──────────────────────────────────────────────
if ($action === 'comments') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $where = $search ? "WHERE c.comment_text LIKE ?" : "";
    $params = $search ? ["%{$search}%"] : [];

    $total = $pdo->prepare("SELECT COUNT(*) FROM comments c $where");
    $total->execute($params);
    $totalCount = $total->fetchColumn();

    $stmt = $pdo->prepare("SELECT c.*, u.username, u.badge, u.badge_icon FROM comments c JOIN users u ON c.user_id=u.id $where ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);

    json_out([
        'comments' => $stmt->fetchAll(),
        'total' => $totalCount,
        'pages' => ceil($totalCount / $limit)
    ]);
}

// ── DELETE COMMENT ────────────────────────────────────────
if ($action === 'delete_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    if (!$id) json_out(['error' => 'ID required']);
    $pdo->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
    json_out(['success' => true]);
}

// ── PIN COMMENT ───────────────────────────────────────────
if ($action === 'pin_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    try { $pdo->exec("ALTER TABLE comments ADD COLUMN is_pinned TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    $stmt = $pdo->prepare("SELECT is_pinned FROM comments WHERE id=?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();
    $pdo->prepare("UPDATE comments SET is_pinned=? WHERE id=?")->execute([$current ? 0 : 1, $id]);
    json_out(['success' => true, 'is_pinned' => !$current]);
}

// ── USERS ─────────────────────────────────────────────────
if ($action === 'users') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    $stmt = $pdo->prepare("SELECT id,username,email,badge,badge_icon,is_admin,created_at,(SELECT COUNT(*) FROM comments WHERE user_id=users.id) as comment_count FROM users ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute();

    json_out([
        'users' => $stmt->fetchAll(),
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]);
}

// ── TOGGLE ADMIN ──────────────────────────────────────────
if ($action === 'toggle_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    if ($id === $user['id']) json_out(['error' => 'Tidak bisa ubah diri sendiri']);

    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id=?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();

    $pdo->prepare("UPDATE users SET is_admin=? WHERE id=?")->execute([$current ? 0 : 1, $id]);
    json_out(['success' => true]);
}

// ── SETTINGS ──────────────────────────────────────────────
if ($action === 'get_settings') {
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (key_name VARCHAR(100) PRIMARY KEY, value TEXT)"); } catch (Exception $e) {}
    $stmt = $pdo->query("SELECT key_name, value FROM site_settings");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) $settings[$row['key_name']] = $row['value'];
    json_out($settings);
}

if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (key_name VARCHAR(100) PRIMARY KEY, value TEXT)"); } catch (Exception $e) {}
    $data = json_decode(file_get_contents('php://input'), true);
    foreach ($data as $key => $value) {
        $pdo->prepare("INSERT INTO site_settings (key_name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?")->execute([$key, $value, $value]);
    }
    json_out(['success' => true]);
}

// ── RUN SCRAPER ───────────────────────────────────────────
if ($action === 'run_scraper') {
    $script = $_GET['script'] ?? '';
    $allowed = ['anime-scraper', 'scrape-detail', 'donghua-update', 'donghua-detail', 'donghua-episodes', 'komiku-scraper', 'novel-readnovelfull'];
    if (!in_array($script, $allowed, true)) json_out(['error' => 'Script tidak valid']);

    $logfile = "/var/log/$script.log";
    $output = shell_exec("php /var/www/api/scraper/$script.php 2>&1 | tee -a $logfile | tail -20");
    json_out(['output' => $output]);
}

// ── VIEW LOG ──────────────────────────────────────────────
if ($action === 'view_log') {
    $file = $_GET['file'] ?? '';
    $allowed = ['update-episodes.log', 'fix-poster.log', 'fix-poster2.log', 'manga-bulk.log', 'manga-update.log', 'donghua-update.log'];
    if (!in_array($file, $allowed, true)) json_out(['error' => 'File tidak valid']);

    $path = "/var/log/$file";
    if (!file_exists($path)) json_out(['content' => 'Log belum ada']);

    $content = file_get_contents($path);
    $lines = explode("\n", trim((string)$content));
    json_out(['content' => implode("\n", array_slice($lines, -50))]);
}

// ── NOVEL CRON STATUS ─────────────────────────────────────
if ($action === 'novel_status') {
    $rows = $pdo->query("
        SELECT source, country, COUNT(*) AS total_novels, COALESCE(SUM(total_chapters),0) AS total_chapters
        FROM novels
        GROUP BY source, country
        ORDER BY source, country
    ")->fetchAll();

    $cursors = [];
    foreach (['mtlreader','freewebnovel','woopread'] as $src) {
        $file = "/root/.novel_sync_{$src}.cursor";
        $cursors[$src] = file_exists($file) ? (int)trim((string)file_get_contents($file)) : 0;
    }

    $running = trim((string)shell_exec("pgrep -af 'novel_sync_batch.php|seed_novel_urls.sh|collect_novel_urls.py|collect_woopread_v2.py' 2>/dev/null"));

    json_out([
        'counts' => $rows,
        'cursors' => $cursors,
        'running' => $running === '' ? [] : explode("\n", $running),
    ]);
}

// ── NOVEL RUN BATCH ───────────────────────────────────────
if ($action === 'novel_run_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $source = trim((string)($data['source'] ?? ''));
    $batch = max(1, min(500, intval($data['batch'] ?? 50)));

    if (!in_array($source, ['mtlreader','freewebnovel','woopread'], true)) {
        json_out(['error' => 'Source tidak valid']);
    }

    $cmd = "nohup php /var/www/api/scripts/novel_sync_batch.php "
        . escapeshellarg($source) . " "
        . escapeshellarg((string)$batch)
        . " >> /root/{$source}_cron_live.log 2>&1 & echo $!";

    $pid = trim((string)shell_exec($cmd));
    json_out(['success' => true, 'pid' => $pid, 'source' => $source, 'batch' => $batch]);
}

// ── NOVEL COLLECT + SEED ──────────────────────────────────
if ($action === 'novel_collect_seed' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $source = trim((string)($data['source'] ?? ''));

    if ($source === 'freewebnovel') {
        $cmd = "nohup bash -lc 'python3 /root/collect_novel_urls.py freewebnovel && bash /root/seed_novel_urls.sh freewebnovel /root/freewebnovel_urls.txt' > /root/freewebnovel_fullseed_live.log 2>&1 & echo $!";
    } elseif ($source === 'woopread') {
        $cmd = "nohup bash -lc 'python3 /root/collect_woopread_v2.py && bash /root/seed_novel_urls.sh woopread /root/woopread_urls.txt' > /root/woopread_fullseed_live.log 2>&1 & echo $!";
    } else {
        json_out(['error' => 'Source collect+seed tidak valid']);
    }

    $pid = trim((string)shell_exec($cmd));
    json_out(['success' => true, 'pid' => $pid, 'source' => $source]);
}

// ── NOVEL STOP ────────────────────────────────────────────
if ($action === 'novel_stop' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $source = trim((string)($data['source'] ?? ''));

    if (!in_array($source, ['mtlreader','freewebnovel','woopread'], true)) {
        json_out(['error' => 'Source tidak valid']);
    }

    shell_exec("pkill -f 'novel_sync_batch.php {$source}' 2>/dev/null || true");
    shell_exec("pkill -f 'seed_novel_urls.sh {$source}' 2>/dev/null || true");

    if ($source === 'freewebnovel') {
        shell_exec("pkill -f 'collect_novel_urls.py freewebnovel' 2>/dev/null || true");
    }
    if ($source === 'woopread') {
        shell_exec("pkill -f 'collect_woopread_v2.py' 2>/dev/null || true");
    }

    json_out(['success' => true]);
}

// ── NOVEL VIEW LOG ────────────────────────────────────────
if ($action === 'novel_view_log') {
    $file = $_GET['file'] ?? '';
    $allowed = [
        'mtlreader_cron_live.log',
        'freewebnovel_cron_live.log',
        'woopread_cron_live.log',
        'freewebnovel_fullseed_live.log',
        'woopread_fullseed_live.log',
        'freewebnovel_seed_live.log',
        'woopread_seed_live.log',
        'mtlreader_cron.log',
        'freewebnovel_cron.log',
        'woopread_cron.log',
    ];

    if (!in_array($file, $allowed, true)) json_out(['error' => 'File log novel tidak valid']);

    $path = "/root/" . $file;
    if (!file_exists($path)) json_out(['content' => 'Log belum ada']);

    $content = file_get_contents($path);
    $lines = explode("\n", trim((string)$content));
    json_out(['content' => implode("\n", array_slice($lines, -80))]);
}

json_out(['error' => 'Invalid action']);
