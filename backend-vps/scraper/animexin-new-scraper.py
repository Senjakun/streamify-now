#!/usr/bin/env python3
import sys, json, re, base64, time
sys.path.insert(0, '/usr/local/lib/python3.10/dist-packages')

from playwright.sync_api import sync_playwright
import pymysql

# DB Config
DB = dict(host='localhost', user='streamify_user', password='rimbamobile2', db='streamify_db', charset='utf8mb4')

def get_db():
    return pymysql.connect(**DB)

def decode_servers(html):
    servers = []
    encoded = re.findall(r'["\']([A-Za-z0-9+/=]{50,})["\']', html)
    for e in encoded:
        try:
            decoded = base64.b64decode(e + '==').decode('utf-8')
            if 'iframe' in decoded or 'src=' in decoded:
                src = re.search(r'src=["\']([^"\']+)["\']', decoded)
                if src:
                    servers.append(src.group(1))
        except:
            pass
    return list(set(servers))

def scrape_series_list(page):
    print("Mengambil daftar donghua...")
    page.goto('https://animexin.dev/anime/list-mode/', timeout=30000)
    page.wait_for_timeout(5000)
    content = page.content()
    links = re.findall(r'href="(https://animexin\.dev/anime/[^"]+)"', content)
    skip = ['list-mode', 'az-lists', 'page', 'genre']
    series = [l for l in set(links) if not any(s in l for s in skip)]
    print(f"Total series: {len(series)}")
    return series

def scrape_detail(page, url):
    page.goto(url, timeout=30000)
    page.wait_for_timeout(4000)
    content = page.content()
    
    data = {}
    
    # Title
    t = re.search(r'<title>([^<]+)</title>', content)
    data['title'] = re.sub(r'\s*[-–|].*$', '', t.group(1)).strip() if t else ''
    data['title'] = re.sub(r'\s*Sub Indo.*$', '', data['title'], flags=re.I).strip()
    
    # Poster
    p = re.search(r'<img[^>]+class="[^"]*wp-post-image[^"]*"[^>]+src="([^"]+)"', content)
    if not p:
        p = re.search(r'src="(https://animexin\.dev/wp-content/uploads/[^"]+)"', content)
    data['poster'] = p.group(1) if p else ''
    
    # Synopsis
    s = re.search(r'<div[^>]*class="[^"]*synopsisField[^"]*"[^>]*>(.*?)</div>', content, re.DOTALL)
    if not s:
        s = re.search(r'<div[^>]*class="[^"]*entry-content[^"]*"[^>]*>(.*?)</div>', content, re.DOTALL)
    data['synopsis'] = re.sub(r'<[^>]+>', '', s.group(1)).strip()[:1000] if s else ''
    
    # Status
    data['status'] = 'ongoing' if re.search(r'ongoing|berlangsung', content, re.I) else 'completed'
    
    # Rating
    r = re.search(r'(\d+\.?\d*)\s*/\s*10', content)
    data['rating'] = min(float(r.group(1)), 9.9) if r else 0.0
    
    # Episodes
    eps = re.findall(r'href="(https://animexin\.dev/[^"]*episode[^"]*)"', content)
    eps = list(dict.fromkeys(eps))  # dedupe preserve order
    data['episodes'] = eps
    
    # Slug
    data['slug'] = url.rstrip('/').split('/')[-1]
    
    return data

def scrape_episode(page, url):
    page.goto(url, timeout=30000)
    page.wait_for_timeout(4000)
    content = page.content()
    
    servers = decode_servers(content)
    
    # Episode number
    n = re.search(r'episode[- ](\d+)', url, re.I)
    ep_num = int(n.group(1)) if n else 0
    
    return {'servers': servers, 'ep_num': ep_num, 'url': url}

def main():
    db = get_db()
    cursor = db.cursor()
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        
        series_list = scrape_series_list(page)
        
        for idx, series_url in enumerate(series_list):
            slug = series_url.rstrip('/').split('/')[-1]
            print(f"\n[{idx+1}/{len(series_list)}] {slug}")
            
            # Cek sudah ada
            cursor.execute("SELECT id FROM content WHERE slug=%s AND type='donghua'", (slug,))
            if cursor.fetchone():
                print("  SKIP")
                continue
            
            try:
                detail = scrape_detail(page, series_url)
                if not detail['title']:
                    print("  GAGAL: no title")
                    continue
                
                # Insert content
                cursor.execute("""
                    INSERT INTO content (slug, title, type, poster_url, description, rating, status)
                    VALUES (%s, %s, 'donghua', %s, %s, %s, %s)
                """, (slug, detail['title'], detail['poster'], detail['synopsis'], detail['rating'], detail['status']))
                content_id = cursor.lastrowid
                db.commit()
                
                print(f"  Title: {detail['title']}")
                print(f"  Episodes: {len(detail['episodes'])}")
                
                # Scrape episodes
                for ep_url in detail['episodes']:
                    ep_data = scrape_episode(page, ep_url)
                    ep_slug = ep_url.rstrip('/').split('/')[-1]
                    ep_title = f"{detail['title']} Episode {ep_data['ep_num']}"
                    embed = ep_data['servers'][0] if ep_data['servers'] else ep_url
                    
                    cursor.execute("""
                        INSERT IGNORE INTO episodes (content_id, episode_number, title, source_url)
                        VALUES (%s, %s, %s, %s)
                    """, (content_id, ep_data['ep_num'], ep_title, embed))
                    db.commit()
                    
                    print(f"    Ep {ep_data['ep_num']}: {len(ep_data['servers'])} servers")
                    time.sleep(0.5)
                
            except Exception as e:
                print(f"  ERROR: {e}")
                continue
            
            time.sleep(1)
        
        browser.close()
    
    cursor.close()
    db.close()
    print("\n=== SELESAI ===")

if __name__ == '__main__':
    main()
