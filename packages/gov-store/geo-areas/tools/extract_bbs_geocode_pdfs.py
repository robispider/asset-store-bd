#!/usr/bin/env python3
"""Extract the BBS district geocode PDFs (June 2017 edition) into CSV datasets.

Requires PyMuPDF (``pip install -r tools/requirements-bbs-pdf.txt``). Reads the
folder written by ``fetch_bbs_geocode_pdfs.py`` and writes, by default, to
``src/database/data/bbs-geocode-2017/``:

- ``bbs_geocode_2017_admin.csv``  zila, city corporation, upazila/thana,
  paurashava and union/ward rows (cards 2-6)
- ``bbs_geocode_2017_full.csv.gz`` every row, including mauza/mahalla (card 7)
  and village/para (card 8)
- ``extraction_issues.csv``       rows that failed a structural check
- ``sources.json``                source URLs, hashes, page and row counts

Each PDF table has 22 numbered columns. Their positions differ between files, so
the numbered header row is located on every page and each word is assigned to
the nearest column. Rows are anchored on the card (level) digit in column 1;
wrapped name lines are attached to the nearest anchor. Bangla names are kept as
extracted (``bn_name_raw``): the PDFs embed Nikosh glyphs without a usable
Unicode map, so the raw text has visual vowel order and private glyphs.
``bn_name`` is a best-effort repair (see ``repair_bangla``) and must not be
treated as authoritative.
"""

import argparse
import csv
import gzip
import hashlib
import json
import re
import sys
import tempfile
from collections import Counter, defaultdict
from pathlib import Path

try:
    import pymupdf
except ImportError:  # pragma: no cover - guidance for a missing optional dependency
    sys.exit('PyMuPDF is required: pip install -r packages/gov-store/geo-areas/tools/requirements-bbs-pdf.txt')

DEFAULT_PDFS = Path(tempfile.gettempdir()) / 'gov-store-bbs-geocode' / 'pdfs'
DEFAULT_OUT = Path(__file__).resolve().parents[1] / 'src/database/data/bbs-geocode-2017'

# Column numbers printed in the PDF header row, in order.
CODE_COLUMNS = {
    1: 'card', 2: 'division_code', 3: 'zila_code', 4: 'city_corporation_code',
    5: 'upazila_thana_code', 6: 'paurashava_code', 7: 'union_ward_code_old',
    8: 'union_ward_code_new', 9: 'mauza_mahalla_code_old', 10: 'mauza_code_new',
    11: 'jl_no', 12: 'village_code_old', 13: 'village_mahalla_para_code_new', 14: 'rmo',
}
TAIL_COLUMNS = {17: 'total_parts', 18: 'part_no', 19: 'land_acres', 20: 'river_acres',
                21: 'forest_acres', 22: 'area_type'}
CARD_LEVELS = {'2': 'zila', '3': 'city_corporation', '4': 'upazila_thana', '5': 'paurashava',
               '6': 'union_ward', '7': 'mauza_mahalla', '8': 'village_mahalla_para'}
# Codes each card must carry (beyond card/division/zila).
REQUIRED = {'3': ['city_corporation_code'], '4': ['upazila_thana_code'],
            '5': ['upazila_thana_code', 'paurashava_code'],
            '6': ['upazila_thana_code', 'union_ward_code_new'],
            '7': ['upazila_thana_code', 'union_ward_code_new'],
            '8': ['upazila_thana_code', 'union_ward_code_new']}
# BBS division codes. The BBS web page lists the Mymensingh (45) districts under Dhaka.
DIVISIONS = {'10': 'BARISAL', '20': 'CHITTAGONG', '30': 'DHAKA', '40': 'KHULNA', '45': 'MYMENSINGH',
             '50': 'RAJSHAHI', '55': 'RANGPUR', '60': 'SYLHET'}
FIELDS = (['source_file', 'page', 'district_label', 'division_name', 'card', 'level']
          + list(CODE_COLUMNS.values())[1:] + ['en_name', 'bn_name', 'bn_name_raw']
          + list(TAIL_COLUMNS.values()) + ['bbs_path'])
BENGALI = re.compile(r'[ঀ-৿]')


def header_columns(words):
    """Return ({column_number: x_center}, header_bottom) from the '1 .. 22' row, or None.

    Columns are taken by position: the Jashore volume misprints column 12 as '11'.
    """
    digits = [w for w in words if w[1] < 220 and re.fullmatch(r'\d{1,2}', w[4])]
    for w in digits:
        if w[4] != '1':
            continue
        row = sorted((d for d in digits if abs(d[1] - w[1]) < 3), key=lambda d: d[0])
        if len(row) == 22 and row[0][4] == '1' and row[-1][4] == '22':
            return {i: (d[0] + d[2]) / 2 for i, d in enumerate(row, 1)}, max(d[3] for d in row)
    return None


