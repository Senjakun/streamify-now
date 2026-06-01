<?php
/**
 * Anichin.cafe Scraper - Donghua
 */

require_once __DIR__ . '/../config/database.php';

class AnichinScraper {
    private $pdo;
    private $baseUrl = 'https://anichin.cafe';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function __construct() {
        $this->pdo = getDB();
    }

    private function fetch($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8',
            ],
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) return null;
        return $html;
    }

    private function slugify($text) {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        return preg_replace('/[\s-]+/', '-', $text);
    }

    public function getAllSeries() {
        $all = [];
        foreach (['ongoing', 'completed'] as $status) {
            $page = 1;
            while (true) {
                $url = $page === 1
                    ? "{$this->baseUrl}/{$status}/"
                    : "{$this->baseUrl}/{$status}/page/{$page}/";
                $html = $this->fetch($url);
                if (!$html) break;

                preg_match_all('|href="(https://anichin\.cafe/seri/[^"]+)"|', $html, $m);
                $urls = array_unique($m[1]);
                if (empty($urls)) break;

                foreach ($urls as $u) {
                    $all[$u] = $status;
                }

                echo "  [{$status}] page {$page} → " . count($urls) . " series\n";

                if (!preg_match('|href="[^"]+/page/' . ($page+1) . '/"|', $html)) break;
                $page++;
                sleep(1);
            }
        }
        return $all;
    }

    public function scrapeSeries($url, $status) {
        $html = $this->fetch($url);
        if (!$html) return null;

        $slug = preg_replace('|.*/seri/([^/]+)/?$|', '$1', $url);

        // Title
        preg_match('|<h1[^>]*>(.*?)</h1>|si', $html, $m);
        $title = trim(strip_tags($m[1] ?? ''));

        // Poster
        preg_match('|class="[^"]*ts-post-image[^"]*"[^>]*src="([^"]+)"|', $html, $m);
        if (empty($m[1])) preg_match('|class="[^"]*thumbook[^"]*".*?<img[^>]*src="([^"]+)"|si', $html, $m);
        $poster = $m[1] ?? '';

        // Description
        preg_match('|itemprop="description"[^>]*>(.*?)</div>|si', $html, $m);
        if (empty($m[1])) preg_match('|class="[^"]*entry-content[^"]*">(.*?)</div>|si', $html, $m);
        $desc = trim(strip_tags($m[1] ?? ''));

        // Genres
        preg_match_all('|class="[^"]*genre[^"]*"[^>]*>(.*?)</a>|si', $html, $m);
        $genres = array_map('trim', $m[1] ?? []);

        // Rating
        preg_match('|itemprop="ratingValue"[^>]*>([\d.]+)|', $html, $m);
        $rating = floatval($m[1] ?? 0);

        // Year
        preg_match('|(20\d\d)|', $html, $m);
        $year = intval($m[1] ?? 0);

        // Episodes
        preg_match_all('|href="(https://anichin\.cafe/[^"]*episode[^"]+)"|', $html, $m);
        $eps = array_unique($m[1] ?? []);

        return compact('title', 'slug', 'poster', 'desc', 'genres', 'rating', 'year', 'status', 'eps');
    }

    public function scrapeEmbed($epUrl) {
        $html = $this->fetch($epUrl);
        if (!$html) return '';

        // Cari iframe src
        preg_match('|<iframe[^>]*src="([^"]*anichin\.stream[^"]*)"[^>]*>|i', $html, $m);
        if (!empty($m[1])) return $m[1];

        // Fallback iframe apapun
        preg_match('|<iframe[^>]*src="([^"]+)"[^>]*>|i', $html, $m);
        return $m[1] ?? '';
    }

    public function saveSeries($data) {
        $genres = json_encode($data['genres'] ?: []);
        $slug = $data['slug'];
        $stmt = $this->pdo->prepare("SELECT id, type FROM content WHERE slug=?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        if ($row && $row['type'] !== 'donghua') {
            $slug = $slug . '-donghua';
            $stmt = $this->pdo->prepare("SELECT id FROM content WHERE slug=?");
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
        }

        if ($row) {
            $this->pdo->prepare("UPDATE content SET title=?,poster_url=?,description=?,genres=?,rating=?,year=?,status=? WHERE id=?")
                ->execute([$data['title'],$data['poster'],$data['desc'],$genres,$data['rating'],$data['year'],$data['status'],$row['id']]);
            return $row['id'];
        } else {
            $this->pdo->prepare("INSERT INTO content (slug,title,type,poster_url,description,genres,rating,year,status) VALUES (?,?,'donghua',?,?,?,?,?,?)")
                ->execute([$slug,$data['title'],$data['poster'],$data['desc'],$genres,$data['rating'],$data['year'],$data['status']]);
            return $this->pdo->lastInsertId();
        }
    }

    public function saveEpisode($contentId, $epNum, $embedUrl, $sourceUrl) {
        $epNum = (int)$epNum;
        $stmt = $this->pdo->prepare("SELECT id FROM episodes WHERE content_id=? AND episode_number=?");
        $stmt->execute([$contentId, $epNum]);
        $row = $stmt->fetch();

        if ($row) {
            $this->pdo->prepare("UPDATE episodes SET video_url=?,source_url=? WHERE id=?")
                ->execute([$embedUrl, $sourceUrl, $row['id']]);
        } else {
            $this->pdo->prepare("INSERT INTO episodes (content_id,episode_number,title,video_url,source_url,created_at) VALUES (?,?,?,?,?,NOW())")
                ->execute([$contentId, $epNum, "Episode {$epNum}", $embedUrl, $sourceUrl]);
        }
    }

    public function run() {
        echo "🚀 Anichin Scraper mulai...\n";
        echo "   " . date('Y-m-d H:i:s') . "\n\n";

        echo "📋 Step 1: Ambil semua series...\n";
        $allSeries = $this->getAllSeries();
        echo "   Total: " . count($allSeries) . " series\n\n";

        echo "🎬 Step 2: Scrape detail + episodes...\n";
        $i = 0;
        $total = count($allSeries);
        foreach ($allSeries as $url => $status) {
            $i++;
            echo "\n[{$i}/{$total}] {$url}\n";

            $series = $this->scrapeSeries($url, $status);
            if (!$series) { echo "  Skip!\n"; continue; }

            echo "  Title   : {$series['title']}\n";
            echo "  Episodes: " . count($series['eps']) . "\n";

            $contentId = $this->saveSeries($series);

            foreach ($series['eps'] as $epUrl) {
                preg_match('|episode-(\d+(?:\.\d+)?)|', $epUrl, $m);
                $epNum = floatval($m[1] ?? 0);
                if (!$epNum) continue;

                $embed = $this->scrapeEmbed($epUrl);
                $this->saveEpisode($contentId, $epNum, $embed, $epUrl);
                echo "  EP {$epNum}: " . substr($embed, 0, 60) . "\n";
                sleep(1);
            }
            sleep(1);
        }
        echo "\n✅ SELESAI!\n";
    }
}

$scraper = new AnichinScraper();
$scraper->run();
