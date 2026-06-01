# Backend VPS - Nonton Streaming

Backend PHP + MySQL untuk website streaming anime, manga, dan movies.

## 🎯 Fitur Utama

- **3 Kategori Utama**: Anime, Manga, Movies (tanpa K-Drama & Short Drama)
- **Web Scraping Otomatis**: Ambil data dari sumber eksternal
- **Auth JWT**: Sistem login/register dengan token
- **Komentar**: Fitur komentar dengan reply dan like
- **Bookmark**: Simpan konten favorit user
- **Admin Panel API**: Kelola konten dan user

## 📊 Sumber Data (Scraping)

| Kategori | Sumber | URL | Scraper File |
|----------|--------|-----|--------------|
| Anime | Otakudesu | https://otakudesu.best | `anime-scraper.php` |
| Manga | Kiryuu | https://kiryuu03.com | `manga-scraper.php` |
| Movies | Filmapik | https://filmapik.fitness | `movie-scraper.php` |

> **Note**: Domain Filmapik sering berubah. Scraper movie akan otomatis detect redirect.

## 📋 Requirements

- PHP 7.4+ (dengan extensions: pdo_mysql, curl, mbstring, json)
- MySQL 5.7+ atau MariaDB 10.3+
- Apache/Nginx dengan mod_rewrite

## 🔧 Instalasi di VPS

### 1. Upload Files

```bash
# Upload folder backend-vps ke VPS
scp -r backend-vps/ user@your-vps:/var/www/playall-api/
```

### 2. Setup Database

```bash
# Login ke MySQL
mysql -u root -p

# Buat database
CREATE DATABASE playall_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Import schema
source /var/www/playall-api/database/schema.sql
```

### 3. Konfigurasi

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'playall_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('SITE_URL', 'https://api.yourdomain.com');
define('JWT_SECRET', 'generate-a-secure-random-string-here');
```

### 4. Setup Nginx

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/playall-api;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Setup Apache (.htaccess)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# CORS Headers
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type, Authorization"
```

## 🕷️ Web Scraper Usage

### Anime Scraper (Otakudesu)

```bash
cd scraper/

# Scrape anime ongoing
php anime-scraper.php ongoing 1

# Scrape anime completed
php anime-scraper.php completed 1

# Scrape detail anime
php anime-scraper.php detail "https://otakudesu.best/anime/jujutsu-kaisen/"

# Search anime
php anime-scraper.php search "naruto"
```

### Manga Scraper (Kiryuu)

```bash
# Scrape manga terbaru
php manga-scraper.php latest 1

# Scrape manga populer
php manga-scraper.php popular 1

# Scrape detail manga
php manga-scraper.php detail "https://kiryuu03.com/manga/one-piece/"

# Scrape chapter images
php manga-scraper.php chapter "https://kiryuu03.com/one-piece-chapter-100/"

# Search manga
php manga-scraper.php search "one punch man"
```

### Movie Scraper (Filmapik)

```bash
# Scrape movie terbaru
php movie-scraper.php latest

# Scrape TV shows
php movie-scraper.php tvshows 1

# Scrape by category
php movie-scraper.php category action 1

# Scrape detail movie
php movie-scraper.php detail "https://filmapik.fitness/nonton-film/dune-2/"

# Search movie
php movie-scraper.php search "avatar"
```

## 📡 API Endpoints

### Proxy API (Unified Scraping)

```
GET /api/proxy.php?action=home                    # Homepage data (all categories)
GET /api/proxy.php?action=list&type=anime&page=1  # List content
GET /api/proxy.php?action=detail&type=anime&slug=xxx
GET /api/proxy.php?action=watch&type=anime&slug=xxx&episode=1
GET /api/proxy.php?action=search&type=anime&q=naruto
GET /api/proxy.php?action=trending&type=anime
GET /api/proxy.php?action=scrape&type=anime&mode=ongoing  # Trigger scrape (admin)
```

### Authentication

```
POST /api/auth.php?action=register
POST /api/auth.php?action=login
GET  /api/auth.php?action=me
```

### Content