def dedupe(words):
    """Drop overprinted duplicates and fragments contained in a longer word."""
    words = sorted(words, key=lambda w: -(w[2] - w[0]))
    kept = []
    for w in words:
        if any(abs(w[1] - k[1]) < 2.5 and w[0] >= k[0] - 0.6 and w[2] <= k[2] + 0.6 for k in kept):
            continue
        kept.append(w)
    return kept


def drop_footer(words):
    lines = defaultdict(list)
    for w in words:
        lines[round(w[1])].append(w)
    drop = set()
    for ws in lines.values():
        if [w[4] for w in sorted(ws, key=lambda w: w[0])][1:2] == ['/'] and len(ws) == 3:
            drop.update(id(w) for w in ws)
    return [w for w in words if id(w) not in drop]


def split_mixed(words):
    """Split a word where an English name runs into a Bangla glyph (e.g. 'SREEPUR̺')."""
    out = []
    for w in words:
        m = re.fullmatch(r"([A-Za-z0-9.()'&/,-]*[A-Za-z][A-Za-z0-9.()'&/,-]*)([^\x00-\x7f].*)", w[4])
        if m:
            cut = w[0] + (w[2] - w[0]) * len(m.group(1)) / len(w[4])
            out += [(w[0], w[1], cut, w[3], m.group(1)), (cut, w[1], w[2], w[3], m.group(2))]
        else:
            out.append(w)
    return out


def join_words(ws):
    lines = []
    for w in sorted(ws, key=lambda w: ((w[1] + w[3]) / 2, w[0])):
        if lines and abs(lines[-1][0] - (w[1] + w[3]) / 2) < 3:
            lines[-1][1].append(w)
        else:
            lines.append([(w[1] + w[3]) / 2, [w]])
    text = ''
    for _, line in lines:
        part = ' '.join(w[4] for w in sorted(line, key=lambda w: w[0]))
        text = part if not text else (text + part if text.endswith('-') else text + ' ' + part)
    return re.sub(r'\s+', ' ', text).strip()


