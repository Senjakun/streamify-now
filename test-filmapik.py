import cloudscraper
from bs4 import BeautifulSoup

scraper = cloudscraper.create_scraper(
    browser={'browser': 'chrome', 'platform': 'windows'},
    delay=5
)

for url in ["https://filmapik.info", "https://filmapik.fitness"]:
    try:
        r = scraper.get(url, timeout=30)
        print(f"{url} → {r.status_code} | {len(r.text)} chars")
        soup = BeautifulSoup(r.text, 'html.parser')
        items = soup.find_all('article') or soup.find_all('div', class_='item')
        print(f"  Film items: {len(items)}")
        if items:
            a = items[0].find('a')
            print(f"  Sample: {a.get('title') or a.text.strip() if a else '-'}")
    except Exception as e:
        print(f"{url} → ERROR: {e}")
