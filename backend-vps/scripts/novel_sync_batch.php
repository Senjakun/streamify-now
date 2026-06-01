#!/usr/bin/env php
<?php
require_once __DIR__ . '/../api/novel_common.php';
require_once __DIR__ . '/../api/novel_mtlreader.php';
require_once __DIR__ . '/../api/novel_freewebnovel.php';
require_once __DIR__ . '/../api/novel_woopread.php';

$source = $argv[1] ?? '';
$batch  = max(1, min(500, (int)($argv[2] ?? 50)));

$allowed = ['mtlreader', 'freewebnovel', 'woopread'];
if (!in_array($source, $allowed, true)) {
    fwrite(STDERR, "source tidak valid\n");
    exit(1);
}

$pdo = nc_db();
$cursorFile = "/root/.novel_sync_{$source}.cursor";
$cursor = is_file($cursorFile) ? (int)trim((string)file_get_contents($cursorFile)) : 0;

function load_rows(PDO $pdo, string $source, int $cursor, int $batch): array {
    $stmt = $pdo->prepare("SELECT * FROM novels WHERE source = ? AND id > ? ORDER BY id ASC LIMIT {$batch}");
    $stmt->execute([$source, $cursor]);
    return $stmt->fetchAll() ?: [];
}

$rows = load_rows($pdo, $source, $cursor, $batch);
if (!$rows && $cursor > 0) {
    $cursor = 0;
    file_put_contents($cursorFile, '0');
    $rows = load_rows($pdo, $source, 0, $batch);
}

if (!$rows) {
    echo "tidak ada row untuk source {$source}\n";
    exit(0);
}

$lastId = $cursor;
foreach ($rows as $row) {
    $lastId = (int)$row['id'];
    $slug = $row['slug'] ?? '';
    echo "[{$source}] sync id={$lastId} slug={$slug}\n";

    try {
        switch ($source) {
            case 'mtlreader':
                nm_sync_detail($pdo, $row);
                break;
            case 'freewebnovel':
                nf_sync_detail($pdo, $row);
                break;
            case 'woopread':
                nw_sync_detail($pdo, $row);
                break;
        }
        echo "   OK\n";
    } catch (Throwable $e) {
        echo "   GAGAL: " . $e->getMessage() . "\n";
    }

    usleep(200000);
}

file_put_contents($cursorFile, (string)$lastId);
echo "selesai, cursor={$lastId}\n";