# --- Bangla repair -------------------------------------------------------------
# Nikosh ligature glyphs that PyMuPDF maps to unrelated code points. Most are a
# consonant + u-kar or a conjunct. The table was read off the column headers and
# then learned by aligning repaired names with the current Bangla names of areas
# matched on English (see the BBS crosswalk); entries with fewer than two
# consistent observations were left out. Unknown glyphs are kept as extracted
# and counted in sources.json so the table can be extended.
GLYPHS = {
    # vowel signs drawn before the consonant
    'έ': 'ে', 'ή': 'ৈ',
    # consonant + u/uu/ri-kar
    'Ň': 'কু', 'ŭ': 'খু', 'Ŷ': 'গু', 'ƌ': 'ঘু', 'ƥ': 'চু', 'Ƹ': 'ছু', 'ǁ': 'জু', 'Ǖ': 'ঝু',
    'Ǩ': 'টু', 'ǽ': 'ডু', 'ȣ': 'তু', 'Ⱥ': 'থু', 'Ƀ': 'দু', 'ɡ': 'ধু', 'ɢ': 'ধু', 'ɭ': 'নু',
    'ɮ': 'নু', 'ʔ': 'পু', 'ʕ': 'পূ', 'ʫ': 'ফু', 'ʸ': 'বু', 'ʺ': 'বৃ', '˘': 'ভূ', '˗': 'ভূ',
    '˥': 'মু', '˦': 'মূ', '˧': 'মৃ', '̌': 'যু', 'Τ': 'য়ু', '̓': 'রু', '̔': 'রূ', '̛': 'লু',
    '̶': 'শু', 'ͦ': 'সু', 'ͧ': 'সূ', 'Β': 'হু', 'Ξ': 'রু', 'Ƕ': 'ঠু', 'ŉ': 'কৃ', 'ʨ': 'পঁ',
    # conjuncts
    'Ő': 'ক্ক', 'ŗ': 'ক্ত', 'ō': 'ক্র', 'Ũ': 'ক্স', 'ţ': 'ক্ষ', 'ŧ': 'ক্ষ্ম', 'Ż': 'গ্র',
    'Ɲ': 'ঙ্গ', 'ƞ': 'ঙ্গু', 'Ɨ': 'ঙ্ক', 'Ʈ': 'চ্ছ', 'Ǣ': 'ঞ্জ', 'ǝ': 'ঞ্চ', 'Ǟ': 'ঞ্চু',
    'ǡ': 'ঞ্ছ', 'ǯ': 'ট্ট', 'ȁ': 'ড্ড', 'Ȁ': 'ড্র', 'Ȫ': 'ত্ত', 'Ȱ': 'ত্ন', 'Ȩ': 'ত্র',
    'ȩ': 'ত্রু', 'Ɍ': 'দ্দ', 'ɏ': 'দ্ধ', 'ɖ': 'দ্ব', 'ɞ': 'দ্ম', 'ɉ': 'দ্র', 'ɶ': 'ন্ড',
    'Ș': 'ন্ড', 'ɻ': 'ন্ত', 'ʁ': 'ন্দ', 'ʂ': 'ন্দু', 'ʃ': 'ন্দ্র', 'ʅ': 'ন্দ্ব', 'ʆ': 'ন্ধ',
    'ʋ': 'ন্ন', 'ʞ': 'প্ত', 'ʚ': 'প্র', '˂': 'ব্দ', '˃': 'ব্দু', 'ʾ': 'ব্র', '̅': 'ম্ম',
    '˲': 'ম্প', '˿': 'ম্ব', '́': 'ম্ভ', '̂': 'ম্ভু', '̲': 'ল্ল', '́': 'শ্ব',
    '̽': 'শ্চ', '̺': 'শ্র', '͕': 'ষ্ণু', '͗': 'ষ্ট', 'ͭ': 'স্ক', 'ͮ': 'স্কু', 'ͷ': 'স্ত',
    'ͼ': 'স্থ', 'Ή': 'স্ব', 'α': '্য',
    # learned from English transliterations of mauza/village names
    '͔': 'ষ্ণ', '͵': 'স্ট', 'Ι': 'হ্ম', 'ʒ': 'ন্স', 'ˊ': 'ব্ব', 'ˌ': 'ব্ল', '̪': 'ল্প',
    'ǈ': 'জ্জ', 'ɲ': 'ন্ট', 'Ȗ': 'ণ্ঠ', 'ș': 'ন্ডু',
    # reph forms
    'Ǆ': 'র্জ', 'ǫ': 'র্ট', 'ǿ': 'র্ড', 'Ȑ': 'র্ণ', 'ʗ': 'র্প', 'ʻ': 'র্ব', '˨': 'র্ম',
    '̹': 'র্শ', 'Ə': 'র্ঘ', 'Ź': 'র্গ', 'Ɇ': 'র্দ', 'Ȧ': 'র্ত', '̎': 'র্য', '̝': 'র্ল',
    'Ŋ': 'র্ক', 'ɰ': 'র্ন',
    # candrabindu
    'ȶ': 'ঁ',
}
PREFIX_SIGNS = 'িেৈ'  # ি ে ৈ are drawn before their consonant
CONSONANT = re.compile(r'[ক-হড়-য়ৎ]')


def repair_bangla(raw):
    if not raw:
        return '', set()
    text = ''.join(GLYPHS.get(ch, ch) for ch in raw)
    unknown = {ch for ch in text if ord(ch) > 0x7f and not BENGALI.match(ch) and ch not in ' -().,/'}
    out, i = [], 0
    chars = list(text)
    while i < len(chars):
        ch = chars[i]
        if ch in PREFIX_SIGNS and i + 1 < len(chars) and CONSONANT.match(chars[i + 1]):
            # Move the sign after the following consonant cluster (C(্C)*).
            j = i + 2
            while j + 1 < len(chars) and chars[j] == '্' and CONSONANT.match(chars[j + 1]):
                j += 2
            if j < len(chars) and chars[j] == '়':
                j += 1
            cluster = ''.join(chars[i + 1:j])
            sign = ch
            if ch == 'ে' and j < len(chars) and chars[j] == 'া':  # ে + া -> ো
                sign, j = 'ো', j + 1
            elif ch == 'ে' and j < len(chars) and chars[j] == 'ৗ':  # ে + ৗ -> ৌ
                sign, j = 'ৌ', j + 1
            out.append(cluster + sign)
            i = j
            continue
        out.append(ch)
        i += 1
    text = ''.join(out)
    # A dependent vowel sign never starts a word: the space is a PDF word split.
    text = re.sub(r'\s+(?=[া-ৌৗ])', '', text)
    # So is a lone consonant after a Bangla word ('পশ্চি ম' -> 'পশ্চিম').
    text = re.sub(r'(?<=[ঀ-৿]) (?=[ক-হড়-য়][া-ৌ]?(?: |$))', '', text)
    return re.sub(r'\s+', ' ', text).strip(), unknown


