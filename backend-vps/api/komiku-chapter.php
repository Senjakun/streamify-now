<?php
// Fetch chapter images from Komiku on-demand
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$url = $_GET['url'] ?? '';
if (!$url || !preg_match('#komiku#i', $url)) { echo json_encode(['images' => []]); exit; }

$ctx = stream_context_create([
    'http' => ['header' => "Referer: https://komiku.org/\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n", 'timeout' => 20],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
]);
$html = @file_get_contents($url, false, $ctx);
if (!$html) { echo json_encode(['images' => []]); exit; }

preg_match_all('#<img[^>]+(?:data-src|src)="(https://img\.komiku[^"]+\.(?:jpg|png|webp|jpeg))"#i', $html, $m);
$images = array_values(array_filter($m[1], fn($u) => !preg_match('#komikuplus|logo|banner|asset/img|thumbnail#i', $u)));

echo json_encode(['images' => $images]);
