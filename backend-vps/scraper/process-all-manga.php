<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/manga-scraper.php';

$pdo = getDB();
$scraper = new MangaScraper();

// Ambil semua manga dari v1.kiryuu.to yang belum diproses detailnya (atau semua)
$stmt = $pdo->query("SELECT id, source_url FROM content WHERE type = 'manga' AND source_url LIKE '%v1.kiryuu.to%'");
$mangas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($mangas);
echo "Total manga: $total\n";

$count = 0;
foreach ($mangas as $manga) {
    $count++;
    echo "[$count/$total] Memproses: " . $manga['source_url'] . "\n";
    try {
        $id = $scraper->scrapeDetail($manga['source_url']);
        echo "  -> OK, ID: $id\n";
    } catch (Exception $e) {
        echo "  -> GAGAL: " . $e->getMessage() . "\n";
    }
    sleep(1); // jeda 1 detik agar tidak kena blokir
}

echo "Selesai.\n";