# --- extraction ---------------------------------------------------------------

def extract_page(page):
    words = [w[:5] for w in page.get_text('words')]
    found = header_columns(words)
    if not found:
        return None, []
    cols, header_bottom = found
    # Use the word centre: the first row can start right under the header.
    body = drop_footer(dedupe([w for w in words if (w[1] + w[3]) / 2 > header_bottom + 1]))
    c14, c16, c17 = cols[14], cols[16], cols[17]
    name_left, name_right = c14 + 6, c17 - 4
    bn_split = (cols[15] + c16) / 2

    anchors = sorted({round((w[1] + w[3]) / 2, 1): w for w in body
                      if re.fullmatch(r'[1-9]', w[4]) and abs((w[0] + w[2]) / 2 - cols[1]) < 6}.items())
    # Collapse anchors closer than 2pt (overprinted rows).
    merged = []
    for y, w in anchors:
        if merged and y - merged[-1][0] < 2:
            continue
        merged.append((y, w))
    if not merged:
        return cols, []
    rows = [dict(card=w[4], codes=defaultdict(list), tail=defaultdict(list), en=[], bn=[], issues=[])
            for _, w in merged]
    ys = [y for y, _ in merged]

    for w in split_mixed(body):
        yc = (w[1] + w[3]) / 2
        k = min(range(len(ys)), key=lambda i: abs(ys[i] - yc))
        if abs(ys[k] - yc) > 22:
            continue
        r = rows[k]
        xc = (w[0] + w[2]) / 2
        if w[0] >= name_left and w[2] <= name_right:
            is_bn = bool(BENGALI.search(w[4])) or any(ord(c) > 0x7f for c in w[4]) or (
                not re.search(r'[A-Za-z0-9]', w[4]) and xc > bn_split)
            (r['bn'] if is_bn else r['en']).append(w)
        elif w[2] < name_left:
            col = min(CODE_COLUMNS, key=lambda c: abs(cols[c] - xc))
            if col == 1 and abs(ys[k] - yc) < 2:
                continue  # the anchor itself
            r['codes'][col].append(w)
        else:
            col = min(TAIL_COLUMNS, key=lambda c: abs(cols[c] - xc))
            r['tail'][col].append(w)

    out = []
    for r in rows:
        rec = {'card': r['card'], 'en_name': join_words(r['en']), 'bn_name_raw': join_words(r['bn'])}
        issues = []
        for col, name in list(CODE_COLUMNS.items())[1:]:
            vals = [w[4] for w in r['codes'].get(col, [])]
            if len(vals) > 1:
                issues.append(f'{name}:multiple_values:{"|".join(vals)}')
            # JL numbers are printed as given, e.g. '70/138' or '27/a'; other codes are digits.
            if vals and name != 'jl_no' and not re.fullmatch(r'\d+', vals[0]):
                issues.append(f'{name}:non_numeric:{vals[0]}')
            rec[name] = vals[0] if vals else ''
        tail = resolve_tail_collisions(r['tail'])
        for col, name in TAIL_COLUMNS.items():
            vals = [w[4] for w in sorted(tail.get(col, []), key=lambda w: (round(w[1]), w[0]))]
            if len(vals) > 1:
                # A long figure wraps inside its cell (688188.4 / 874 -> 688188.4874).
                issues.append(f'{name}:wrapped_value_joined:{"|".join(vals)}')
            rec[name] = ''.join(vals)
        rec['_issues'] = issues
        out.append(rec)
    return cols, out


def resolve_tail_collisions(tail):
    """Two figures on one line in the same column: shift one into an empty neighbour."""
    tail = {c: list(ws) for c, ws in tail.items()}
    for col in sorted(TAIL_COLUMNS):
        ws = sorted(tail.get(col, []), key=lambda w: w[0])
        same_line = [w for w in ws if any(o is not w and abs(o[1] - w[1]) < 2 for o in ws)]
        if len(same_line) == 2:
            left, right = same_line
            if col - 1 in TAIL_COLUMNS and not tail.get(col - 1):
                tail[col - 1] = [left]
                tail[col].remove(left)
            elif col + 1 in TAIL_COLUMNS and not tail.get(col + 1):
                tail[col + 1] = [right]
                tail[col].remove(right)
    return tail


