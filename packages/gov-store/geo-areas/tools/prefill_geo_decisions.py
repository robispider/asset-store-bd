#!/usr/bin/env python3
"""Draft review decisions for every area the BBS crosswalk left unlinked.

Python 3.10+, standard library only. Reads geo_areas.base.csv, the BBS admin dataset
and the crosswalk outputs. Writes (or refreshes)
``src/database/data/bbs-geocode-2017/review/decisions.csv``.

Each row gets a *draft* (``draft_decision``, ``draft_target``, ``draft_basis``).
The confirmed columns (``decision``, ``target``, ``note``, ``reviewed_by``,
``reviewed_on``) are never filled by this tool, and existing confirmations are
kept on rerun. ``build_geo_seed.py`` only acts on confirmed decisions.

Decisions (see docs/plans/bbs-geocode-adoption-plan.md §3):

current side  link        same unit as BBS ``target`` (bbs_path) in the same parent
              moved       same unit as BBS ``target``, now under a different parent:
                          provisional code under the current parent
              newer       kept; no 2017 counterpart; provisional code
              fix_parent  ``target`` = GeoAreaId of the intended parent
              duplicate   ``target`` = GeoAreaId of the row it duplicates; not seeded again
              national    the country row
bbs side      linked      resolved by a current-side link/moved decision
              superseded  replaced by current GeoAreaId(s) in ``target`` (';'-separated)
              add         missing from GovStore: seeded as a new area
"""

import csv
import difflib
import importlib.util
import json
import re
import sys
from collections import defaultdict
from pathlib import Path

sys.dont_write_bytecode = True
TOOLS = Path(__file__).resolve().parent
DATA = TOOLS.parent / 'src/database/data'
BBS = DATA / 'bbs-geocode-2017'
OUT = BBS / 'review/decisions.csv'
_spec = importlib.util.spec_from_file_location('xw', TOOLS / 'crosswalk_bbs_geocode_2017.py')
xw = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(xw)

FIELDS = ['side', 'key', 'level', 'name_en', 'name_bn', 'parent', 'reason', 'draft_decision', 'draft_target',
          'draft_basis', 'decision', 'target', 'note', 'reviewed_by', 'reviewed_on']
CONFIRMED = ['decision', 'target', 'note', 'reviewed_by', 'reviewed_on']
KIND_OF_LEVEL = {'upazila_thana': 'upazila_thana', 'paurashava': 'municipality', 'city_corporation': 'city'}


def read(path):
    with path.open(encoding='utf-8-sig', newline='') as f:
        return list(csv.DictReader(f))


def bbs_kind(b):
    if b['bbs_level'] == 'union_ward':
        return 'ward' if (b['bbs_city_corporation_code'] or b['bbs_paurashava_code']) else 'union'
    return KIND_OF_LEVEL.get(b['bbs_level'], b['bbs_level'])


def score(c_en, c_bn, b_en, b_bn, kind):
    en = difflib.SequenceMatcher(None, xw.skeleton(c_en, kind), xw.skeleton(b_en, kind)).ratio()
    bn = difflib.SequenceMatcher(None, xw.bn_key(c_bn), xw.bn_key(b_bn)).ratio() if c_bn and b_bn else 0
    return max(en, bn)


def keys(name, bn, kind):
    return (xw.alias_keys(name, kind) | {xw.skeleton(name, kind)} | ({xw.bn_key(bn)} if bn else set())) - {''}


