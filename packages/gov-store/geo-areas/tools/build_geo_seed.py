#!/usr/bin/env python3
"""Build the geo-areas seed bundle: every area gets a geocode.

Python 3.10+, standard library only. Implements docs/plans/bbs-geocode-adoption-plan.md §4.

Inputs (all under src/database/data/):
  geo_areas.base.csv                           the hand-maintained 9-column base (the original
                                               9,111 rows); edit this, never geo_areas.csv
  bbs-geocode-2017/bbs_geocode_2017_admin.csv  extracted BBS admin areas
  bbs-geocode-2017/bbs_geocode_2017_full.csv.gz  extracted BBS areas incl. mauza/village
  bbs-geocode-2017/crosswalk/matched.csv       automatic links
  bbs-geocode-2017/review/decisions.csv        confirmed review decisions

Outputs:
  geo_areas.csv                     generated: base lines byte-identical except the D2 English fixes;
                                    BBS areas missing from GovStore appended
  seed/geo_area_codes.csv           GeoAreaId -> bbs_code, legacy code, status, source, attributes
  seed/geo_area_localities.csv.gz   mauza (GeoLevel 6) and village (GeoLevel 7) rows with codes
  seed/manifest.json, seed/BUILD_REPORT.md

Nothing is written unless every gate passes (``--check`` runs the gates only).
"""

import argparse
import csv
import gzip
import hashlib
import io
import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path

DATA = Path(__file__).resolve().parents[1] / 'src/database/data'
BBS = DATA / 'bbs-geocode-2017'
SEED = DATA / 'seed'
VERSION = 'bd-admin-areas-2026-10-21.1'
SOURCE = 'bbs-geocode-2017'
BASE_FIELDS = ['GeoAreaId', 'hid', 'geo_type', 'parent_geo_code', 'geo_code', 'bn_name', 'domain', 'en_name', 'GeoLevel']
CODE_FIELDS = ['GeoAreaId', 'bbs_code', 'bbs_code_legacy', 'code_status', 'code_source', 'bbs_attributes']
ADDED_ID_START, ADDED_CODE_START, LOCALITY_ID_START = 9359, 900001, 1_000_000
ATTR_KEYS = ['jl_no', 'rmo', 'land_acres', 'river_acres', 'forest_acres', 'total_parts', 'part_no', 'area_type']

# Provisional code = parent code + filler + 'Pnn'. The filler keeps the
# segment layout division-zila-city-upazila-paurashava-union/ward.
PROVISIONAL_FILLER = {'upazilla': '-_-', 'city': '-', 'city_thana': '-', 'pourasabha': '-', 'union': '-_-',
                      'special_area': '-_-', 'pouro_ward': '-', 'thana_ward': '-_-'}


class GateError(Exception):
    pass


def read_csv(path):
    with path.open(encoding='utf-8-sig', newline='') as f:
        return list(csv.DictReader(f))


def strip_path(path):
    """'10-06-_-02-_-142' -> parent '10-06-_-02' (trailing empty levels removed)."""
    parts = path.split('-')[:-1]
    while parts and parts[-1] == '_':
        parts.pop()
    return '-'.join(parts)


def legacy_code(r, code=''):
    """Concatenated old-style code (2-digit union/ward, old mauza/village codes).

    Empty when the leaf's old code is not printed. Areas without a dataset row
    (divisions, unnamed thanas) use their path, whose segments are all 2-digit."""
    keys = ['division_code', 'zila_code', 'city_corporation_code', 'upazila_thana_code', 'paurashava_code',
            'union_ward_code_old', 'mauza_mahalla_code_old', 'village_code_old']
    card = r.get('card', '')
    if not card:
        parts = [p for p in code.split('-') if p != '_']
        return ''.join(parts) if parts and all(len(p) == 2 and p.isdigit() for p in parts) else ''
    leaf = {'6': 'union_ward_code_old', '7': 'mauza_mahalla_code_old', '8': 'village_code_old'}.get(card)
    if leaf and not r.get(leaf):
        return ''
    return ''.join(r.get(k, '') or '' for k in keys[:int(card)])


