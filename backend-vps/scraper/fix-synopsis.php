<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$DEEPSEEK_KEY = 'sk-bb7755362cf042d6a1c2e982a122dc5a';

function fetchJikan($query) {
    $url = "https://api.jikan.moe/v4/anime?q=" . urlencode($query) . "&limit=1";
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_USERAGENT=>'Mozilla/5.0']);
    $r = curl_exec($ch); curl_close($ch);
    $data = json_decode($r, true);
    return $data['data'][0]['synopsis'] ?? null;
}

function translateWithDeepSeek($text, $apiKey) {
    if (empty($text)) return null;
    $url = "https://api.deepseek.com/chat/completions";
    $body = json_encode([
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => 'Kamu adalah penerjemah profesional anime. Terjemahkan sinopsis anime dari Bahasa Inggris ke Bahasa Indonesia yang natural, menarik, dan mudah dipahami. Jangan tambahkan komentar apapun, langsung terjemahannya saja.'],
            ['role' => 'user', 'content' => $text]
        ],
        'max_tokens' => 4000,
        'temperature' => 0.3
    ]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $r = curl_exec($ch); curl_close($ch);
    $data = json_decode($r, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

$stmt = $pdo->query("SELECT id, title FROM content WHERE type='anime' AND (description IS NULL OR description LIKE '%MYMEMORY%' OR description LIKE '%otakudesu%' OR description LIKE '%sub indo%' OR description LIKE '%Nonton%')");
$animes = $stmt->fetchAll();
$total = count($animes);
echo "Total: $total anime\n";

foreach ($animes as $idx => $anime) {
    $num = $idx + 1;
    echo "[$num/$total] {$anime['title']}\n";
    try {
        $synopsis_en = fetchJikan($anime['title']);
        if (!$synopsis_en) { echo "  Skip: tidak ada di Jikan\n"; sleep(1); continue; }

        $synopsis_id = translateWithDeepSeek($synopsis_en, $DEEPSEEK_KEY);
        if (!$synopsis_id) { echo "  Error: DeepSeek gagal\n"; continue; }

        $stmt2 = $pdo->prepare("UPDATE content SET description=?, updated_at=NOW() WHERE id=?");
        $stmt2->execute([trim($synopsis_id), $anime['id']]);
        echo "  OK: " . substr($synopsis_id, 0, 80) . "...\n";
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
    sleep(1);
}
echo "SELESAI!\n";
