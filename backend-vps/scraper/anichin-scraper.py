#!/usr/bin/env python3
"""
Anichin.cafe Scraper for playall.me
Scrapes donghua series + episodes + embed URLs
"""

import cloudscraper
from bs4 import BeautifulSoup
import mysql.connector
import time
import re
import sys
from datetime import datetime

# ── DB Config ─────────────────────────────────────────────────
DB = dict(host='localhost', user='streamify_user',
          password='rimbamobile2', database='streamify_db')  # isi password

BASE = 'https://anichin.cafe'

scraper = cloudscraper.create_scraper(
    browser={'browser': 'chrome', 'platform': 'windows'},
    delay=3
)
scraper.headers.update({'Accept-Language': 'id-ID,id;q=0.9,en;q=0.8'})

def db_connect():
    return mysql.connector.connect(**DB)

def slugify(text):
    text = text.lower().strip()
    text = re.sub(r'[^\w\s-]', '', text)
    return re.sub(r'[\s_-]+', '-', text)

def get_all_series():
    """Ambil semua slug seri dari /ongoing/ dan /completed/"""
    slugs = set()
    for status in ['ongoing', 'completed']:
        page = 1
        while True:
            url = f"{BASE}/{status}/page/{page}/" if page > 1 else f"{BASE}/{status}/"
            try:
                r = scraper.get(url, timeout=30)
                if r.status_code == 404:
                    break
                soup = BeautifulSoup(r.text, 'html.parser')
                items = soup.find_all('article')
                if not items:
                    break
                for it in items:
                    a = it.find('a', href=True)
                    if a and '/seri/' in a['href']:
                        slugs.add((a['href'], status))
                nxt = soup.select_one('a.next, a[rel=next], .next a')
                if not nxt:
                    break
                page += 1
                print(f"  [{status}] page {page-1} → {len(items)} series")
                time.sleep(1)
            except Exception as e:
                print(f"  Error page {page}: {e}")
                break
    return list(slugs)

def scrape_series(url, status):
    """Scrape detail seri + semua episode"""
    try:
        r = scraper.get(url, timeout=30)
        soup = BeautifulSoup(r.text, 'html.parser')

        # Title
        title = soup.find('h1')
        title = title.text.strip() if title else ''

        # Slug
        slug = url.rstrip('/').split('/seri/')[-1]

        # Poster
        img = soup.find('img', class_=lambda c: c and 'ts-post-image' in (c or ''))
        if not img:
            img = soup.select_one('.thumbook img, .thumb img')
        poster = ''
        if img:
            poster = img.get('data-src') or img.get('src', '')

        # Description
        desc_el = soup.select_one('.entry-content, .synp, .desc, [itemprop=description]')
        desc = desc_el.text.strip() if desc_el else ''

        # Genre
        genres = [a.text.strip() for a in soup.select('.genxed a, .sgenre a, [rel=tag]')]

        # Rating
        rating_el = soup.select_one('.num, .rating, [itemprop=ratingValue]')
        rating = 0.0
        if rating_el:
            try:
                rating = float(re.search(r'[\d.]+', rating_el.text).group())
            except:
                pass

        # Year
        year_el = soup.select_one('.info-content .spe span')
        year = None
        for span in soup.select('.spe span, .info-content span'):
            if re.search(r'20\d\d', span.text):
                m = re.search(r'(20\d\d)', span.text)
                if m:
                    year = int(m.group(1))
                    break

        # Episodes
        eps_links = list(dict.fromkeys([
            a['href'] for a in soup.find_all('a', href=True)
            if 'episode' in a['href'] and 'anichin.cafe' in a['href']
        ]))

        return {
            'title': title, 'slug': slug, 'poster': poster,
            'description': desc, 'genres': genres, 'rating': rating,
            'year': year, 'status': status, 'episode_urls': eps_links
        }
    except Exception as e:
        print(f"  Error scrape series {url}: {e}")
        return None

