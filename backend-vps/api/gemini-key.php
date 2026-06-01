<?php
// Validate a Gemini API key, then add to shared pool
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$pdo = getDB();
$body = json_decode(file_get_contents('php://input'), true);
$key = trim($body['key'] ?? '');
$donor = substr(trim($body['donor'] ?? 'Anonim'), 0, 80);
if (!$key) { echo json_encode(['ok' => false, 'error' => 'Key kosong']); exit; }

// Test the key across multiple models. Valid if any 200, OR if 429 (key authenticated but rate-limited).
$models = ['gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-2.5-flash-lite'];
$valid = false; $rateLimited = false; $lastMsg = 'Key tidak valid';
foreach ($models as $model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . urlencode($key);
    $payload = json_encode(['contents' => [['parts' => [['text' => 'hi']]]]]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => false]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($code === 200 && isset($data['candidates'])) { $valid = true; break; }
    if ($code === 429) { $rateLimited = true; continue; } // key authenticated, just quota/model-limit
    $lastMsg = $data['error']['message'] ?? "Key tidak valid (HTTP $code)";
    if ($code === 400 || $code === 403) break; // genuinely bad key
}

if (!$valid && !$rateLimited) {
    echo json_encode(['ok' => false, 'error' => $lastMsg]);
    exit;
}

// Valid (or valid-but-rate-limited) — add to pool
try {
    $pdo->prepare("INSERT INTO gemini_keys (api_key, donor, status) VALUES (?,?,'active') ON DUPLICATE KEY UPDATE status='active', fail_count=0")
        ->execute([$key, $donor]);
    $count = $pdo->query("SELECT COUNT(*) FROM gemini_keys WHERE status='active'")->fetchColumn();
    $note = $rateLimited && !$valid ? ' (key valid tapi lagi kena limit harian — tetap diterima, aktif lagi nanti)' : '';
    echo json_encode(['ok' => true, 'message' => 'Key valid & ditambahkan! Terima kasih 🙏' . $note, 'pool' => (int)$count]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Gagal simpan: ' . $e->getMessage()]);
}
