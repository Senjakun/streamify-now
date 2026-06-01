<?php
/**
 * Comments API
 * Endpoints: /api/comments.php?action=list|create|delete|like
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
        $contentId = (int)($_GET['content_id'] ?? 0);
        $episodeId = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : null;
        $chapterId = isset($_GET['chapter_id']) ? (int)$_GET['chapter_id'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        if (!$contentId) {
            http_response_code(400);
            echo json_encode(['error' => 'content_id is required']);
            exit;
        }
        
        $pdo = getDB();
        
        $where = "c.content_id = ? AND c.parent_id IS NULL";
        $params = [$contentId];
        
        if ($episodeId !== null) {
            $where .= " AND c.episode_id = ?";
            $params[] = $episodeId;
        }
        
        if ($chapterId !== null) {
            $where .= " AND c.chapter_id = ?";
            $params[] = $chapterId;
        }
        
        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments c WHERE $where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        // Get comments with user info
        $sql = "
            SELECT c.*, u.username, u.avatar_url, u.rank_label, u.badge, u.badge_icon, u.badge_color,
                   (SELECT COUNT(*) FROM comments r WHERE r.parent_id = c.id) as reply_count
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE $where
            ORDER BY c.created_at DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $comments = $stmt->fetchAll();
        
        // Get replies for each comment
        foreach ($comments as &$comment) {
            if ($comment['reply_count'] > 0) {
                $replyStmt = $pdo->prepare("
                    SELECT c.*, u.username, u.avatar_url, u.rank_label, u.badge, u.badge_icon, u.badge_color
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.parent_id = ?
                    ORDER BY c.created_at ASC
                    LIMIT 5
                ");
                $replyStmt->execute([$comment['id']]);
                $comment['replies'] = $replyStmt->fetchAll();
            } else {
                $comment['replies'] = [];
            }
        }
        
        echo json_encode([
            'comments' => $comments,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    case 'create':
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
        $episodeId = isset($data['episode_id']) ? (int)$data['episode_id'] : null;
        $chapterId = isset($data['chapter_id']) ? (int)$data['chapter_id'] : null;
        $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;
        $commentText = trim($data['comment_text'] ?? '');
        $isSpoiler = (int)($data['is_spoiler'] ?? 0);
        
        if (!$contentId) {
            http_response_code(400);
            echo json_encode(['error' => 'content_id is required']);
            exit;
        }
        
        if (strlen($commentText) < 1 || strlen($commentText) > 1000) {
            http_response_code(400);
            echo json_encode(['error' => 'Komentar harus 1-1000 karakter']);
            exit;
        }
        
        $pdo = getDB();
        

        // ── SPAM FILTER ──────────────────────────────────────────
        $spamWords = ['anjing','bangsat','kontol','memek','ngentot','bajingan','tolol','brengsek','kampret','tai','babi','goblok','idiot','bodoh banget','wa.me','t.me','discord.gg','bit.ly','tinyurl','join grup','join group','daftar sekarang','klik link','hubungi kami di','slot','gacor','situs','togel','judi','bet','scatter','maxwin','jackpot','deposit','withdraw','daftar','login disini','link alternatif'];
        $textLower = strtolower($commentText);

        // 1. Cek kata terlarang
        foreach ($spamWords as $word) {
                error_log("Spam word detected: " . $word);
            if (strpos($textLower, $word) !== false) {
                http_response_code(400);
                echo json_encode(['error' => 'Komentar mengandung kata yang tidak diizinkan']);
                exit;
            }
        }

        // 2. Cek link spam (URL dari domain luar)
        if (preg_match('/https?:\/\/(?!playall\.me|ibb\.co|ibb\.co\.com|imgur\.com|i\.ibb\.co|i\.bb\.co)[\w\-\.]+\.[a-z]{2,}/i', $commentText)) {
            http_response_code(400);
            echo json_encode(['error' => 'Komentar tidak boleh mengandung link eksternal']);
            exit;
        }

        // 3. Cek duplikat dalam 60 detik
        $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id=? AND comment_text=? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)");
        $dupCheck->execute([$user['id'], $commentText]);
        if ($dupCheck->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Komentar duplikat, tunggu 60 detik']);
            exit;
        }

        // 4. Cek flood - max 5 komentar per menit
        $floodCheck = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)");
        $floodCheck->execute([$user['id']]);
        if ($floodCheck->fetchColumn() >= 5) {
            http_response_code(400);
            echo json_encode(['error' => 'Terlalu banyak komentar, tunggu sebentar']);
            exit;
        }

        // 5. Cek promosi
        $promoPatterns = ['/\+62[0-9]{9,}/', '/[0-9]{10,}/', '/@[\w]+\.com/'];
        foreach ($promoPatterns as $pattern) {
            if (preg_match($pattern, $commentText)) {
                http_response_code(400);
                echo json_encode(['error' => 'Komentar terdeteksi sebagai spam']);
                exit;
            }
        }
        // ── END SPAM FILTER ───────────────────────────────────────

        $stmt = $pdo->prepare("
            INSERT INTO comments (user_id, content_id, episode_id, chapter_id, parent_id, comment_text, is_spoiler)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user['id'], $contentId, $episodeId, $chapterId, $parentId, $commentText, $isSpoiler]);
        
        $commentId = $pdo->lastInsertId();

        // Notifikasi email reply
        if ($parentId) {
            try {
                require_once '../config/mailer.php';
                $parentStmt = $pdo->prepare("SELECT c.user_id, u.email, u.username FROM comments c JOIN users u ON c.user_id=u.id WHERE c.id=?");
                $parentStmt->execute([$parentId]);
                $parentComment = $parentStmt->fetch();
                $contentStmt = $pdo->prepare("SELECT title, type, slug FROM content WHERE id=?");
                $contentStmt->execute([$contentId]);
                $content = $contentStmt->fetch();
                if ($parentComment && $parentComment['user_id'] != $user['id'] && $content) {
                    $url = SITE_URL . '/' . $content['type'] . '/' . $content['slug'];
                    sendCommentReplyNotif($parentComment['email'], $parentComment['username'], $user['username'], $commentText, $content['title'], $url);
                }
            } catch (Exception $e) {
                error_log('Notify error: ' . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'comment' => [
                'id' => $commentId,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'avatar_url' => $user['avatar_url'],
                'content_id' => $contentId,
                'episode_id' => $episodeId,
                'chapter_id' => $chapterId,
                'parent_id' => $parentId,
                'comment_text' => $commentText,
                'is_spoiler' => $isSpoiler,
                'likes_count' => 0,
                'created_at' => gmdate('Y-m-d H:i:s')
            ]
        ]);
        break;
        
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
        $commentId = (int)($data['comment_id'] ?? $_GET['comment_id'] ?? 0);
        
        if (!$commentId) {
            http_response_code(400);
            echo json_encode(['error' => 'comment_id is required']);
            exit;
        }
        
        $pdo = getDB();
        
        // Check ownership or admin
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['error' => 'Komentar tidak ditemukan']);
            exit;
        }
        
        $isAdmin = in_array('admin', $user['roles']) || in_array('moderator', $user['roles']);
        if ($comment['user_id'] != $user['id'] && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Tidak diizinkan']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        
        echo json_encode(['success' => true]);
        break;
        
    case 'like':
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
        $commentId = (int)($data['comment_id'] ?? 0);
        
        if (!$commentId) {
            http_response_code(400);
            echo json_encode(['error' => 'comment_id is required']);
            exit;
        }
        
        $pdo = getDB();
        
        // Check if already liked
        $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$user['id'], $commentId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Unlike
            $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE id = ?");
            $stmt->execute([$existing['id']]);
            $stmt = $pdo->prepare("UPDATE comments SET likes_count = likes_count - 1 WHERE id = ?");
            $stmt->execute([$commentId]);
            $liked = false;
        } else {
            // Like
            $stmt = $pdo->prepare("INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)");
            $stmt->execute([$user['id'], $commentId]);
            $stmt = $pdo->prepare("UPDATE comments SET likes_count = likes_count + 1 WHERE id = ?");
            $stmt->execute([$commentId]);
            $liked = true;
        }
        
        // Get updated count
        $stmt = $pdo->prepare("SELECT likes_count FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $likesCount = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'likes_count' => (int)$likesCount
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