def bbs_attrs(r, **extra):
    out = {'bbs_en_name': r.get('en_name', ''), 'bbs_bn_name': r.get('bn_name', '')}
    out.update({k: r[k] for k in ATTR_KEYS if r.get(k) not in (None, '', '0', '0.00')})
    if r.get('source_file') and r.get('page'):
        out['source'] = f"{r['source_file']}#page={r['page']}"
    out.update({k: v for k, v in extra.items() if v})
    return {k: v for k, v in out.items() if v not in (None, '')}


def is_junk_english(name):
    return name in ('', 'Www') or bool(re.match(r'^\d+no', name, re.I)) or name.endswith(' Distric')


def english_from_bbs(bbs_name, geo_type):
    title = ' '.join(w.capitalize() for w in bbs_name.split())
    return {'district': f'{title} District', 'upazilla': f'{title} Upazilla'}.get(geo_type, title)


def file_sha256(path):
    """geo_areas.csv is a git text file (LF or CRLF by platform): hash it as CRLF."""
    data = path.read_bytes()
    if path.name == 'geo_areas.csv':
        data = data.replace(b'\r\n', b'\n').replace(b'\n', b'\r\n')
    return hashlib.sha256(data).hexdigest()


def csv_line(values, eol=b'\r\n'):
    buf = io.StringIO()
    csv.writer(buf, lineterminator=eol.decode()).writerow(values)
    return buf.getvalue().encode('utf-8')


# --- build --------------------------------------------------------------------

