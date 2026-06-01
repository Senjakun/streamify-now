<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/manga-scraper.php';

$pdo = getDB();
$scraper = new MangaScraper();

// Ambil semua chapter dengan images kosong
$stmt = $pdo->query("
    SELECT id, source_url 
    FROM chapters 
    WHERE images IS NULL 
       OR images = '' 
       OR images = '[]' 
       OR JSON_LENGTH(images) = 0
");
$chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($chapters);
echo "Total chapter yang perlu diambil gambarnya: $total\n";

$count = 0;
$success = 0;
$failed = 0;

foreach ($chapters as $ch) {
    $count++;
    echo "[$count/$total] Memproses chapter ID {$ch['id']}: {$ch['source_url']}\n";
    try {
        $result = $scraper->scrapeChapter($ch['source_url']);
        if (!empty($result['images'])) {
            $update = $pdo->prepare("UPDATE chapters SET images = ? WHERE id = ?");
            $update->execute([json_encode($result['images']), $ch['id']]);
            echo "  -> Berhasil, " . count($result['images']) . " gambar\n";
            $success++;
        } else {
            echo "  -> Tidak ada gambar ditemukan\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "  -> ERROR: " . $e->getMessage() . "\n";
        $failed++;
    }
    sleep(1); // jeda 1 detik
}

echo "\nSelesai. Berhasil: $success, Gagal: $failed\n";