```
GET  /api/content.php?action=list&type=anime&page=1
GET  /api/content.php?action=detail&slug=jujutsu-kaisen
GET  /api/content.php?action=search&q=naruto
GET  /api/content.php?action=trending&limit=10
```

### Comments

```
GET  /api/comments.php?action=list&content_id=1
POST /api/comments.php?action=create
POST /api/comments.php?action=delete&comment_id=1
POST /api/comments.php?action=like
```

### Bookmarks

```
GET  /api/bookmarks.php?action=list
POST /api/bookmarks.php?action=toggle
GET  /api/bookmarks.php?action=check&content_id=1
```

### Admin (requires admin role)

```
POST /api/admin.php?action=content_create
POST /api/admin.php?action=content_update
POST /api/admin.php?action=content_delete
GET  /api/admin.php?action=stats
```

## 🔐 Default Admin

```
Email: admin@playall.me
Password: admin123
```

⚠️ **PENTING**: Ganti password admin setelah deploy!

## 📁 Struktur Folder

```
backend-vps/
├── config/
│   ├── database.php      # Konfigurasi database & JWT
│   └── sources.php       # Konfigurasi sumber scraping
├── database/
│   └── schema.sql        # SQL schema untuk setup awal
├── api/
│   ├── auth.php          # Authentication endpoints
│   ├── content.php       # Content CRUD
│   ├── comments.php      # Comments system
│   ├── bookmarks.php     # Bookmarks
│   ├── admin.php         # Admin panel API
│   └── proxy.php         # Unified proxy for scraping
├── scraper/
│   ├── anime-scraper.php # Scraper Otakudesu
│   ├── manga-scraper.php # Scraper Kiryuu
│   └── movie-scraper.php # Scraper Filmapik
└── README.md
```

## 🔗 Integrasi dengan Frontend

```typescript
// src/lib/api.ts
const API_BASE = 'https://api.yourdomain.com/api';

export const api = {
  // Home page data
  home: () =>
    fetch(`${API_BASE}/proxy.php?action=home`).then(r => r.json()),
  
  // Content list
  getAnime: (page = 1) =>
    fetch(`${API_BASE}/proxy.php?action=list&type=anime&page=${page}`).then(r => r.json()),
  
  getManga: (page = 1) =>
    fetch(`${API_BASE}/proxy.php?action=list&type=manga&page=${page}`).then(r => r.json()),
  
  getMovies: (page = 1) =>
    fetch(`${API_BASE}/proxy.php?action=list&type=movie&page=${page}`).then(r => r.json()),
  
  // Detail
  getDetail: (type: string, slug: string) =>
    fetch(`${API_BASE}/proxy.php?action=detail&type=${type}&slug=${slug}`).then(r => r.json()),
  
  // Watch/Read
  getEpisode: (slug: string, episode: number) =>
    fetch(`${API_BASE}/proxy.php?action=watch&type=anime&slug=${slug}&episode=${episode}`).then(r => r.json()),
  
  getChapter: (slug: string, chapter: number) =>
    fetch(`${API_BASE}/proxy.php?action=watch&type=manga&slug=${slug}&chapter=${chapter}`).then(r => r.json()),
  
  // Search
  search: (type: string, query: string) =>
    fetch(`${API_BASE}/proxy.php?action=search&type=${type}&q=${encodeURIComponent(query)}`).then(r => r.json()),
};
```

## 🛡️ Security Notes

1. Selalu gunakan HTTPS di production
2. Ganti `JWT_SECRET` dengan string random yang panjang
3. Batasi akses database hanya dari localhost
4. Aktifkan rate limiting di nginx/apache
5. Backup database secara berkala

## 📝 Cron Job untuk Auto-Scrape

```bash
# Scrape anime setiap 6 jam
0 */6 * * * cd /var/www/playall-api/scraper && php anime-scraper.php ongoing 1 >> /var/log/scraper.log 2>&1

# Scrape manga setiap 4 jam
0 */4 * * * cd /var/www/playall-api/scraper && php manga-scraper.php latest 1 >> /var/log/scraper.log 2>&1

# Scrape movie setiap 12 jam
0 */12 * * * cd /var/www/playall-api/scraper && php movie-scraper.php latest >> /var/log/scraper.log 2>&1
```

## 📝 License

MIT