def build():
    raw = (DATA / 'geo_areas.base.csv').read_bytes()
    if not raw.startswith(b'\xef\xbb\xbf'):
        raise GateError('geo_areas.base.csv lost its BOM.')
    eol = b'\r\n' if b'\r\n' in raw else b'\n'  # git may check the file out either way
    lines = raw[3:].split(eol)
    if lines[-1] != b'':
        raise GateError('geo_areas.base.csv must end with a newline.')
    lines = lines[:-1]
    base = [dict(zip(BASE_FIELDS, next(csv.reader([l.decode('utf-8')])))) for l in lines]
    byid = {r['GeoAreaId']: r for r in base}
    bycode = {r['geo_code']: r for r in base}

    admin = {}
    for r in read_csv(BBS / 'bbs_geocode_2017_admin.csv'):
        admin.setdefault(r['bbs_path'], r)
    matched = {r['GeoAreaId']: r for r in read_csv(BBS / 'crosswalk/matched.csv')}
    decisions = read_csv(BBS / 'review/decisions.csv')
    unconfirmed = [d for d in decisions if not d['decision']]
    if unconfirmed:
        raise GateError(f'{len(unconfirmed)} review rows have no confirmed decision (gate 5).')
    cur_dec = {d['key']: d for d in decisions if d['side'] == 'current'}
    bbs_dec = {d['key']: d for d in decisions if d['side'] == 'bbs'}

    codes, report = {}, defaultdict(list)

    def bbs_record(path, fallback=None):
        r = admin.get(path)
        if r:
            return r
        f = fallback or {}
        return {'en_name': f.get('bbs_en_name', ''), 'bn_name': f.get('bbs_bn_name', ''),
                'source_file': (f.get('bbs_source', '') or '').split('#')[0], 'page': '', 'card': ''}

    def official(row, path, method, fallback=None):
        rec = bbs_record(path, fallback)
        if row['geo_type'] == 'city_thana':
            # A thana inside a city carries the city segment, like its wards.
            parent = codes.get(bycode[row['parent_geo_code']]['GeoAreaId'])
            d, z, c = parent['bbs_code'].split('-')[:3]
            path = f"{d}-{z}-{c}-{path.split('-')[3]}"
        return dict(bbs_code=path, bbs_code_legacy=legacy_code(rec, path), code_status='bbs_2017', code_source=SOURCE,
                    attrs=bbs_attrs(rec, match_method=method))

    serial = Counter()

    def provisional(row, extra):
        parent = codes[bycode[row['parent_geo_code']]['GeoAreaId']]
        base_code = parent['bbs_code']
        serial[(base_code, row['geo_type'])] += 1
        filler = PROVISIONAL_FILLER.get(row['geo_type'], '-')
        return dict(bbs_code=f"{base_code}{filler}P{serial[(base_code, row['geo_type'])]:02d}", bbs_code_legacy='',
                    code_status='provisional', code_source=f'provisional:{VERSION}', attrs=extra)

    # 1. Existing rows, parents first.
    order = sorted(base, key=lambda r: (int(r['GeoLevel']), int(r['GeoAreaId'])))
    duplicates = []
    for r in order:
        gid, d = r['GeoAreaId'], cur_dec.get(r['GeoAreaId'])
        if r['geo_type'] == 'country':
            codes[gid] = dict(bbs_code='BD', bbs_code_legacy='', code_status='national', code_source=SOURCE,
                              attrs={})
        elif gid in matched:
            codes[gid] = official(r, matched[gid]['bbs_path'], matched[gid]['match_method'], matched[gid])
        elif d and d['decision'] == 'link':
            codes[gid] = official(r, d['target'], 'reviewed')
            if d['note']:
                codes[gid]['attrs']['review_note'] = d['note']
        elif d and d['decision'] in ('moved', 'newer'):
            rec = admin.get(d['target'], {}) if d['decision'] == 'moved' else {}
            extra = bbs_attrs(rec, bbs_2017_code=d['target']) if rec else {}
            if d['note']:
                extra['review_note'] = d['note']
            codes[gid] = provisional(r, extra)
        elif d and d['decision'] == 'duplicate':
            duplicates.append((r, d))
        else:
            raise GateError(f'GeoAreaId {gid} ({r["en_name"]}) has neither a link nor a decision.')
    # BBS occasionally prints one code for two areas (Cumilla city wards 23 and
    # 26 are both '026'). The area whose name agrees with the code keeps it.
    holders = defaultdict(list)
    for gid, c in codes.items():
        if c['code_status'] == 'bbs_2017':
            holders[c['bbs_code']].append(gid)
    for code, gids in holders.items():
        if len(gids) < 2:
            continue
        seg = code.rsplit('-', 1)[1]
        agree = [g for g in gids if seg.isdigit() and re.search(rf'\b0*{int(seg)}\b', byid[g]['en_name'])]
        keep = agree[0] if len(agree) == 1 else min(gids, key=int)
        for n, gid in enumerate((g for g in sorted(gids, key=int) if g != keep), 2):
            c = codes[gid]
            c.update(bbs_code=f'{code}-D{n}', code_status='provisional', code_source=f'provisional:{VERSION}')
            c['attrs'].update(bbs_2017_code=code, bbs_code_repeated=True)
            report['BBS codes printed for two areas'].append(f'{code}: kept by {keep}, {gid} -> {code}-D{n}')
    for r, d in duplicates:
        orig = codes[d['target']]
        codes[r['GeoAreaId']] = dict(orig, code_status='duplicate',
                                     attrs={'duplicate_of': int(d['target']), 'review_note': d['note']})

    # 2. D2: junk English names take the BBS English.
    en_fixes = {}
    for r in base:
        c = codes[r['GeoAreaId']]
        if is_junk_english(r['en_name']):
            bbs_en = c['attrs'].get('bbs_en_name', '')
            if bbs_en and c['code_status'] in ('bbs_2017', 'provisional'):
                en_fixes[r['GeoAreaId']] = english_from_bbs(bbs_en, r['geo_type'])
            else:
                report['junk English kept (no BBS name)'].append(f"{r['GeoAreaId']} {r['en_name']!r} ({r['geo_type']})")

    # Owners: which current row stands for a BBS path (for children and localities).
    owner = {}
    for gid, c in codes.items():
        if c['code_status'] == 'bbs_2017':
            owner.setdefault(c['bbs_code'], gid)
        if c['attrs'].get('bbs_2017_code'):
            owner.setdefault(c['attrs']['bbs_2017_code'], gid)
    for gid, m in matched.items():  # a city thana also owns its raw BBS path
        owner.setdefault(m['bbs_path'], gid)

    # 3. BBS areas missing from GovStore.
    added, next_id, next_code = [], ADDED_ID_START, ADDED_CODE_START
    kinds = {'union_ward': None, 'paurashava': 'pourasabha', 'upazila_thana': 'upazilla', 'city_corporation': 'city'}
    for path in sorted((k for k, d in bbs_dec.items() if d['decision'] == 'add'), key=lambda p: (p.count('-'), p)):
        d, rec = bbs_dec[path], admin[path]
        parent_gid = owner.get(strip_path(path))
        if not parent_gid:
            raise GateError(f'BBS add {path} {rec["en_name"]}: parent {strip_path(path)} has no current owner.')
        parent = byid.get(parent_gid) or next(a for a in added if a['GeoAreaId'] == parent_gid)
        geo_type = kinds.get(rec['level'])
        if geo_type is None:
            if re.search(r'-99[78]$', path):
                geo_type = 'special_area'
            elif rec['city_corporation_code']:
                geo_type = 'thana_ward'
            elif rec['paurashava_code']:
                geo_type = 'pouro_ward'
            else:
                geo_type = 'union'
        m = re.fullmatch(r'geo_code=(\d+)', d['target'] or '')
        if m:
            geo_code = m.group(1)
            if geo_code in bycode:
                raise GateError(f'BBS add {path}: requested geo_code {geo_code} already exists.')
        else:
            geo_code, next_code = str(next_code), next_code + 1
        en = rec['en_name'] if geo_type in ('pourasabha', 'pouro_ward', 'thana_ward') else english_from_bbs(
            rec['en_name'], geo_type)
        # Only the rural tree carries hid today (plan D6); added urban rows follow suit.
        with_hid = parent['hid'] and geo_type in ('upazilla', 'union', 'special_area')
        row = dict(GeoAreaId=str(next_id), hid=f"{parent['hid']}{geo_code}/" if with_hid else '',
                   geo_type=geo_type, parent_geo_code=parent['geo_code'], geo_code=geo_code,
                   bn_name=rec['bn_name'], domain='', en_name=en, GeoLevel=str(int(parent['GeoLevel']) + 1))
        next_id += 1
        added.append(row)
        bycode[geo_code] = row
        codes[row['GeoAreaId']] = dict(bbs_code=path, bbs_code_legacy=legacy_code(rec), code_status='bbs_2017',
                                       code_source=SOURCE, attrs=bbs_attrs(rec, review_note=d['note'], added=True))
        owner.setdefault(path, row['GeoAreaId'])
        if re.search(r'[^\x00-\x7fঀ-৿]', rec['bn_name']):
            report['added areas whose Bangla needs a hand check'].append(f"{row['GeoAreaId']} {path} {rec['bn_name']}")
    byid.update({a['GeoAreaId']: a for a in added})

    # 4. Localities: mauzas first, then villages. A village attaches to the
    #    nearest mauza with its code in source order (preceding if any): split
    #    '(PART)' mauzas and repeated codes print villages before their mauza.
    localities, held, seen_code = [], Counter(), set()
    mauza_rows, next_loc = defaultdict(list), LOCALITY_ID_START
    superseded = {k for k, d in bbs_dec.items() if d['decision'] == 'superseded'}
    with gzip.open(BBS / 'bbs_geocode_2017_full.csv.gz', 'rt', encoding='utf-8-sig', newline='') as f:
        source = [(i, rec) for i, rec in enumerate(csv.DictReader(f)) if rec['card'] in ('7', '8')]
    for idx, rec in sorted(source, key=lambda x: (x[1]['card'], x[0])):
        path, parent_path = rec['bbs_path'], strip_path(rec['bbs_path'])
        if rec['card'] == '7':
            parent_gid = owner.get(parent_path)
        else:
            candidates = mauza_rows.get(parent_path, [])
            before = [g for i, g in candidates if i < idx]
            parent_gid = before[-1] if before else (candidates[0][1] if candidates else None)
        if not parent_gid:
            if parent_path in superseded or any(parent_path.startswith(s + '-') for s in superseded):
                reason = 'parent superseded (split/merged after 2017)'
            elif rec['card'] == '8' and owner.get(strip_path(parent_path)):
                reason = 'village whose mauza the volume never prints'
            elif rec['card'] == '8':
                reason = 'village under a held-back mauza'
            else:
                reason = 'mauza whose union/ward is not in GovStore'
            held[reason] += 1
            continue
        parent = byid[parent_gid]
        pc = codes.get(parent_gid) or parent  # a village's parent is a locality row
        seg = path.rsplit('-', 1)[1]
        if pc['code_status'] == 'bbs_2017' and pc['bbs_code'] == parent_path:
            code, status, extra = path, 'bbs_2017', {}
        else:
            code, status, extra = f"{pc['bbs_code']}-{seg}", 'provisional', {'bbs_2017_code': path}
        n, first = 1, code
        while code in seen_code:  # BBS printed the same code twice
            n += 1
            code, status, extra = f"{first}-D{n}", 'provisional', {'bbs_2017_code': path, 'bbs_code_repeated': True}
        seen_code.add(code)
        gid = str(next_loc)
        next_loc += 1
        level = 6 if rec['card'] == '7' else 7
        row = dict(GeoAreaId=gid, hid=f"{parent['hid']}{gid}/" if parent['hid'] else '',
                   geo_type='mauza' if rec['card'] == '7' else 'village', parent_geo_code=parent['geo_code'],
                   geo_code=gid, bn_name=rec['bn_name'], domain='', en_name=rec['en_name'] or rec['bn_name'],
                   GeoLevel=str(level))
        attrs = bbs_attrs(rec, **extra)
        attrs.pop('bbs_en_name', None)
        attrs.pop('bbs_bn_name', None)
        row.update(bbs_code=code, bbs_code_legacy=legacy_code(rec), code_status=status,
                   code_source=SOURCE if status == 'bbs_2017' else f'provisional:{VERSION}',
                   bbs_attributes=json.dumps(attrs, ensure_ascii=False, separators=(',', ':')))
        localities.append(row)
        byid[gid] = row
        if rec['card'] == '7':
            mauza_rows[path].append((idx, gid))
    report['localities held back'] = [f'{k}: {v:,}' for k, v in held.items()]

    # 5. Gates.
    admin_rows = base + added
    for r in admin_rows:
        c = codes.get(r['GeoAreaId'])
        if not c or not c['bbs_code'] or not c['code_status']:
            raise GateError(f"gate 1: GeoAreaId {r['GeoAreaId']} has no code")
    nondup = Counter(c['bbs_code'] for c in codes.values() if c['code_status'] != 'duplicate')
    clash = [k for k, n in nondup.items() if n > 1]
    shared_ok = []
    for k in clash:
        holders = [gid for gid, c in codes.items() if c['bbs_code'] == k and c['code_status'] != 'duplicate']
        raise GateError(f'gate 1: bbs_code {k} used by GeoAreaId {holders}')
    loc_codes = Counter(r['bbs_code'] for r in localities)
    if any(n > 1 for n in loc_codes.values()) or set(loc_codes) & set(nondup):
        raise GateError('gate 1: locality bbs_code not unique')
    code_of_geo = {r['geo_code']: r['GeoAreaId'] for r in admin_rows}
    code_of_geo.update({r['geo_code']: r['GeoAreaId'] for r in localities})
    if len(code_of_geo) != len(admin_rows) + len(localities):
        raise GateError('gate 3: geo_code is not unique across the dataset')
    for r in admin_rows + localities:
        if r['geo_type'] != 'country' and r['parent_geo_code'] not in code_of_geo:
            raise GateError(f"gate 3: GeoAreaId {r['GeoAreaId']} parent {r['parent_geo_code']} missing")
    all_codes = {**{gid: c for gid, c in codes.items()},
                 **{r['GeoAreaId']: dict(bbs_code=r['bbs_code'], code_status=r['code_status']) for r in localities}}
    for r in admin_rows + localities:
        if r['geo_type'] in ('country', 'divison'):
            continue
        c = all_codes[r['GeoAreaId']]
        if c['code_status'] == 'duplicate':
            continue
        anc = all_codes[code_of_geo[r['parent_geo_code']]]
        if c['code_status'] == 'bbs_2017' and anc['code_status'] != 'bbs_2017':
            # An official child under a provisional parent (e.g. a ward of a
            # reorganised thana) must still sit under its nearest official ancestor.
            row = byid[code_of_geo[r['parent_geo_code']]]
            while all_codes[row['GeoAreaId']]['code_status'] != 'bbs_2017':
                row = byid[code_of_geo[row['parent_geo_code']]]
            anc = all_codes[row['GeoAreaId']]
        if anc['code_status'] != 'national' and not c['bbs_code'].startswith(anc['bbs_code'] + '-'):
            raise GateError(f"gate 3: {r['GeoAreaId']} code {c['bbs_code']} not under {anc['bbs_code']}")
    return dict(lines=lines, eol=eol, base=base, added=added, codes=codes, en_fixes=en_fixes,
                localities=localities, report=report, shared=shared_ok)


