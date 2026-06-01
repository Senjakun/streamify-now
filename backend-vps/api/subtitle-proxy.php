<?php
header('Access-Control-Allow-Origin: *');

// $_GET sudah auto-decode, jadi URL sudah berisi karakter China
$url = $_GET['url'] ?? '';
if (!$url) { http_response_code(400); exit; }

if (!str_contains($url, 'velolo.tv')) { http_response_code(403); exit; }

// Encode karakter non-ASCII
$url = preg_replace_callback('/[^\x00-\x7F]/', function($m) {
    return rawurlencode($m[0]);
}, $url);

$ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
$srt = @file_get_contents($url, false, $ctx);
if (!$srt) { header('Content-Type: text/vtt'); echo "WEBVTT\n\n"; exit; }

$vtt = "WEBVTT\n\n";
$vtt .= preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $srt);

header('Content-Type: text/vtt; charset=utf-8');
echo $vtt;