def bbs_path(r):
    parts = [r['division_code'], r['zila_code'], r['city_corporation_code'], r['upazila_thana_code'],
             r['paurashava_code'], r['union_ward_code_new'], r['mauza_code_new'],
             r['village_mahalla_para_code_new']]
    level = int(r['card'])
    keep = {2: 2, 3: 3, 4: 4, 5: 5, 6: 6, 7: 7, 8: 8}[level]
    return '-'.join(p or '_' for p in parts[:keep])


def validate(r):
    issues = list(r.pop('_issues'))
    card = r['card']
    if card not in CARD_LEVELS:
        issues.append(f'unknown_card:{card}')
        return issues
    for name in ['division_code', 'zila_code'] + REQUIRED.get(card, []):
        if not r.get(name):
            issues.append(f'missing:{name}')
    if card == '6' and not r['city_corporation_code'] and not r['paurashava_code'] and not r['union_ward_code_old']:
        issues.append('missing:union_ward_code_old')
    if not r['en_name']:
        issues.append('missing:en_name')
    return issues


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--pdfs', type=Path, default=DEFAULT_PDFS, help=f'folder with manifest.json (default {DEFAULT_PDFS})')
    parser.add_argument('--out', type=Path, default=DEFAULT_OUT)
    args = parser.parse_args()

    manifest = json.loads((args.pdfs / 'manifest.json').read_text(encoding='utf-8'))
    rows, issues, sources = [], [], []
    unknown_glyphs = Counter()
    for item in manifest['pdfs']:
        path = args.pdfs / item['file']
        if hashlib.sha256(path.read_bytes()).hexdigest() != item['sha256']:
            sys.exit(f'{path.name}: SHA-256 differs from manifest; refetch before extracting.')
        doc = pymupdf.open(path)
        cover = doc[0].get_text()
        data_pages, file_rows = 0, []
        for pno, page in enumerate(doc, 1):
            cols, recs = extract_page(page)
            if cols is None:
                continue
            data_pages += 1
            for rec in recs:
                rec.update(source_file=item['file'], page=pno, district_label=item['district'],
                           level=CARD_LEVELS.get(rec['card'], ''),
                           division_name=DIVISIONS.get(rec.get('division_code', ''), ''))
                rec['bn_name'], unknown = repair_bangla(rec['bn_name_raw'])
                unknown_glyphs.update(unknown)
                problems = validate(rec)
                rec['bbs_path'] = bbs_path(rec) if rec['card'] in CARD_LEVELS else ''
                if problems:
                    issues.append(dict(source_file=item['file'], page=pno, card=rec['card'],
                                       bbs_path=rec['bbs_path'], en_name=rec['en_name'], issues=';'.join(problems)))
                file_rows.append(rec)
        rows.extend(file_rows)
        sources.append(dict(item, pages=len(doc), data_pages=data_pages, rows=len(file_rows),
                            rows_by_level=dict(Counter(r['level'] for r in file_rows)),
                            cover_mentions_june_2017='জুন ২০১৭' in cover))
        print(f'{item["file"]}: {data_pages}/{len(doc)} pages, {len(file_rows)} rows', flush=True)

    args.out.mkdir(parents=True, exist_ok=True)
    admin = [r for r in rows if r['card'] in '23456']
    with (args.out / 'bbs_geocode_2017_admin.csv').open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, FIELDS, extrasaction='ignore')
        w.writeheader()
        w.writerows(admin)
    with gzip.open(args.out / 'bbs_geocode_2017_full.csv.gz', 'wt', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, FIELDS, extrasaction='ignore')
        w.writeheader()
        w.writerows(rows)
    with (args.out / 'extraction_issues.csv').open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, ['source_file', 'page', 'card', 'bbs_path', 'en_name', 'issues'])
        w.writeheader()
        w.writerows(issues)
    summary = dict(edition='Bangladesh Geocode, district volumes, June 2017 (BBS)',
                   page_url=manifest['page_url'], retrieved_at=manifest['retrieved_at'],
                   total_rows=len(rows), admin_rows=len(admin),
                   rows_by_level=dict(Counter(r['level'] for r in rows)),
                   rows_with_issues=len(issues),
                   unmapped_bangla_glyphs={f'U+{ord(c):04X}': n for c, n in unknown_glyphs.most_common()},
                   sources=sources)
    (args.out / 'sources.json').write_text(json.dumps(summary, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({k: summary[k] for k in ['total_rows', 'admin_rows', 'rows_by_level', 'rows_with_issues']},
                     ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
