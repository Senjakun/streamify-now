<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/novel_common.php';
require_once __DIR__ . '/novel_mtlreader.php';
require_once __DIR__ . '/novel_freewebnovel.php';
require_once __DIR__ . '/novel_woopread.php';

$pdo = nc_db();
$action = trim((string)($_GET['action'] ?? 'list'));
$source = trim((string)($_GET['source'] ?? ''));

if ($action === 'seed') {
    if ($source === '') {
        nc_fail('source wajib diisi');
    }

    switch ($source) {
        case 'mtlreader':
            nc_ok(nm_seed($pdo, $_GET));
            break;
        case 'freewebnovel':
            nc_ok(nf_seed($pdo, $_GET));
            break;
        case 'woopread':
            nc_ok(nw_seed($pdo, $_GET));
            break;
        default:
            nc_fail('source tidak dikenali');
    }
}

if ($action === 'sync_detail') {
    $slug = trim((string)($_GET['slug'] ?? ''));
    if ($slug === '') {
        nc_fail('slug wajib diisi');
    }

    $novel = nc_novel_by_slug($pdo, $slug);
    if (!$novel) {
        nc_fail('Novel tidak ditemukan', 404);
    }

    switch ($novel['source'] ?? '') {
        case 'mtlreader':
            nc_ok(nm_sync_detail($pdo, $novel));
            break;
        case 'freewebnovel':
            nc_ok(nf_sync_detail($pdo, $novel));
            break;
        case 'woopread':
            nc_ok(nw_sync_detail($pdo, $novel));
            break;
        default:
            nc_fail('source novel tidak didukung', 400);
    }
}

if ($action === 'list') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = max(1, min(50, (int)($_GET['limit'] ?? 24)));
    $offset = ($page - 1) * $limit;

    $status  = trim((string)($_GET['status'] ?? ''));
    $search  = trim((string)($_GET['search'] ?? ''));
    $country = trim((string)($_GET['country'] ?? ''));
    $sourceQ = trim((string)($_GET['source'] ?? ''));
    $genre   = trim((string)($_GET['genre'] ?? ''));
    $orderby = trim((string)($_GET['orderby'] ?? 'terbaru'));

    $where = ['1=1'];
    $params = [];
    $rankSql = '';

    if ($status !== '' && strtolower($status) !== 'all') {
        if (strtolower($status) === 'tamat' || strtolower($status) === 'completed') {
            $where[] = '(LOWER(status) = ? OR LOWER(status) = ?)';
            $params[] = 'completed';
            $params[] = 'tamat';
        } else {
            $where[] = 'LOWER(status) = ?';
            $params[] = 'ongoing';
        }
    }

    if ($sourceQ !== '' && nc_hasc($pdo, 'novels', 'source')) {
        $where[] = 'source = ?';
        $params[] = $sourceQ;
    }

    if ($country !== '' && nc_hasc($pdo, 'novels', 'country')) {
        $where[] = 'LOWER(country) = ?';
        $params[] = strtolower($country);
    }

    if ($genre !== '') {
        $where[] = 'genres LIKE ?';
        $params[] = '%"' . $genre . '"%';
    }

    if ($search !== '') {
        $q = mb_strtolower($search, 'UTF-8');
        $where[] = '(LOWER(title) LIKE ? OR LOWER(author) LIKE ? OR LOWER(description) LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';

        $rankSql = "CASE
            WHEN LOWER(title) = ? THEN 0
            WHEN LOWER(title) LIKE ? THEN 1
            WHEN LOWER(title) LIKE ? THEN 2
            WHEN LOWER(author) LIKE ? THEN 3
            WHEN LOWER(description) LIKE ? THEN 4
            ELSE 5
        END";
    }

    $whereSql = implode(' AND ', $where);

    $allowedOrder = [
        'terbaru' => (nc_hasc($pdo, 'novels', 'updated_at') ? 'updated_at DESC, id DESC' : 'id DESC'),
        'latest' => (nc_hasc($pdo, 'novels', 'updated_at') ? 'updated_at DESC, id DESC' : 'id DESC'),
        'rating' => 'rating DESC, id DESC',
        'chapter' => 'total_chapters DESC, latest_chapter DESC, id DESC',
        'chapters' => 'total_chapters DESC, latest_chapter DESC, id DESC',
        'az' => 'title ASC',
        'title' => 'title ASC',
    ];
    $orderSql = $allowedOrder[$orderby] ?? $allowedOrder['terbaru'];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM novels WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $limit));

    $select = "SELECT id, slug, title, source, poster_url, author, status, genres, rating, total_chapters, latest_chapter";
    if (nc_hasc($pdo, 'novels', 'country')) {
        $select .= ", country";
    }

    $queryParams = $params;

    if ($search !== '') {
        $q = mb_strtolower($search, 'UTF-8');
        $queryParams[] = $q;
        $queryParams[] = $q . '%';
        $queryParams[] = '%' . $q . '%';
        $queryParams[] = '%' . $q . '%';
        $queryParams[] = '%' . $q . '%';
        $sql = "$select FROM novels WHERE $whereSql ORDER BY $rankSql ASC, $orderSql LIMIT $limit OFFSET $offset";
    } else {
        $sql = "$select FROM novels WHERE $whereSql ORDER BY $orderSql LIMIT $limit OFFSET $offset";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['genres'] = nc_jarr($row['genres'] ?? '[]');
        $row['rating'] = (float)($row['rating'] ?? 0);
        $row['total_chapters'] = (int)($row['total_chapters'] ?? 0);
        $row['latest_chapter'] = (int)($row['latest_chapter'] ?? 0);
    }

    nc_ok([
        'content' => $rows,
        'pagination' => [
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ],
    ]);
}

