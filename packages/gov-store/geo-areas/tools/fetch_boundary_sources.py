#!/usr/bin/env python3
"""Download the administrative boundary sources listed in boundaries/sources.json.

Python 3.10+, standard library only. Implements docs/plans/geo-boundary-data-plan.md §7 step 1.

Files go to a cache folder outside the repository (default %TEMP%/gov-store-boundaries/<id>/).
Partial downloads resume. A file whose SHA-256 differs from sources.json is refused, so a
reissued upstream file stops the build instead of silently changing the map. The members
named under "extract" are unpacked next to the archive for the crosswalk and Mapshaper steps.

  --seed ID=PATH   use a local copy instead of downloading (still verified), e.g.
                   --seed gb_adm4_2015=%USERPROFILE%/Desktop/geoBoundaries-BGD-ADM4-all.zip
"""

import argparse
import hashlib
import json
import shutil
import ssl
import sys
import tempfile
import time
import urllib.request
import zipfile
from pathlib import Path

DATA = Path(__file__).resolve().parents[1] / 'src/database/data'
SOURCES = DATA / 'boundaries/sources.json'
DEFAULT_CACHE = Path(tempfile.gettempdir()) / 'gov-store-boundaries'
USER_AGENT = 'Mozilla/5.0 (gov-store geo-areas boundary fetcher)'
CHUNK = 1 << 20


def sha256(path):
    h = hashlib.sha256()
    with path.open('rb') as f:
        for block in iter(lambda: f.read(CHUNK), b''):
            h.update(block)
    return h.hexdigest()


def download(url, path, size, attempts=20):
    """Resume into path.part until it reaches size bytes (HDX is slow and drops connections)."""
    part = path.with_name(path.name + '.part')
    for attempt in range(1, attempts + 1):
        have = part.stat().st_size if part.exists() else 0
        if have >= size:
            break
        req = urllib.request.Request(url, headers={'User-Agent': USER_AGENT, 'Range': f'bytes={have}-'})
        try:
            with urllib.request.urlopen(req, timeout=120, context=ssl.create_default_context()) as r:
                mode = 'ab' if r.status == 206 else 'wb'  # a server that ignores Range restarts the file
                with part.open(mode) as f:
                    shutil.copyfileobj(r, f, CHUNK)
        except Exception as exc:  # network errors are retried, then reported
            if attempt == attempts:
                raise RuntimeError(f'{url}: {exc}') from exc
            print(f'  retry {attempt}: {exc}', file=sys.stderr)
            time.sleep(min(30, 2 * attempt))
    part.replace(path)


def fetch(src, cache, seed, force):
    folder = cache / src['id']
    folder.mkdir(parents=True, exist_ok=True)
    path = folder / src['file']
    if seed:
        shutil.copyfile(seed, path)
        status = 'seeded'
    elif force or not path.exists() or path.stat().st_size != src['bytes']:
        path.unlink(missing_ok=True)
        download(src['url'], path, src['bytes'])
        status = 'downloaded'
    else:
        status = 'reused'
    digest = sha256(path)
    if digest != src['sha256']:
        raise SystemExit(f"{src['id']}: SHA-256 {digest} does not match sources.json {src['sha256']}; "
                         'upstream changed the file. Review it, then update sources.json and the build.')
    with zipfile.ZipFile(path) as z:
        for name in src.get('extract', []):
            target = folder / name
            if not target.exists() or target.stat().st_size != z.getinfo(name).file_size:
                with z.open(name) as r, target.open('wb') as w:
                    shutil.copyfileobj(r, w, CHUNK)
    return status, path


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--cache', type=Path, default=DEFAULT_CACHE, help=f'cache folder (default {DEFAULT_CACHE})')
    ap.add_argument('--seed', action='append', default=[], metavar='ID=PATH', help='use a local copy of a source')
    ap.add_argument('--force', action='store_true', help='download again even if a complete file exists')
    args = ap.parse_args()

    sources = json.loads(SOURCES.read_text(encoding='utf-8'))['sources']
    seeds = dict(s.split('=', 1) for s in args.seed)
    unknown = set(seeds) - {s['id'] for s in sources}
    if unknown:
        sys.exit(f'--seed: unknown source id {", ".join(sorted(unknown))}')
    for src in sources:
        status, path = fetch(src, args.cache, Path(seeds[src['id']]) if src['id'] in seeds else None, args.force)
        print(f"{src['id']}: {status}, SHA-256 verified -> {path}")


if __name__ == '__main__':
    main()