def write(b):
    out = bytearray(b'\xef\xbb\xbf')
    fixed = 0
    for line, r in zip(b['lines'], b['base']):
        if r['GeoAreaId'] in b['en_fixes']:
            out += csv_line([r[k] if k != 'en_name' else b['en_fixes'][r['GeoAreaId']] for k in BASE_FIELDS],
                            b['eol'])
            fixed += 1
        else:
            out += line + b['eol']
    for r in b['added']:
        out += csv_line([r[k] for k in BASE_FIELDS], b['eol'])
    (DATA / 'geo_areas.csv').write_bytes(bytes(out))

    SEED.mkdir(exist_ok=True)
    with (SEED / 'geo_area_codes.csv').open('w', encoding='utf-8', newline='') as f:
        w = csv.writer(f, lineterminator='\n')
        w.writerow(CODE_FIELDS)
        for gid in sorted(b['codes'], key=int):
            c = b['codes'][gid]
            w.writerow([gid, c['bbs_code'], c['bbs_code_legacy'], c['code_status'], c['code_source'],
                        json.dumps(c['attrs'], ensure_ascii=False, separators=(',', ':'), sort_keys=True)])
    fields = BASE_FIELDS + CODE_FIELDS[1:]
    buf = io.StringIO()
    w = csv.DictWriter(buf, fields, lineterminator='\n', extrasaction='ignore')
    w.writeheader()
    w.writerows(b['localities'])
    # mtime=0 keeps the archive byte-identical between runs.
    with gzip.GzipFile(SEED / 'geo_area_localities.csv.gz', 'wb', mtime=0) as gz:
        gz.write(buf.getvalue().encode('utf-8'))

    files = ['geo_areas.csv', 'seed/geo_area_codes.csv', 'seed/geo_area_localities.csv.gz']
    statuses = Counter(c['code_status'] for c in b['codes'].values()) + Counter(r['code_status'] for r in b['localities'])
    manifest = dict(version=VERSION, source='BBS Bangladesh Geocode district volumes, June 2017 (bbs-geocode-2017/)',
                    rows=dict(admin_existing=len(b['base']), admin_added=len(b['added']),
                              localities=len(b['localities']),
                              by_type=dict(Counter(r['geo_type'] for r in b['base'] + b['added'] + b['localities']))),
                    code_status=dict(statuses), english_fixes=fixed,
                    sha256={f: file_sha256(DATA / f) for f in files})
    (SEED / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

    lines = [f'# Geo seed build report — {VERSION}', '',
             f"Existing areas {len(b['base']):,} · added {len(b['added']):,} · localities {len(b['localities']):,} · "
             f"English names fixed (D2) {fixed}", '', '## Code status', '', '| Status | Rows |', '| --- | ---: |']
    lines += [f'| {k} | {v:,} |' for k, v in sorted(statuses.items())]
    for title, items in b['report'].items():
        lines += ['', f'## {title} ({len(items)})', '']
        lines += [f'- {x}' for x in items[:200]]
        if len(items) > 200:
            lines.append(f'- … {len(items) - 200} more')
    lines += ['', 'All six build gates passed.', '']
    (SEED / 'BUILD_REPORT.md').write_text('\n'.join(lines), encoding='utf-8')
    return manifest


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--check', action='store_true', help='run the gates without writing')
    args = ap.parse_args()
    try:
        b = build()
    except GateError as e:
        sys.exit(f'Build refused: {e}')
    if args.check:
        print('All gates pass.')
        return
    print(json.dumps(write(b), ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
