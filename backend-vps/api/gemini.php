<?php
// Gemini translation - uses shared donated key pool (rotation) or user-provided key
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$pdo = getDB();
$body = json_decode(file_get_contents('php://input'), true);
$text = $body['text'] ?? '';
$target = $body['target'] ?? 'Indonesia';
$userKey = trim($body['key'] ?? '');
if (!$text) { echo json_encode(['error' => 'text required']); exit; }

$prompt = "Terjemahkan teks novel berikut ke Bahasa $target yang natural dan enak dibaca. Hanya berikan hasil terjemahan tanpa komentar/catatan apapun:\n\n" . mb_substr($text, 0, 14000);

// Models: free-tier friendly first (higher RPM), then better quality
$models = ['gemini-2.0-flash', 'gemini-2.5-flash-lite', 'gemini-1.5-flash', 'gemini-2.5-flash'];

function callGemini($key, $model, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . urlencode($key);
    $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 45, CURLOPT_SSL_VERIFYPEER => false]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode($res, true)];
}

// Build key list: user key first (if given), else pool (random order to spread RPM)
$keys = [];
if ($userKey) {
    $keys[] = ['id' => null, 'api_key' => $userKey];
} else {
    $rows = $pdo->query("SELECT id, api_key FROM gemini_keys WHERE status='active' ORDER BY RAND() LIMIT 10")->fetchAll();
    $keys = $rows;
}
if (!$keys) { echo json_encode(['error' => 'no_key', 'message' => 'Belum ada API key. Donasikan key kamu atau masukkan key sendiri.']); exit; }

$lastErr = 'unknown';
foreach ($keys as $k) {
    foreach ($models as $model) {
        [$code, $data] = callGemini($k['api_key'], $model, $prompt);
        if ($code === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            if (!empty($k['id'])) $pdo->prepare("UPDATE gemini_keys SET last_used=NOW(), fail_count=0 WHERE id=?")->execute([$k['id']]);
            echo json_encode(['success' => true, 'text' => $data['candidates'][0]['content']['parts'][0]['text'], 'model' => $model]);
            exit;
        }
        $lastErr = $data['error']['message'] ?? "HTTP $code";
        // 429 = rate limit / model not available on free tier → try NEXT MODEL on same key
        if ($code === 429) continue;
        // 400/403 = invalid key → mark dead, skip to next key
        if (($code === 400 || $code === 403) && !empty($k['id'])) {
            $pdo->prepare("UPDATE gemini_keys SET fail_count=fail_count+1, status=IF(fail_count>=2,'dead','active') WHERE id=?")->execute([$k['id']]);
            break;
        }
    }
}
echo json_encode(['error' => $lastErr]);
