<?php
// Otakudesu anime resolver: list streaming/download mirrors (tokens) + resolve a token on-demand.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$url = trim($_GET['url'] ?? '');
$token = trim($_GET['token'] ?? '');
$rurl = trim($_GET['rurl'] ?? '');
$rkey = trim($_GET['rkey'] ?? '');
if (!$url && !$token) { echo json_encode(['servers' => [], 'downloads' => []]); exit; }

$ctx = stream_context_create(['http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nReferer: https://otakudesu.live/\r\n", 'timeout' => 15], 'ssl' => ['verify_peer' => false]]);

// RESOLVE mode: POST token to mirror resolver
if ($token && $rurl) {
    $ch = curl_init($rurl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['token' => $token]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-API-Key: ' . $rkey, 'User-Agent: Mozilla/5.0', 'Referer: https://otakudesu.live/'],
        CURLOPT_TIMEOUT => 18, CURLOPT_SSL_VERIFYPEER => false]);
    $r = curl_exec($ch); curl_close($ch);
    $d = json_decode($r, true);
    $embed = $d['data']['resolved_url'] ?? '';
    if (!$embed && !empty($d['data']['embed_html']) && preg_match('#src="([^"]+)"#i', $d['data']['embed_html'], $m)) $embed = html_entity_decode($m[1]);
    echo json_encode(['embed' => $embed]);
    exit;
}

// LIST mode: fetch episode page, extract resolver url/key + grouped tokens
$h = @file_get_contents($url, false, $ctx);
if (!$h) { echo json_encode(['servers' => [], 'downloads' => []]); exit; }
preg_match('#data-mirror-resolver-url="([^"]+)"#i', $h, $u);
preg_match('#data-mirror-resolver-key="([^"]*)"#i', $h, $k);
$resolverUrl = html_entity_decode($u[1] ?? '');
$resolverKey = $k[1] ?? '';

$servers = []; $downloads = [];

// === otakudesu.blog (classic WP): data-content tokens + .download list ===
if (!$resolverUrl && preg_match('#data-content="#', $h)) {
    // Shared backend resolver; fetch+cache key from a .live episode
    $resolverUrl = 'https://web.videylist.site/api/episodes/resolve-mirror';
    $cache = sys_get_temp_dir() . '/otk_rkey';
    if (file_exists($cache) && time() - filemtime($cache) < 3600 && trim(@file_get_contents($cache))) {
        $resolverKey = trim(file_get_contents($cache));
    } else {
        // find a known .live episode URL (from DB) and pull its key
        $liveEp = '';
        try {
            require_once __DIR__ . '/../config/database.php';
            $liveEp = getDB()->query("SELECT source_url FROM episodes WHERE source_url LIKE '%otakudesu.live/episodes/%' LIMIT 1")->fetchColumn();
        } catch (Exception $e) {}
        if (!$liveEp) $liveEp = 'https://otakudesu.live/episodes/omae-gotoki-ga-maou-ni-kateru-to-omouna-episode-1-subtitle-indonesia';
        $ep = @file_get_contents($liveEp, false, $ctx);
        if ($ep && preg_match('#data-mirror-resolver-key="([^"]*)"#i', $ep, $km)) {
            $resolverKey = $km[1];
            @file_put_contents($cache, $resolverKey);
        }
    }
    // Default reliable server: main desustream iframe (plays directly, no resolve)
    if (preg_match('#<iframe[^>]+src="([^"]*desustream[^"]*)"#i', $h, $mf)) {
        $servers[] = ['label' => 'Server Utama', 'embed' => html_entity_decode($mf[1])];
    }
    // Streaming mirrors: <a data-content="BASE64">provider</a> (quality from decoded token)
    if (preg_match_all('#data-content="([^"]+)"[^>]*>([^<]+)</a>#i', $h, $mc)) {
        for ($i = 0; $i < count($mc[1]); $i++) {
            $tok = $mc[1][$i];
            $info = json_decode(base64_decode($tok), true);
            $q = $info['q'] ?? '';
            $servers[] = ['label' => trim(($q ? $q . ' · ' : '') . $mc[2][$i]), 'token' => $tok];
        }
    }
    // Downloads: <li><strong>RES</strong> <a href>provider</a> ...
    if (preg_match('#<div class="download">(.*?)</div>#is', $h, $dd) && preg_match_all('#<strong>([^<]+)</strong>(.*?)(?=<li|</div>)#is', $dd[1], $lis)) {
        for ($i = 0; $i < count($lis[1]); $i++) {
            $res = trim($lis[1][$i]);
            if (preg_match_all('#<a[^>]+href="(https?://[^"]+)"[^>]*>([^<]+)</a>#i', $lis[2][$i], $al)) {
                for ($j = 0; $j < count($al[1]); $j++) {
                    $downloads[] = ['label' => $res . ' · ' . trim($al[2][$j]), 'url' => html_entity_decode($al[1][$j])];
                }
            }
        }
    }
    echo json_encode(['rurl' => $resolverUrl, 'rkey' => $resolverKey, 'servers' => $servers, 'downloads' => $downloads]);
    exit;
}

// === otakudesu.live (Astro): data-token + quality-pill groups ===
// Each download-group: <summary> quality-pill + provider <a data-token><span link-name>
if (preg_match_all('#<details[^>]*class="[^"]*download-group[^"]*"[^>]*>(.*?)</details>#is', $h, $groups)) {
    foreach ($groups[1] as $g) {
        preg_match('#class="quality-pill"[^>]*>([^<]+)<#i', $g, $qp);
        $quality = trim($qp[1] ?? '');
        $isStream = stripos($quality, 'mirror') !== false;
        if ($isStream) {
            preg_match_all('#data-token="([^"]+)"[^>]*>.*?class="link-name"[^>]*>([^<]+)<#is', $g, $links);
            for ($i = 0; $i < count($links[1]); $i++) {
                $servers[] = ['label' => $quality . ' · ' . trim($links[2][$i]), 'token' => $links[1][$i]];
            }
        } else {
            // Download = direct href links
            preg_match_all('#<a[^>]+href="(https?://[^"]+)"[^>]*class="link-card"[^>]*>.*?class="link-name"[^>]*>([^<]+)<#is', $g, $links);
            for ($i = 0; $i < count($links[1]); $i++) {
                $downloads[] = ['label' => $quality . ' · ' . trim($links[2][$i]), 'url' => html_entity_decode($links[1][$i])];
            }
        }
    }
}
echo json_encode(['rurl' => $resolverUrl, 'rkey' => $resolverKey, 'servers' => $servers, 'downloads' => $downloads]);
