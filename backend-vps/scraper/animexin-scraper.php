<?php
require_once __DIR__ . '/../config/database.php';

class AnimexinScraper {
    private $pdo;
    private $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';

    public function __construct() { $this->pdo = getDB(); }

    private function fetch($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $this->ua,
            CURLOPT_HTTPHEADER => ['Accept-Language: id-ID,id;q=0.9,en;q=0.8'],
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200 ? $html : null;
    }

    private function decodeEmbed($base64) {
        $html = base64_decode($base64);
        if (!$html) return '';
        preg_match('/src=["\']([^"\']+)["\']/', $html, $m);
        return $m[1] ?? '';
    }

    public function getAllSeriesViaPlaywright() {
        // Jalankan Python script untuk ambil semua series URL
        $script = <<<'PYTHON'
from playwright.sync_api import sync_playwright
import re, json

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto('https://animexin.dev/anime/list-mode/', timeout=30000)
    page.wait_for_timeout(4000)
    page.evaluate('window.scrollTo(0, document.body.scrollHeight)')
    page.wait_for_timeout(2000)
    links = page.query_selector_all('.listupd a, .animelist a, .soralist a, li a')
    series = set()
    skip = ['az-lists','page','feed','bookmark','dmca','privacy','genre','release-date','help','download','kofi']
    for l in links:
        href = l.get_attribute('href') or ''
        title = l.text_content().strip()
        if 'animexin.dev' in href and title and len(title) > 2:
            if not any(s in href for s in skip):
                series.add(href)
    browser.close()
    print(json.dumps(list(series)))
PYTHON;
        $tmpFile = '/tmp/pw_list.py';
        file_put_contents($tmpFile, $script);
        $output = shell_exec("python3 {$tmpFile} 2>/dev/null");
        $series = json_decode(trim($output), true);
        return $series ?: [];
    }

    public function scrapeSeries($url) {
        $html = $this->fetch($url);
        if (!$html) return null;

        // Slug dari URL
        $slug = rtrim(parse_url($url, PHP_URL_PATH), '/');
        $slug = ltrim(str_replace('/anime/', '', $slug), '/');
        if (empty($slug)) $slug = basename(rtrim($url, '/'));

        preg_match('|<h1[^>]*>(.*?)</h1>|si', $html, $m);
        $title = trim(strip_tags($m[1] ?? ''));
        if (!$title) return null;

        preg_match('|<img[^>]*class="[^"]*wp-post-image[^"]*"[^>]*src="([^"]+)"|', $html, $m);
        if (empty($m[1])) preg_match('|class="[^"]*thumb[^"]*".*?<img[^>]*src="([^"]+)"|si', $html, $m);
        if (empty($m[1])) preg_match('|<img[^>]*src="([^"]*animexin[^"]+)"|i', $html, $m);
        $poster = $m[1] ?? '';

        preg_match('|class="entry-content[^"]*"[^>]*>(.*?)</div>|si', $html, $m);
        $desc = trim(preg_replace('/\s*(Tonton|Nonton|Download|Subtitle|AnimeXin|Watch)[^.]*\./i', '', strip_tags($m[1] ?? '')));
        $desc = trim(preg_replace('/\s+/', ' ', $desc));

        preg_match_all('|href="https://animexin\.dev/genres/[^"]*"[^>]*>(.*?)</a>|si', $html, $m);
        $genres = array_unique(array_filter(array_map(function($g) { return trim(strip_tags($g)); }, $m[1] ?? []), function($g) { return $g && strlen($g) < 30; }));

        preg_match('|(20\d\d)|', $html, $m);
        $year = intval($m[1] ?? 0);

        $status = stripos($html, 'Completed') !== false ? 'completed' : 'ongoing';

        preg_match('|itemprop="ratingValue"[^>]*>([\d.]+)|', $html, $m);
        $rating = floatval($m[1] ?? 0);

        preg_match('|href="[^"]*/studio/[^"]*"[^>]*>(.*?)</a>|si', $html, $m);
        $studio = trim(strip_tags($m[1] ?? ''));

        // Episode URLs - indonesia sub
        preg_match_all('|href="(https://animexin\.dev/[^"]*-indonesia-[^"]+)"|', $html, $m);
        $eps = array_unique($m[1] ?? []);

        // Fallback: semua episode
        if (empty($eps)) {
            preg_match_all('|href="(https://animexin\.dev/[^"]*episode[^"]+)"|', $html, $m);
            $eps = array_unique($m[1] ?? []);
        }

        return compact('title','slug','poster','desc','genres','year','status','rating','studio','eps');
    }