def scrape_episode_embed(ep_url):
    """Ambil embed URL dari halaman episode"""
    try:
        r = scraper.get(ep_url, timeout=30)
        soup = BeautifulSoup(r.text, 'html.parser')
        iframe = soup.find('iframe', src=True) or soup.find('iframe', attrs={'data-src': True})
        if iframe:
            return iframe.get('src') or iframe.get('data-src', '')

        # Fallback: cari di script
        for script in soup.find_all('script'):
            m = re.search(r'["\']([^"\']*anichin\.stream[^"\']*)["\']', script.text or '')
            if m:
                return m.group(1)
        return ''
    except Exception as e:
        print(f"  Error embed {ep_url}: {e}")
        return ''

def save_series(conn, series):
    """Insert/update series ke database"""
    cur = conn.cursor()
    # Cek existing
    cur.execute("SELECT id FROM content WHERE slug=%s", (series['slug'],))
    row = cur.fetchone()

    genres_str = ','.join(series['genres'])

    if row:
        content_id = row[0]
        cur.execute("""UPDATE content SET title=%s, poster_url=%s, description=%s,
            genres=%s, rating=%s, year=%s, status=%s, updated_at=NOW()
            WHERE id=%s""",
            (series['title'], series['poster'], series['description'],
             genres_str, series['rating'], series['year'],
             series['status'], content_id))
    else:
        cur.execute("""INSERT INTO content
            (title, slug, type, poster_url, description, genres, rating, year, status, created_at, updated_at)
            VALUES (%s,%s,'donghua',%s,%s,%s,%s,%s,%s,NOW(),NOW())""",
            (series['title'], series['slug'], series['poster'],
             series['description'], genres_str, series['rating'],
             series['year'], series['status']))
        content_id = cur.lastrowid

    conn.commit()
    cur.close()
    return content_id

def save_episode(conn, content_id, ep_num, embed_url, ep_url):
    """Insert/update episode"""
    cur = conn.cursor()
    cur.execute("SELECT id FROM episodes WHERE content_id=%s AND episode_number=%s",
                (content_id, ep_num))
    row = cur.fetchone()
    if row:
        cur.execute("UPDATE episodes SET embed_url=%s, source_url=%s, updated_at=NOW() WHERE id=%s",
                    (embed_url, ep_url, row[0]))
    else:
        cur.execute("""INSERT INTO episodes
            (content_id, episode_number, title, embed_url, source_url, created_at, updated_at)
            VALUES (%s,%s,%s,%s,%s,NOW(),NOW())""",
            (content_id, ep_num, f"Episode {ep_num}", embed_url, ep_url))
    conn.commit()
    cur.close()

def extract_ep_number(url):
    """Extract nomor episode dari URL"""
    m = re.search(r'episode-(\d+(?:\.\d+)?)', url)
    return float(m.group(1)) if m else 0

# ── MAIN ──────────────────────────────────────────────────────
print("🚀 Anichin Scraper mulai...")
print(f"   {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

conn = db_connect()

# Step 1: Ambil semua series
print("\n📋 Step 1: Ambil list semua series...")
all_series = get_all_series()
print(f"   Total: {len(all_series)} series")

# Step 2: Scrape tiap series
print("\n🎬 Step 2: Scrape detail + episodes...")
for i, (url, status) in enumerate(all_series, 1):
    print(f"\n[{i}/{len(all_series)}] {url}")
    series = scrape_series(url, status)
    if not series:
        continue

    print(f"  Title   : {series['title']}")
    print(f"  Episodes: {len(series['episode_urls'])}")

    content_id = save_series(conn, series)

    # Scrape embed tiap episode
    for ep_url in series['episode_urls']:
        ep_num = extract_ep_number(ep_url)
        if ep_num == 0:
            continue
        embed = scrape_episode_embed(ep_url)
        save_episode(conn, content_id, ep_num, embed, ep_url)
        print(f"  EP {ep_num}: {embed[:60] if embed else 'no embed'}")
        time.sleep(0.5)

    time.sleep(1)

conn.close()
print("\n✅ SELESAI!")
