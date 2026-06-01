<?php
// On-demand readnovelfull chapter content
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$url = $_GET['url'] ?? '';
if (!$url || !preg_match('#readnovelfull#i', $url)) { echo json_encode(['content' => '']); exit; }
$ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0\r\nReferer: https://readnovelfull.com/\r\n", 'timeout' => 20], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$html = @file_get_contents($url, false, $ctx);
if (!$html) { echo json_encode(['content' => '']); exit; }
$content = '';
if (preg_match('#<div id="chr-content"[^>]*>(.*?)</div>\s*(?:<div|<script)#si', $html, $m)) $content = $m[1];
// clean ads/scripts
$content = preg_replace('#<script[^>]*>.*?</script>#si', '', $content);
$content = preg_replace('#<div[^>]*>\s*</div>#si', '', $content);
$content = preg_replace('#<ins[^>]*>.*?</ins>#si', '', $content);
echo json_encode(['content' => trim($content)]);
