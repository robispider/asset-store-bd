#!/usr/bin/env python3
"""Download the BBS district geocode PDFs listed on the BBS geocode page.

Python 3.10+, standard library only. Files go to a temporary folder (outside the
repository) together with a manifest recording each source URL, size and SHA-256.
Existing files whose size matches the server are reused, so reruns are cheap.
"""

import argparse
import hashlib
import html
import json
import re
import ssl
import sys
import tempfile
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path

PAGE_URL = 'https://bbs.gov.bd/pages/static-pages/6922ded1933eb65569e1d95d'
DEFAULT_OUT = Path(tempfile.gettempdir()) / 'gov-store-bbs-geocode' / 'pdfs'
USER_AGENT = 'Mozilla/5.0 (gov-store geo-areas BBS geocode fetcher)'


def get(url, timeout=120, attempts=4):
    req = urllib.request.Request(url, headers={'User-Agent': USER_AGENT})
    for attempt in range(1, attempts + 1):
        try:
            with urllib.request.urlopen(req, timeout=timeout, context=ssl.create_default_context()) as r:
                return r.read()
        except Exception as exc:  # network errors are retried, then reported
            if attempt == attempts:
                raise RuntimeError(f'{url}: {exc}') from exc
            time.sleep(2 * attempt)


def list_district_pdfs(page_html):
    """Return [{division, district, url}] in page order.

    The page is a table grouped under '<NAME> DIVISION' headings. Only anchors
    whose label is an upper-case district name are kept, which skips the
    citizen-charter PDF linked from the site menu.
    """
    events = [(m.start(), 'division', m.group(1)) for m in
              re.finditer(r'>\s*([A-Z]+)\s+DIVISION\s*<', page_html)]
    for m in re.finditer(r'<a[^>]+href="([^"]+\.pdf)"[^>]*>(.*?)</a>', page_html, re.S):
        label = re.sub(r'<[^>]+>', '', html.unescape(m.group(2)))
        label = re.sub(r'\s+', ' ', label.replace('’', "'")).strip()
        if re.fullmatch(r"[A-Z][A-Z' .]+", label):
            events.append((m.start(), 'pdf', (label, html.unescape(m.group(1)))))
    division, out = '', []
    for _, kind, value in sorted(events, key=lambda e: e[0]):
        if kind == 'division':
            division = value
        else:
            out.append(dict(division=division, district=value[0], url=value[1]))
    return out


def slug(text):
    return re.sub(r'[^A-Z0-9]+', '_', text.upper()).strip('_')


def fetch_one(item, out_dir, index, force):
    path = out_dir / f'{index:02d}_{slug(item["division"])}_{slug(item["district"])}.pdf'
    if force or not path.exists() or path.stat().st_size == 0:
        data = get(item['url'])
        if not data.startswith(b'%PDF'):
            raise RuntimeError(f'{item["url"]}: response is not a PDF')
        path.write_bytes(data)
        status = 'downloaded'
    else:
        status = 'reused'
    data = path.read_bytes()
    return dict(item, file=path.name, bytes=len(data), sha256=hashlib.sha256(data).hexdigest(), status=status)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--page', default=PAGE_URL, help='BBS page that lists the district PDFs')
    parser.add_argument('--out', type=Path, default=DEFAULT_OUT, help=f'download folder (default {DEFAULT_OUT})')
    parser.add_argument('--workers', type=int, default=4)
    parser.add_argument('--force', action='store_true', help='download again even if a file exists')
    args = parser.parse_args()

    page = get(args.page).decode('utf-8')
    items = list_district_pdfs(page)
    if not items:
        sys.exit('No district PDF links found; the page layout may have changed.')
    args.out.mkdir(parents=True, exist_ok=True)
    with ThreadPoolExecutor(max_workers=args.workers) as pool:
        results = list(pool.map(lambda p: fetch_one(p[1], args.out, p[0], args.force), enumerate(items, 1)))

    manifest = dict(page_url=args.page, retrieved_at=datetime.now(timezone.utc).isoformat(timespec='seconds'),
                    page_sha256=hashlib.sha256(page.encode('utf-8')).hexdigest(), pdfs=results)
    (args.out / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    by_division = {}
    for r in results:
        by_division[r['division']] = by_division.get(r['division'], 0) + 1
    print(f'{len(results)} PDFs in {args.out}  ({sum(r["bytes"] for r in results) / 1e6:.1f} MB)')
    print('by division:', by_division)
    print('downloaded:', sum(r['status'] == 'downloaded' for r in results), 'reused:', sum(r['status'] == 'reused' for r in results))


if __name__ == '__main__':
    main()