    public function scrapeEpisode($epUrl) {
        $html = $this->fetch($epUrl);
        if (!$html) return null;

        preg_match('|episode-(\d+(?:\.\d+)?)|', $epUrl, $m);
        $epNum = floatval($m[1] ?? 0);
        if (!$epNum) return null;

        $streams = [];
        $downloads = [];

        preg_match_all('|<option[^>]*value="([A-Za-z0-9+/]{20,}=*)"[^>]*>(.*?)</option>|si', $html, $m);
        foreach ($m[1] as $i => $b64) {
            $label = trim(strip_tags($m[2][$i]));
            if (!$label || $label === 'Select Video Server') continue;
            $embedUrl = $this->decodeEmbed($b64);
            if ($embedUrl) $streams[$label] = $embedUrl;
        }

        preg_match_all('|<div class="soraddlx[^"]*">.*?<h3>(.*?)</h3>.*?<div class="soraurlx">(.*?)</div>|si', $html, $sections);
        foreach ($sections[1] as $i => $langLabel) {
            $langLabel = trim(strip_tags($langLabel));
            if (stripos($langLabel, 'VIP') !== false) continue;
            $lang = stripos($langLabel, 'Indonesia') !== false ? 'id' : 'en';
            preg_match_all('|<a href="([^"]+)"[^>]*>([^<]+)</a>|', $sections[2][$i], $links);
            foreach ($links[1] as $j => $dlUrl) {
                $dlName = trim($links[2][$j]);
                $downloads[] = ['name' => "{$langLabel} {$dlName}", 'url' => $dlUrl, 'lang' => $lang];
            }
        }

        $preferred = ['Hardsub Indonesia Dailymotion','Hardsub Indonesia Odysee','Hardsub Indonesia Rumble','Hardsub Indonesia Ok.ru','Hardsub Indonesia Mega'];
        $mainVideo = '';
        foreach ($preferred as $p) {
            if (isset($streams[$p])) { $mainVideo = $streams[$p]; break; }
        }
        if (!$mainVideo) {
            foreach ($streams as $label => $url) {
                if (stripos($label, 'indonesia') !== false) { $mainVideo = $url; break; }
            }
        }

        return compact('epNum','mainVideo','streams','downloads','epUrl');
    }

    public function saveSeries($data) {
        $genres = json_encode(array_values($data['genres']) ?: []);
        $stmt = $this->pdo->prepare("SELECT id FROM content WHERE slug=?");
        $stmt->execute([$data['slug']]);
        $row = $stmt->fetch();
        if ($row) {
            $this->pdo->prepare("UPDATE content SET title=?,poster_url=?,description=?,genres=?,year=?,status=?,rating=?,studio=? WHERE id=?")
                ->execute([$data['title'],$data['poster'],$data['desc'],$genres,$data['year'],$data['status'],$data['rating'],$data['studio'],$row['id']]);
            return $row['id'];
        }
        $this->pdo->prepare("INSERT INTO content (slug,title,type,poster_url,description,genres,year,status,rating,studio,created_at,updated_at) VALUES (?,?,'donghua',?,?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$data['slug'],$data['title'],$data['poster'],$data['desc'],$genres,$data['year'],$data['status'],$data['rating'],$data['studio']]);
        return $this->pdo->lastInsertId();
    }

    public function saveEpisode($contentId, $epData) {
        $stmt = $this->pdo->prepare("SELECT id FROM episodes WHERE content_id=? AND episode_number=?");
        $stmt->execute([$contentId, $epData['epNum']]);
        $row = $stmt->fetch();
        if ($row) {
            $epId = $row['id'];
            $this->pdo->prepare("UPDATE episodes SET video_url=?,source_url=? WHERE id=?")->execute([$epData['mainVideo'],$epData['epUrl'],$epId]);
        } else {
            $this->pdo->prepare("INSERT INTO episodes (content_id,episode_number,title,video_url,source_url,created_at) VALUES (?,?,?,?,?,NOW())")
                ->execute([$contentId,$epData['epNum'],"Episode {$epData['epNum']}",$epData['mainVideo'],$epData['epUrl']]);
            $epId = $this->pdo->lastInsertId();
        }
        $this->pdo->prepare("DELETE FROM episode_servers WHERE episode_id=?")->execute([$epId]);
        foreach ($epData['streams'] as $name => $url) {
            $lang = stripos($name, 'indonesia') !== false ? 'id' : 'en';
            $this->pdo->prepare("INSERT INTO episode_servers (episode_id,server_name,server_url,type,language) VALUES (?,?,?,'stream',?)")->execute([$epId,$name,$url,$lang]);
        }
        foreach ($epData['downloads'] as $dl) {
            $this->pdo->prepare("INSERT INTO episode_servers (episode_id,server_name,server_url,type,language) VALUES (?,?,?,'download',?)")->execute([$epId,$dl['name'],$dl['url'],$dl['lang']]);
        }
    }

    public function run() {
        echo "Animexin Scraper (322 series via Playwright)\n";
        echo date('Y-m-d H:i:s') . "\n\n";
        echo "Step 1: Ambil semua series via Playwright...\n";
        $allSeries = $this->getAllSeriesViaPlaywright();
        $total = count($allSeries);
        echo "Total: {$total} series\n\n";
        echo "Step 2: Scrape detail + episodes...\n";
        foreach ($allSeries as $i => $url) {
            $num = $i + 1;
            echo "\n[{$num}/{$total}] {$url}\n";
            $series = $this->scrapeSeries($url);
            if (!$series || empty($series['title'])) { echo "  Skip!\n"; continue; }
            echo "  Title : {$series['title']}\n";
            echo "  Eps   : " . count($series['eps']) . "\n";
            $contentId = $this->saveSeries($series);
            foreach ($series['eps'] as $epUrl) {
                $epData = $this->scrapeEpisode($epUrl);
                if (!$epData) continue;
                $this->saveEpisode($contentId, $epData);
                echo "  EP {$epData['epNum']}: " . count($epData['streams']) . " stream, " . count($epData['downloads']) . " dl\n";
                sleep(1);
            }
            sleep(1);
        }
        echo "\nSELESAI!\n";
    }
}

$scraper = new AnimexinScraper();
$scraper->run();
