<?php
// Return ALL video servers (label + embed) for an animexin episode URL.
// Falls back to title+ep search if no direct URL.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$url = trim($_GET['url'] ?? '');
$title = trim($_GET['title'] ?? '');
$ep = (int)($_GET['ep'] ?? 0);
$ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n", 'timeout' => 14], 'ssl' => ['verify_peer' => false]]);

// Resolve URL via search if not given/not animexin
if ((!$url || strpos($url, 'animexin') === false) && $title && $ep) {
    $sh = @file_get_contents('https://animexin.dev/?s=' . urlencode($title), false, $ctx);
    if ($sh && preg_match_all('#href="(https://animexin\.dev/anime/([^"/]+)/)"#i', $sh, $m)) {
        $want = preg_replace('/[^a-z0-9]/', '', strtolower($title));
        $best = ''; $bs = 0;
        foreach (array_unique($m[2]) as $slug) {
            similar_text($want, preg_replace('/[^a-z0-9]/', '', strtolower($slug)), $pct);
            if ($pct > $bs) { $bs = $pct; $best = $m[1][array_search($slug, $m[2])]; }
        }
        if ($best && $bs > 55) {
            $sp = @file_get_contents($best, false, $ctx);
            if ($sp && preg_match('#href="(https://animexin\.dev/[^"]*-episode-' . $ep . '-[^"]+)"#i', $sp, $em)) $url = $em[1];
        }
    }
}

if (!$url) { echo json_encode(['servers' => []]); exit; }

$h = @file_get_contents($url, false, $ctx);
$servers = [];
if ($h && preg_match('#<select[^>]*class="[^"]*mirror[^"]*"[^>]*>(.*?)</select>#is', $h, $sel)) {
    preg_match_all('#<option[^>]*value="([^"]+)"[^>]*>([^<]+)</option>#i', $sel[1], $o);
    for ($i = 0; $i < count($o[2]); $i++) {
        $label = trim(html_entity_decode($o[2][$i]));
        if (stripos($label, 'select') !== false || $o[1][$i] === '') continue;
        $dec = base64_decode($o[1][$i]);
        if (preg_match('#src="([^"]+)"#i', $dec, $s)) {
            $embed = $s[1];
            if (strpos($embed, '//') === 0) $embed = 'https:' . $embed;
            $servers[] = ['label' => $label, 'embed' => $embed];
        }
    }
}

// Fallback: anichin (single server, has ads) for titles animexin lacks
if (empty($servers) && strpos($url, 'anichin') !== false && $h) {
    if (preg_match('#<iframe[^>]*src=["\']([^"\']*anichin\.stream[^"\']*)["\']#i', $h, $am)) {
        $servers[] = ['label' => 'Server 1 (Sub Indo)', 'embed' => $am[1]];
    }
}

// Downloads: Mega (embed->file) from servers + cloud links on page
$downloads = [];
foreach ($servers as $s) {
    if (stripos($s['embed'], 'mega.nz/embed/') !== false) {
        $downloads[] = ['label' => $s['label'], 'url' => str_replace('/embed/', '/file/', $s['embed'])];
    }
}
if ($h && preg_match_all('#href="(https?://(?:terabox\.com|pixeldrain\.com|gofile\.io|krakenfiles\.com|drive\.google\.com)[^"]+)"#i', $h, $dm)) {
    foreach (array_unique($dm[1]) as $i => $du) {
        $host = parse_url($du, PHP_URL_HOST);
        $downloads[] = ['label' => ucfirst(explode('.', $host)[0]) . ' ' . ($i + 1), 'url' => $du];
    }
}
echo json_encode(['servers' => $servers, 'downloads' => $downloads]);