def main():
    with (DATA / 'geo_areas.base.csv').open(encoding='utf-8-sig', newline='') as f:
        base = {r[0]: dict(zip(xw.BASE_FIELDS, r)) for r in csv.reader(f)}
    bycode = {r['geo_code']: r for r in base.values()}
    matched = {r['GeoAreaId']: r for r in read(BBS / 'crosswalk/matched.csv')}
    cur = read(BBS / 'crosswalk/govstore_unmatched.csv')
    bbs = read(BBS / 'crosswalk/bbs_unmatched.csv')
    path_owner = defaultdict(list)
    for gid, r in matched.items():
        path_owner[r['bbs_path']].append(gid)

    def district_of(row):
        while row and row['geo_type'] not in ('district', 'divison', 'country'):
            row = bycode.get(row['parent_geo_code'])
        return row['GeoAreaId'] if row and row['geo_type'] == 'district' else None

    def bbs_parent_owner(b):
        """GeoAreaId linked to the BBS area directly above ``b``."""
        parts = b['bbs_path'].split('-')
        for n in range(len(parts) - 1, 1, -1):
            owners = path_owner.get('-'.join(parts[:n]).rstrip('-_'), [])
            if owners:
                return owners[0]
        return None

    zila_owner = {r['bbs_path']: gid for gid, r in matched.items() if r['geo_type'] == 'district'}
    bbs_by_district = defaultdict(list)
    for b in bbs:
        gid = zila_owner.get('-'.join(b['bbs_path'].split('-')[:2]))
        if gid:
            bbs_by_district[gid].append(b)

    drafts, used_bbs = [], {}
    for r in cur:
        gid, t = r['GeoAreaId'], r['geo_type']
        kind = xw.KIND.get(t, '')
        parent = bycode.get(r['parent_geo_code'])
        d = dict(side='current', key=gid, level=t, name_en=r['en_name'], name_bn=r['bn_name'],
                 parent=parent['en_name'] if parent else r['parent_geo_code'], reason=r['reason'])
        if t == 'country':
            d.update(draft_decision='national', draft_basis='country row')
        elif r['reason'] == 'missing_current_parent':
            d.update(draft_decision='fix_parent', draft_basis=f"parent geo_code {r['parent_geo_code']} does not exist")
        else:
            pool = [b for b in bbs_by_district.get(district_of(parent), []) if bbs_kind(b) == kind]
            mine = keys(r['en_name'], r['bn_name'], kind)
            same_parent = [b for b in pool if parent and bbs_parent_owner(b) == parent['GeoAreaId']]
            exact = [b for b in pool if mine & keys(b['bbs_en_name'], b['bbs_bn_name'], kind)]
            best = sorted(((score(r['en_name'], r['bn_name'], b['bbs_en_name'], b['bbs_bn_name'], kind), b)
                           for b in same_parent), key=lambda x: -x[0])
            if len(exact) == 1 and exact[0] not in same_parent:
                b = exact[0]
                d.update(draft_decision='moved', draft_target=b['bbs_path'],
                         draft_basis=f"same name {b['bbs_en_name']} / {b['bbs_bn_name']} under another BBS parent")
            elif best and best[0][0] >= 0.72 and (len(best) == 1 or best[0][0] - best[1][0] >= 0.08):
                s, b = best[0]
                d.update(draft_decision='link', draft_target=b['bbs_path'],
                         draft_basis=f"{s:.2f} similar: {b['bbs_en_name']} / {b['bbs_bn_name']}")
            else:
                hint = f"; nearest {best[0][1]['bbs_en_name']} {best[0][0]:.2f}" if best else ''
                d.update(draft_decision='newer', draft_basis=f"no 2017 counterpart in parent{hint}")
        if d.get('draft_target'):
            used_bbs.setdefault(d['draft_target'], []).append(gid)
        drafts.append(d)

    # Splits: a BBS area whose stem appears in two or more unlinked current
    # siblings ('ULANIA' -> North/South Ulania) is superseded by them.
    cur_by_parent = defaultdict(list)
    for r in cur:
        p = bycode.get(r['parent_geo_code'])
        if p:
            cur_by_parent[p['GeoAreaId']].append(r)
    for b in bbs:
        kind = bbs_kind(b)
        d = dict(side='bbs', key=b['bbs_path'], level=b['bbs_level'], name_en=b['bbs_en_name'],
                 name_bn=b['bbs_bn_name'], parent=b['bbs_path'].rsplit('-', 1)[0], reason=b['reason'])
        owner = bbs_parent_owner(b)
        stem = xw.skeleton(b['bbs_en_name'], kind).split('|')[-1]
        heirs = [r['GeoAreaId'] for r in cur_by_parent.get(owner, [])
                 if stem and len(stem) >= 3 and stem in xw.skeleton(r['en_name'], kind).split('|')[-1]
                 and b['bbs_path'] not in used_bbs]
        if b['bbs_path'] in used_bbs:
            d.update(draft_decision='linked', draft_target=';'.join(used_bbs[b['bbs_path']]),
                     draft_basis='target of a current-side draft')
        elif len(heirs) >= 2:
            d.update(draft_decision='superseded', draft_target=';'.join(heirs),
                     draft_basis='split into current areas sharing its name')
        elif not b['bbs_en_name']:
            d.update(draft_decision='superseded', draft_basis='unnamed 2017 thana; its wards link city-wide')
        else:
            d.update(draft_decision='add', draft_basis='no current counterpart' + (
                '' if owner else '; parent not linked yet'))
        drafts.append(d)

    previous = {(r['side'], r['key']): r for r in read(OUT)} if OUT.exists() else {}
    for d in drafts:
        old = previous.get((d['side'], d['key']), {})
        for c in CONFIRMED:
            d[c] = old.get(c, '')
        for c in FIELDS:
            d.setdefault(c, '')
    OUT.parent.mkdir(parents=True, exist_ok=True)
    with OUT.open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, FIELDS)
        w.writeheader()
        w.writerows(sorted(drafts, key=lambda d: (d['side'] != 'current', d['draft_decision'], d['parent'], d['key'])))
    counts = defaultdict(int)
    for d in drafts:
        counts[(d['side'], d['draft_decision'], bool(d['decision']))] += 1
    print(json.dumps({f'{s}:{dd}:{"confirmed" if c else "draft"}': n for (s, dd, c), n in sorted(counts.items())},
                     indent=2))


if __name__ == '__main__':
    main()
