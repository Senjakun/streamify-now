<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../config/auth_helper.php';

function pa_fail(string $msg, int $code = 400, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['error' => $msg], $extra));
    exit;
}

function pa_public_root(): string {
    return '/var/www/streamify/public';
}

function pa_avatar_root(): string {
    return pa_public_root() . '/avatars';
}

function pa_random_name(int $userId): string {
    return 'avatar_' . $userId . '_' . bin2hex(random_bytes(8));
}

function pa_delete_old_local_avatar(?string $avatarUrl): void {
    if (!$avatarUrl) return;
    if (!str_starts_with($avatarUrl, '/avatars/')) return;

    $full = pa_public_root() . $avatarUrl;
    if (is_file($full)) {
        @unlink($full);
    }
}

function pa_make_dirs(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Gagal membuat folder avatar');
    }
}

function pa_detect_mime(string $tmpFile): string {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmpFile) : '';
    if ($finfo) finfo_close($finfo);
    return (string)$mime;
}

function pa_create_image(string $tmpFile, string $mime) {
    return match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmpFile),
        'image/png'  => imagecreatefrompng($tmpFile),
        'image/gif'  => imagecreatefromgif($tmpFile),
        'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmpFile) : false,
        default => false,
    };
}

function pa_resize_to_webp(string $tmpFile, string $destFile, int $maxSize = 320, int $quality = 82): void {
    $mime = pa_detect_mime($tmpFile);
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Format harus JPG/PNG/GIF/WEBP');
    }

    if (!function_exists('imagewebp')) {
        throw new RuntimeException('GD/WebP tidak tersedia di server');
    }

    $src = pa_create_image($tmpFile, $mime);
    if (!$src) {
        throw new RuntimeException('Gagal membaca gambar');
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    if ($srcW < 1 || $srcH < 1) {
        imagedestroy($src);
        throw new RuntimeException('Ukuran gambar tidak valid');
    }

    $ratio = min($maxSize / $srcW, $maxSize / $srcH, 1);
    $newW = max(1, (int)round($srcW * $ratio));
    $newH = max(1, (int)round($srcH * $ratio));

    $dst = imagecreatetruecolor($newW, $newH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

    if (!imagewebp($dst, $destFile, $quality)) {
        imagedestroy($src);
        imagedestroy($dst);
        throw new RuntimeException('Gagal menyimpan avatar webp');
    }

    imagedestroy($src);
    imagedestroy($dst);
}

$user = getCurrentUser();
if (!$user) {
    pa_fail('Unauthorized', 401);
}

if (!isset($_FILES['avatar'])) {
    pa_fail('File avatar tidak ditemukan');
}

$file = $_FILES['avatar'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    pa_fail('Upload avatar gagal');
}

if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
    pa_fail('File maksimal 2MB');
}

if (!is_uploaded_file($file['tmp_name'])) {
    pa_fail('Upload file tidak valid');
}

try {
    $userId = (int)$user['id'];
    $subDir = date('Y/m');
    $diskDir = pa_avatar_root() . '/' . $subDir;
    pa_make_dirs($diskDir);

    $baseName = pa_random_name($userId);
    $diskPath = $diskDir . '/' . $baseName . '.webp';
    $publicPath = '/avatars/' . $subDir . '/' . $baseName . '.webp';

    pa_resize_to_webp($file['tmp_name'], $diskPath, 320, 82);

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$userId]);
    $oldAvatar = $stmt->fetchColumn();

    $pdo->prepare("UPDATE users SET avatar_url=? WHERE id=?")->execute([$publicPath, $userId]);

    if ($oldAvatar && $oldAvatar !== $publicPath) {
        pa_delete_old_local_avatar((string)$oldAvatar);
    }

    echo json_encode([
        'success' => true,
        'avatar_url' => $publicPath,
    ]);
} catch (Throwable $e) {
    pa_fail('Gagal upload avatar', 500, ['detail' => $e->getMessage()]);
}
