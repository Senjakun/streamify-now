<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$source_url = $_GET['url'] ?? '';
if (!$source_url) { echo json_encode(['embed' => null, 'servers' => []]); exit; }

$ch = curl_init($source_url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15, CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', CURLOPT_SSL_VERIFYPEER=>false]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$html || $code >= 400) { echo json_encode(['embed' => null, 'servers' => []]); exit; }

$servers = [];
$embed = null;

// Otakudesu: find iframe or video sources
if (preg_match_all('#<iframe[^>]+src=["\']([^"\']+)["\']#i', $html, $m)) {
    foreach ($m[1] as $i => $url) {
        $url = html_entity_decode($url);
        if (stripos($url, 'javascript') !== false) continue;
        $label = "Server " . ($i + 1);
        if (stripos($url, 'desustream') !== false || stripos($url, 'desudrive') !== false) $label = "DesuStream";
        elseif (stripos($url, 'mp4upload') !== false) $label = "MP4Upload";
        elseif (stripos($url, 'yourupload') !== false) $label = "YourUpload";
        elseif (stripos($url, 'streamsb') !== false || stripos($url, 'sbembed') !== false) $label = "StreamSB";
        $servers[] = ['label' => $label, 'url' => $url];
    }
}

// Also check for nonce-based players (otakudesu uses ajax post for mirrors)
if (preg_match_all('#data-content=["\']([^"\']+)["\']#', $html, $m)) {
    foreach ($m[1] as $encoded) {
        $decoded = base64_decode($encoded);
        if ($decoded && preg_match('#src=["\']([^"\']+)["\']#i', $decoded, $dm)) {
            $url = html_entity_decode($dm[1]);
            $servers[] = ['label' => 'Mirror', 'url' => $url];
        }
    }
}

// Check for direct video/source tags
if (preg_match_all('#<source[^>]+src=["\']([^"\']+)["\']#i', $html, $m)) {
    foreach ($m[1] as $url) {
        $servers[] = ['label' => 'Direct', 'url' => html_entity_decode($url)];
    }
}

if (!empty($servers)) $embed = $servers[0]['url'];

echo json_encode(['embed' => $embed, 'servers' => $servers]);
