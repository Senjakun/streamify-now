#!/usr/bin/env python3
import json
import os
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlparse, parse_qs

try:
    import cloudscraper  # type: ignore
except Exception:
    cloudscraper = None

try:
    import requests  # type: ignore
except Exception:
    requests = None

HOST = os.environ.get('NOVEL_PROXY_HOST', '127.0.0.1')
PORT = int(os.environ.get('NOVEL_PROXY_PORT', '8787'))
ALLOWED_HOSTS = tuple(h.strip().lower() for h in os.environ.get('NOVEL_PROXY_ALLOWED_HOSTS', 'www.sonicmtl.com,sonicmtl.com').split(',') if h.strip())
TIMEOUT = int(os.environ.get('NOVEL_PROXY_TIMEOUT', '30'))
USER_AGENT = os.environ.get(
    'NOVEL_PROXY_UA',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36',
)

def build_client():
    if cloudscraper is not None:
        return cloudscraper.create_scraper(browser={'browser': 'chrome', 'platform': 'windows', 'mobile': False})
    if requests is not None:
        return requests.Session()
    raise RuntimeError('cloudscraper or requests is required')

CLIENT = build_client()

class Handler(BaseHTTPRequestHandler):
    server_version = 'NovelFetchProxy/1.0'

    def _send_json(self, status: int, payload: dict):
        data = json.dumps(payload).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def _bad(self, status: int, msg: str):
        self._send_json(status, {'success': False, 'error': msg})

    def log_message(self, fmt, *args):
        sys.stdout.write('%s - - [%s] %s\n' % (self.client_address[0], self.log_date_time_string(), fmt % args))

    def do_GET(self):
        parsed = urlparse(self.path)

        if parsed.path == '/health':
            self._send_json(200, {'success': True, 'status': 'ok'})
            return

        if parsed.path != '/fetch':
            self._bad(404, 'not found')
            return

        qs = parse_qs(parsed.query)
        target = (qs.get('url') or [''])[0].strip()
        if not target:
            self._bad(400, 'url is required')
            return

        try:
            target_parsed = urlparse(target)
        except Exception:
            self._bad(400, 'invalid url')
            return

        if target_parsed.scheme not in ('http', 'https'):
            self._bad(400, 'invalid scheme')
            return

        hostname = (target_parsed.hostname or '').lower()
        if not hostname or not any(hostname == allowed or hostname.endswith('.' + allowed) for allowed in ALLOWED_HOSTS):
            self._bad(403, 'host not allowed')
            return

        headers = {
            'User-Agent': USER_AGENT,
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.9,id;q=0.8',
            'Referer': 'https://www.sonicmtl.com/',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache',
        }

        try:
            resp = CLIENT.get(target, headers=headers, timeout=TIMEOUT, allow_redirects=True)
            status = getattr(resp, 'status_code', 0)
            text = getattr(resp, 'text', '')
            if status < 200 or status >= 400 or not text:
                self._bad(502, f'upstream status {status}')
                return

            self._send_json(200, {
                'success': True,
                'status_code': status,
                'final_url': getattr(resp, 'url', target),
                'html': text,
            })
        except Exception as exc:
            self._bad(502, f'fetch failed: {exc}')

if __name__ == '__main__':
    server = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f'Novel fetch proxy listening on http://{HOST}:{PORT}', flush=True)
    server.serve_forever()
