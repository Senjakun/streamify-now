#!/usr/bin/env python3
import sys
import cloudscraper
from bs4 import BeautifulSoup

if len(sys.argv) < 2:
    print("Usage: python3 grab_embed.py <episode_url>")
    sys.exit(1)

url = sys.argv[1]
scraper = cloudscraper.create_scraper()
try:
    response = scraper.get(url)
    soup = BeautifulSoup(response.text, 'html.parser')

    # Cari semua iframe
    for iframe in soup.find_all('iframe'):
        src = iframe.get('src')
        if src and ('ok.ru' in src or 'dailymotion' in src or 'drive.google' in src):
            print(src)
            sys.exit(0)

    # Coba cari video player lain (misal dari script)
    print("Tidak ditemukan embed video.")
except Exception as e:
    print(f"Error: {e}")
