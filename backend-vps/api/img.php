<?php
// Image proxy - bypass hotlink protection
$url = $_GET['url'] ?? '';
if (!$url || !preg_match('#^https?://#', $url)) { http_response_code(400); exit; }

// Only allow image domains we use
$allowed = ['otakudesu.blog','otakudesu.live','v5.kiryuu.to','v1.kiryuu.to','kiryuu','anichin.cafe','anichin.moe','cdn','img','wp-content'];
$host = parse_url($url, PHP_URL_HOST);
$ok = false;
foreach ($allowed as $d) { if (stripos($host, $d) !== false) { $ok = true; break; } }
if (!$ok) { http_response_code(403); exit; }

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
]);
$data = curl_exec($ch);
$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || !$data) { http_response_code(404); exit; }

header("Content-Type: " . ($type ?: 'image/jpeg'));
header("Cache-Control: public, max-age=604800");
header("Access-Control-Allow-Origin: *");
echo $data;
