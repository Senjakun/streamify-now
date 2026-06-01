from playwright.sync_api import sync_playwright
import pymysql, time

DB = dict(host='localhost', user='streamify_user', password='rimbamobile2', db='streamify_db', charset='utf8mb4')

db = pymysql.connect(**DB)
cursor = db.cursor()

cursor.execute("SELECT id, source_url FROM episodes WHERE (video_url IS NULL OR video_url='') AND source_url LIKE 'http://143.198.93.61%' LIMIT 50")
episodes = cursor.fetchall()
print(f"Total episode perlu fix: {len(episodes)}")

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    
    for i, (ep_id, source_url) in enumerate(episodes):
        try:
            page.goto(source_url, timeout=20000)
            page.wait_for_timeout(4000)
            
            iframes = page.query_selector_all('iframe')
            embed = None
            for f in iframes:
                src = f.get_attribute('src')
                if src and 'discord' not in src:
                    embed = src
                    break
            
            if embed:
                cursor.execute("UPDATE episodes SET video_url=%s WHERE id=%s", (embed, ep_id))
                db.commit()
                print(f"[{i+1}] OK: {embed[:60]}")
            else:
                print(f"[{i+1}] NO EMBED: {source_url}")
        except Exception as e:
            print(f"[{i+1}] ERROR: {e}")
        time.sleep(1)
    
    browser.close()

cursor.close()
db.close()
print("SELESAI!")