if ($action === 'detail') {
    $slug = trim((string)($_GET['slug'] ?? ''));
    if ($slug === '') {
        nc_fail('slug wajib diisi');
    }

    $novel = nc_novel_by_slug($pdo, $slug);
    if (!$novel) {
        nc_fail('Novel tidak ditemukan', 404);
    }

    $chapters = nc_chapter_rows($pdo, (int)$novel['id']);

    if (!$chapters) {
        switch ($novel['source'] ?? '') {
            case 'mtlreader':
                nm_sync_detail($pdo, $novel);
                break;
            case 'freewebnovel':
                nf_sync_detail($pdo, $novel);
                break;
            case 'woopread':
                nw_sync_detail($pdo, $novel);
                break;
        }

        $novel = nc_novel_by_slug($pdo, $slug);
        $chapters = nc_chapter_rows($pdo, (int)$novel['id']);
    }

    $novel['id'] = (int)$novel['id'];
    $novel['genres'] = nc_jarr($novel['genres'] ?? '[]');
    $novel['tags'] = nc_jarr($novel['tags'] ?? '[]');
    $novel['rating'] = (float)($novel['rating'] ?? 0);
    $novel['total_chapters'] = (int)($novel['total_chapters'] ?? count($chapters));
    $novel['latest_chapter'] = (int)($novel['latest_chapter'] ?? count($chapters));
    $novel['chapters'] = $chapters;

    nc_ok($novel);
}

if ($action === 'chapter') {
    $slug = trim((string)($_GET['novel'] ?? ''));
    $chapterNumber = max(1, (int)($_GET['chapter'] ?? 0));

    if ($slug === '' || $chapterNumber < 1) {
        nc_fail('parameter tidak valid');
    }

    $novel = nc_novel_by_slug($pdo, $slug);
    if (!$novel) {
        nc_fail('Novel tidak ditemukan', 404);
    }

    switch ($novel['source'] ?? '') {
        case 'mtlreader':
            nc_ok(nm_chapter($pdo, $novel, $chapterNumber));
            break;
        case 'freewebnovel':
            nc_ok(nf_chapter($pdo, $novel, $chapterNumber));
            break;
        case 'woopread':
            nc_ok(nw_chapter($pdo, $novel, $chapterNumber));
            break;
        default:
            nc_fail('source novel tidak didukung', 400);
    }
}

nc_fail('Invalid action', 404);
