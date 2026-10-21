#!/usr/bin/env python3
"""Crosswalk the hand-maintained geo_areas.base.csv to the BBS June 2017 geocode dataset.

Python 3.10+, standard library only. Offline: never writes the source files or
connects to a database. Writes review files to
``src/database/data/bbs-geocode-2017/crosswalk/``:

- ``matched.csv``             current areas linked to one BBS area, with the tier used
- ``govstore_unmatched.csv``  current areas with no accepted BBS link, with reasons
- ``bbs_unmatched.csv``       BBS admin areas not linked to any current area
- ``summary.json`` / ``README.md``

Matching follows the rules of ``reconcile_bbs_geocodes.py``: a record is only
compared inside its already matched parent, and a link needs a key that is
unique on both sides of that parent. Tiers are tried in order and recorded:

1. ``exact``     normalized English name
2. ``alias``     reviewed spelling aliases (SPELLING_ALIASES), parenthetical
                 alternatives such as 'SADAR (KOTWALI)', and 'Sadar' prefixed
                 with the matched district name
3. ``bangla``    repaired BBS Bangla name equals the current Bangla name
4. ``skeleton``  English consonant skeleton (e.g. Kaderpur / KEDARPUR); the
                 weakest name tier, kept separate so it can be reviewed or excluded
5. ``ward_set``  city thanas only: unnamed BBS thanas (code known only from
                 ward rows) linked by mutually best ward-number overlap

Review candidates are spelling scores only and are never assigned. Mauza and
village rows have no current counterpart and are not crosswalked.
"""

import argparse
import csv
import difflib
import hashlib
import importlib.util
import json
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path

sys.dont_write_bytecode = True  # importing the sibling tool must not leave __pycache__ behind
TOOLS = Path(__file__).resolve().parent
DATA = TOOLS.parent / 'src/database/data'
_spec = importlib.util.spec_from_file_location('reconcile_bbs_geocodes', TOOLS / 'reconcile_bbs_geocodes.py')
_reconcile = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_reconcile)
normalize = _reconcile.normalize
BASE_FIELDS = _reconcile.BASE_FIELDS

DIVISIONS = {'10': 'BARISAL', '20': 'CHITTAGONG', '30': 'DHAKA', '40': 'KHULNA', '45': 'MYMENSINGH',
             '50': 'RAJSHAHI', '55': 'RANGPUR', '60': 'SYLHET'}

# Reviewed English spelling differences between the 2017 BBS volumes and the
# current names. Applied as whole-token replacements on normalized names, on
# both sides. The first five are the 2018 official spelling changes.
SPELLING_ALIASES = {
    'chittagong': 'chattogram', 'comilla': 'cumilla', 'barisal': 'barishal', 'jessore': 'jashore',
    'bogra': 'bogura',
    'jhalokati': 'jhalakathi', 'jhalokathi': 'jhalakathi', 'jhalakati': 'jhalakathi',
    'brahmmanbaria': 'brahmanbaria', 'maulvibazar': 'moulvibazar', 'netrokona': 'netrakona',
}

# Reviewed one-to-one links (current geo_code -> BBS path) for upazilas renamed
# or re-spelled since the 2017 volumes, where no name rule can be trusted.
REVIEWED_LINKS = {
    '88': ('20-19-_-67', 'Comilla Adarsha Sadar is now Cumilla Sadar'),
    '91': ('20-19-_-33', 'Comilla Sadar Dakkhin is now Cumilla Sadar Dakshin'),
    '33274': ('10-79-_-90', 'Zianagar upazila renamed Indurkani'),
    '1931': ('55-94-_-86', 'Guchchhagram Ranishankail / Ranisankail'),
    '1959': ('55-49-_-08', 'Rajibpur / Char Rajibpur'),
    '1962': ('30-93-_-19', 'Bhuanpur / Bhuapur spelling'),
    '156': ('55-77-_-90', 'Tentulia / Tetulia spelling'),
    '1133': ('50-10-_-33', 'Dupchachia / Dupchanchia spelling'),
    '108': ('10-42-_-43', 'Kanthalia / Kathalia spelling'),
    '3280': ('20-46-_-61', 'Lakkhichhari / Laxmichhari spelling'),
    '58599': ('20-19-_-33', 'Cumilla city thana Sadar Dakkhin = BBS Comilla Sadar Dakkhin'),
}
REVIEWED_BY_PATH = {path: code for code, (path, _) in REVIEWED_LINKS.items()}

# Directional qualifiers: written as a prefix in BBS ('UTTAR GUNAIGHAR') and
# often as a suffix in current names ('Gunaighornorth', 'গুনাইঘর (উত্তর)').
EN_DIRECTIONS = {'uttar': 'N', 'north': 'N', 'dakkhin': 'S', 'dakshin': 'S', 'dakhin': 'S', 'south': 'S',
                 'purba': 'E', 'east': 'E', 'pashchim': 'W', 'paschim': 'W', 'west': 'W'}
BN_DIRECTIONS = {'উত্তর': 'N', 'উ:': 'N', 'উঃ': 'N', 'দক্ষিণ': 'S', 'দক্ষিন': 'S', 'দ:': 'S', 'দঃ': 'S',
                 'পূর্ব': 'E', 'পুর্ব': 'E', 'পূ:': 'E', 'পূঃ': 'E', 'পশ্চিম': 'W', 'প:': 'W', 'পঃ': 'W'}


def canon_direction(key):
    """'gunaighornorth' / 'uttargunaighar' -> 'N|gunaighor' / 'N|gunaighar'."""
    for word, code in EN_DIRECTIONS.items():
        if key.endswith(word) and len(key) > len(word) + 2:
            return f'{code}|{key[:-len(word)]}'
    for word, code in EN_DIRECTIONS.items():
        if key.startswith(word) and len(key) > len(word) + 2:
            return f'{code}|{key[len(word):]}'
    return key


KIND = {'divison': 'division', 'district': 'district', 'upazilla': 'upazila_thana', 'city': 'city',
        'city_thana': 'upazila_thana', 'pourasabha': 'municipality', 'union': 'union',
        'pouro_ward': 'ward', 'thana_ward': 'ward'}
ORDER = ['divison', 'district', 'upazilla', 'city', 'city_thana', 'pourasabha', 'union',
         'pouro_ward', 'thana_ward']
TIERS = ['reviewed', 'exact', 'alias', 'bangla', 'skeleton']
ALL_TIERS = TIERS + ['ward_set', 'city_ward']
BBS_CODES = ['division_code', 'zila_code', 'city_corporation_code', 'upazila_thana_code',
             'paurashava_code', 'union_ward_code_old', 'union_ward_code_new']


# --- keys ---------------------------------------------------------------------

def en_key(name, kind):
    return normalize(name or '', kind)


def alias_keys(name, kind, district_key=''):
    text = unicodedata.normalize('NFKC', name or '').casefold()
    if kind == 'ward':
        # 'MANDA KHAL PAR WARD NO. 72' / 'WARD NO. 72'; first number only.
        m = re.search(r'ward\s*(?:no\.?)?\s*[-.:]?\s*0*(\d+)', text)
        return {'ward' + m.group(1)} if m else set()
    # Union names prefixed with their serial number: '7no Sofapur', '০৩ নং'.
    text = re.sub(r'^\s*\d+\s*no\.?\s*', '', text)
    variants = {text}
    m = re.fullmatch(r'(.*?)\s*\((.*?)\)\s*', text)
    if m and kind == 'upazila_thana':
        variants |= {m.group(1), m.group(2)}
    keys = set()
    for v in variants:
        k = normalize(v, kind)
        if kind == 'union':
            k = strip_union_noise(k)
        for old, new in SPELLING_ALIASES.items():
            k = k.replace(old, new)
        keys.add(canon_direction(k))
        if kind == 'upazila_thana' and k == 'sadar' and district_key:
            keys.add(district_key + 'sadar')
    return keys


BN_SUFFIX = re.compile(r'\s*(জেলা|উপজেলা|ই্?উনিয়ন|ইয়নিয়ন|পৌরসভা|থানা|বিভাগ|সদর)$')
# Spelling variants that are not significant inside one parent area.
BN_VARIANTS = (('ী', 'ি'), ('ূ', 'ু'), ('ঙ্গ', 'ংগ'), ('ঙ্ক', 'ংক'), ('ঁ', ''), ('ক্ষ্ম', 'ক্ষ'), ('ণ', 'ন'),
               ('ড়', 'র'), ('ঢ়', 'ঢ'), ('ৎ', 'ত'))


def strip_union_noise(key):
    """'7nosofapur' / 'narayanpurup10' / 'lamasadar' -> the union's own name."""
    key = re.sub(r'^\d+no', '', key)
    key = re.sub(r'up\d*$', '', key)
    return re.sub(r'(?<=.{3})sadar$', '', key)


def bn_key(name):
    """Bangla comparison key: spacing, serial prefix, type suffix and common
    spelling variants (ী/ি, ূ/ু, ঙ্গ/ংগ, ঙ্ক/ংক) are not significant."""
    text = re.sub(r'^\s*[০-৯0-9]+\s*নং\s*', '', unicodedata.normalize('NFC', name or ''))
    text = re.sub(r'[\s‌‍]+', ' ', text).strip()
    for _ in range(2):  # 'X সদর ইউনিয়ন'
        text = BN_SUFFIX.sub('', text).strip()
    words = '|'.join(re.escape(w) for w in BN_DIRECTIONS)
    m = (re.fullmatch(rf'(.+?)\s*\(?({words})\)?', text) or re.fullmatch(rf'\(?({words})\)?\s*(.+)', text))
    prefix = ''
    if m:
        word, rest = (m.group(2), m.group(1)) if m.re.pattern.startswith('(.+?)') else (m.group(1), m.group(2))
        prefix, text = BN_DIRECTIONS[word] + '|', rest
    text = re.sub(r'[\s.\-()]', '', text)
    for a, b in BN_VARIANTS:
        text = text.replace(a, b)
    return prefix + unicodedata.normalize('NFD', text)


def skeleton(name, kind):
    k = en_key(name, kind)
    if kind == 'ward' or not k:
        return k
    if kind == 'union':
        k = strip_union_noise(k)
    for old, new in SPELLING_ALIASES.items():
        k = k.replace(old, new)
    direction, _, k = canon_direction(re.sub(r'\d', '', k)).rpartition('|')
    head = 'V' if k[:1] in 'aeiou' else ''
    for a, b in (('ph', 'f'), ('z', 'j'), ('v', 'b'), ('q', 'k'), ('x', 'ks'), ('ck', 'k')):
        k = k.replace(a, b)
    k = re.sub(r'[aeiouyhw]', '', k)
    return direction + '|' + head + re.sub(r'(.)\1+', r'\1', k)


# --- BBS model ----------------------------------------------------------------

def load_bbs(path):
    with path.open(encoding='utf-8-sig', newline='') as f:
        rows = list(csv.DictReader(f))
    recs = []
    for i, r in enumerate(rows, 2):
        card = r['card']
        if card == '2':
            kind = 'district'
        elif card == '3':
            kind = 'city'
        elif card == '4':
            kind = 'upazila_thana'
        elif card == '5':
            kind = 'municipality'
        elif card == '6':
            kind = 'ward' if (r['city_corporation_code'] or r['paurashava_code']) else 'union'
        else:
            continue
        recs.append(dict(r, kind=kind, line=i, name=r['en_name'], bn=r['bn_name']))
    for code, name in DIVISIONS.items():
        recs.append(dict(kind='division', line='', name=name, bn='', division_code=code, bbs_path=code,
                         level='division', source_file='(division code)', page='',
                         **{c: '' for c in BBS_CODES[1:]}, en_name=name, bn_name=''))
    return recs


class BbsIndex:
    def __init__(self, recs):
        self.by = defaultdict(list)
        for r in recs:
            d, z, c, u, p = (r[k] for k in BBS_CODES[:5])
            k = r['kind']
            if k == 'division':
                self.by[('division',)].append(r)
            elif k == 'district':
                self.by[('district', d)].append(r)
            elif k == 'city':
                self.by[('city', d, z)].append(r)
            elif k == 'upazila_thana':
                self.by[('upazila', d, z)].append(r) if not c else self.by[('city_thana', d, z, c)].append(r)
            elif k == 'municipality':
                self.by[('pourasabha', d, z, u)].append(r)
            elif k == 'union':
                self.by[('union', d, z, u)].append(r)
            elif k == 'ward' and c:
                self.by[('thana_ward', d, z, c, u)].append(r)
                self.by[('city_thana_codes', d, z, c)].append(u)
            elif k == 'ward':
                self.by[('pouro_ward', d, z, u, p)].append(r)
        # Some volumes (e.g. Dhaka) never name their metropolitan thanas: the
        # thana code only appears on ward rows. Represent each as an unnamed
        # thana so it can be matched by its wards (tier 'ward_set').
        self.synthetic = []
        for key in [k for k in self.by if k[0] == 'city_thana_codes']:
            _, d, z, c = key
            named = {r['upazila_thana_code'] for r in self.by.get(('city_thana', d, z, c), [])}
            named |= {r['upazila_thana_code'] for r in self.by.get(('upazila', d, z), [])}
            for u in sorted(set(self.by[key]) - named):
                r = dict(kind='upazila_thana', level='upazila_thana', line='', name='', bn='', en_name='',
                         bn_name='', division_code=d, zila_code=z, city_corporation_code=c,
                         upazila_thana_code=u, paurashava_code='', union_ward_code_old='',
                         union_ward_code_new='', bbs_path=f'{d}-{z}-{c}-{u}', page='',
                         source_file='(unnamed thana: code from ward rows)')
                self.synthetic.append(r)
                self.by[('city_thana', d, z, c)].append(r)

    def city_wards(self, city):
        d, z, c = city['division_code'], city['zila_code'], city['city_corporation_code']
        return [r for k, rows in sorted(self.by.items(), key=lambda kv: str(kv[0]))
                if k[0] == 'thana_ward' and k[1:4] == (d, z, c) for r in rows]

    def wards_of(self, thana):
        t = thana
        return self.by.get(('thana_ward', t['division_code'], t['zila_code'], t['city_corporation_code'],
                            t['upazila_thana_code']), [])

    def pool(self, geo_type, parent, grandparent):
        """BBS candidates for a current record whose parent matched ``parent``."""
        g = lambda *k: self.by.get(k, [])
        if geo_type == 'divison':
            return g('division')
        p = parent
        if geo_type == 'district':
            return g('district', p['division_code'])
        if geo_type in ('upazilla', 'city'):
            return g('upazila' if geo_type == 'upazilla' else 'city', p['division_code'], p['zila_code'])
        if geo_type == 'city_thana':
            d, z, c = p['division_code'], p['zila_code'], p['city_corporation_code']
            ward_thanas = set(g('city_thana_codes', d, z, c))
            listed = g('city_thana', d, z, c)
            listed_codes = {r['upazila_thana_code'] for r in listed}
            return listed + [r for r in g('upazila', d, z)
                             if r['upazila_thana_code'] in ward_thanas - listed_codes]
        if geo_type in ('pourasabha', 'union'):
            return g(geo_type, p['division_code'], p['zila_code'], p['upazila_thana_code'])
        if geo_type == 'pouro_ward':
            return g('pouro_ward', p['division_code'], p['zila_code'], p['upazila_thana_code'], p['paurashava_code'])
        if geo_type == 'thana_ward':
            return g('thana_ward', p['division_code'], p['zila_code'], grandparent['city_corporation_code'],
                     p['upazila_thana_code'])
        return []


def match_by_wards(left, right, current_wards, bbs_wards):
    """Tier 'ward_set': link city thanas whose ward sets are each other's unique
    best match (Jaccard >= 0.5). Used for thanas BBS never names."""
    key = lambda names: {k for n in names for k in alias_keys(n, 'ward')}
    lw = {c['GeoAreaId']: key(current_wards(c)) for c in left}
    rw = {id(b): key(w['name'] for w in bbs_wards(b)) for b in right}
    score = {(c, b): len(lw[c] & rw[b]) / len(lw[c] | rw[b]) for c in lw for b in rw if lw[c] and rw[b]}

    def best(items):
        ranked = sorted(items, key=lambda x: -x[1])
        if ranked and ranked[0][1] >= 0.5 and (len(ranked) == 1 or ranked[0][1] > ranked[1][1]):
            return ranked[0][0]
    out = {}
    by_id = {id(b): b for b in right}
    for c in lw:
        b = best([(b, s) for (cc, b), s in score.items() if cc == c])
        if b is not None and best([(cc, s) for (cc, bb), s in score.items() if bb == b]) == c:
            out[c] = (by_id[b], 'ward_set')
    return out


# --- matching -----------------------------------------------------------------

def keys_for(tier, name, bn, kind, district_key):
    if tier == 'exact':
        return {en_key(name, kind)} - {''}
    if tier == 'alias':
        return alias_keys(name, kind, district_key) - {''}
    if tier == 'bangla':
        return {bn_key(bn)} - {''} if bn else set()
    return {skeleton(name, kind)} - {''}


def match_scope(children, pool, kind, district_key):
    """Unique-key bipartite matching, tier by tier. Returns {GeoAreaId: (bbs, tier)}."""
    out, left, right = {}, list(children), list(pool)
    for tier in TIERS:
        if not left or not right:
            break
        if tier == 'reviewed':
            lk = {id(c): {REVIEWED_LINKS[c['geo_code']][0]} if c['geo_code'] in REVIEWED_LINKS else set() for c in left}
            rk = {id(b): {b['bbs_path']} if b['bbs_path'] in REVIEWED_BY_PATH else set() for b in right}
        else:
            lk = {id(c): keys_for(tier, c['en_name'], c['bn_name'], kind, district_key) for c in left}
            rk = {id(b): keys_for(tier, b['name'], b['bn'], kind, district_key) for b in right}
        lcount, rcount = Counter(k for ks in lk.values() for k in ks), Counter(k for ks in rk.values() for k in ks)
        pairs = {}
        for c in left:
            hits = {id(b) for b in right for k in lk[id(c)] if k in rk[id(b)] and lcount[k] == 1 and rcount[k] == 1}
            if len(hits) == 1:
                pairs[id(c)] = hits.pop()
        taken = Counter(pairs.values())
        by_id = {id(b): b for b in right}
        for c in left:
            b = pairs.get(id(c))
            if b is not None and taken[b] == 1:
                out[c['GeoAreaId']] = (by_id[b], tier)
        matched_b = {id(v[0]) for v in out.values()}
        left = [c for c in left if c['GeoAreaId'] not in out]
        right = [b for b in right if id(b) not in matched_b]
    return out, left, right


def candidates(name, kind, others, key=lambda o: o['name']):
    norm = en_key(name, kind)
    scored = sorted(((difflib.SequenceMatcher(None, norm, en_key(key(o), kind)).ratio(), o) for o in others),
                    key=lambda x: -x[0])
    return scored[:3]


def city_ward_pass(base, bycode, index, match, reason, seen_in_scope):
    """Tier 'city_ward': city wards whose thana was reorganised after 2017 (Sylhet,
    Gazipur, Chattogram, Dhaka metro thanas). The ward itself is unchanged, so it
    is matched by its label across the whole, already matched city corporation."""
    linked = {id(b) for b, _ in match.values()}
    by_city = defaultdict(list)
    for r in base:
        if r['geo_type'] != 'thana_ward' or r['GeoAreaId'] in match:
            continue
        thana = bycode.get(r['parent_geo_code'])
        city = bycode.get(thana['parent_geo_code']) if thana else None
        if city and city['GeoAreaId'] in match:
            by_city[city['GeoAreaId']].append(r)
    for city_id, group in sorted(by_city.items()):
        pool = [b for b in index.city_wards(match[city_id][0]) if id(b) not in linked]
        seen_in_scope.update(id(b) for b in pool)
        got, left, _ = match_scope(group, pool, 'ward', '')
        for rid, (b, _) in got.items():
            match[rid] = (b, 'city_ward')
            reason.pop(rid, None)
        for r in left:
            reason[r['GeoAreaId']] = 'no_counterpart_in_matched_city'


def crosswalk(current_path, bbs_path):
    with current_path.open(encoding='utf-8-sig', newline='') as f:
        base = [dict(zip(BASE_FIELDS, r)) for r in csv.reader(f)]
    bycode = {r['geo_code']: r for r in base}
    recs = load_bbs(bbs_path)
    index = BbsIndex(recs)
    recs += index.synthetic
    children = defaultdict(list)
    for r in base:
        children[(r['geo_type'], r['parent_geo_code'])].append(r)

    match, reason, cands = {}, {}, {}
    seen_in_scope, bbs_cands = set(), {}
    for t in ORDER:
        kind = KIND[t]
        parents = {r['parent_geo_code'] for r in base if r['geo_type'] == t}
        for pcode in sorted(parents):
            group = children[(t, pcode)]
            if t == 'divison':
                parent = grand = None
            else:
                prow = bycode.get(pcode)
                if not prow:
                    for r in group:
                        reason[r['GeoAreaId']] = 'missing_current_parent'
                    continue
                if prow['GeoAreaId'] not in match:
                    for r in group:
                        reason[r['GeoAreaId']] = 'parent_not_matched'
                    continue
                parent = match[prow['GeoAreaId']][0]
                gp = bycode.get(prow['parent_geo_code'])
                grand = match.get(gp['GeoAreaId'], (None,))[0] if gp else None
                if t == 'thana_ward' and grand is None:
                    for r in group:
                        reason[r['GeoAreaId']] = 'parent_not_matched'
                    continue
            pool = index.pool(t, parent, grand)
            seen_in_scope.update(id(b) for b in pool)
            district_key = ''
            if t == 'upazilla' and parent:
                district_key = normalize(parent['name'], 'district')
                for old, new in SPELLING_ALIASES.items():
                    district_key = district_key.replace(old, new)
            got, left, right = match_scope(group, pool, kind, district_key)
            if t == 'city_thana' and left and right:
                extra = match_by_wards(left, right,
                                       lambda c: [w['en_name'] for w in children[('thana_ward', c['geo_code'])]],
                                       index.wards_of)
                got.update(extra)
                left = [c for c in left if c['GeoAreaId'] not in extra]
                taken = {id(v[0]) for v in extra.values()}
                right = [b for b in right if id(b) not in taken]
            for rid, v in got.items():
                match[rid] = v
            for r in left:
                reason[r['GeoAreaId']] = 'no_counterpart_in_matched_parent' if pool else 'no_bbs_areas_under_matched_parent'
                cands[r['GeoAreaId']] = [dict(bbs_path=b['bbs_path'], bbs_name=b['name'], similarity=round(s, 3))
                                         for s, b in candidates(r['en_name'], kind, right)]
            for b in right:
                bbs_cands[id(b)] = [dict(GeoAreaId=c['GeoAreaId'], en_name=c['en_name'], similarity=round(s, 3))
                                    for s, c in candidates(b['name'], kind, left, key=lambda o: o['en_name'])]
    city_ward_pass(base, bycode, index, match, reason, seen_in_scope)
    for r in base:
        if r['geo_type'] == 'country':
            reason[r['GeoAreaId']] = 'country_not_in_bbs'

    # Several current types can legitimately share one BBS area, e.g. a Sadar
    # upazila and the city thana of the same name. Report those, don't drop them.
    users = defaultdict(list)
    for rid, (b, _) in match.items():
        users[id(b)].append(rid)
    return base, recs, match, reason, cands, seen_in_scope, bbs_cands, users


# --- output -------------------------------------------------------------------

MATCH_FIELDS = BASE_FIELDS + ['match_method', 'bbs_level', 'bbs_path'] + ['bbs_' + c for c in BBS_CODES] + [
    'bbs_en_name', 'bbs_bn_name', 'bbs_source', 'notes']
CUR_UNMATCHED = BASE_FIELDS + ['reason', 'review_candidates_json']
BBS_UNMATCHED = ['bbs_level', 'bbs_path'] + ['bbs_' + c for c in BBS_CODES] + [
    'bbs_en_name', 'bbs_bn_name', 'bbs_source', 'reason', 'review_candidates_json']


def bbs_cols(b):
    return dict(bbs_level=b.get('level', b['kind']), bbs_path=b['bbs_path'],
                **{'bbs_' + c: b.get(c, '') for c in BBS_CODES}, bbs_en_name=b['name'], bbs_bn_name=b['bn'],
                bbs_source=f"{b['source_file']}#page={b['page']}" if b.get('page') else b['source_file'])


def write_csv(path, fields, rows):
    with path.open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, fields, extrasaction='raise')
        w.writeheader()
        w.writerows(rows)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--current', type=Path, default=DATA / 'geo_areas.base.csv')
    ap.add_argument('--bbs', type=Path, default=DATA / 'bbs-geocode-2017/bbs_geocode_2017_admin.csv')
    ap.add_argument('--output', type=Path, default=DATA / 'bbs-geocode-2017/crosswalk')
    args = ap.parse_args()

    base, recs, match, reason, cands, seen, bbs_cands, users = crosswalk(args.current, args.bbs)
    byid = {r['GeoAreaId']: r for r in base}
    matched, unmatched = [], []
    for r in base:
        rid = r['GeoAreaId']
        if rid in match:
            b, tier = match[rid]
            shared = [x for x in users[id(b)] if x != rid]
            notes = [REVIEWED_LINKS[r['geo_code']][1]] if tier == 'reviewed' else []
            if shared:
                notes.append('BBS area also linked to GeoAreaId ' + ', '.join(shared))
            note = '; '.join(notes)
            matched.append(dict(r, match_method=tier, notes=note, **bbs_cols(b)))
        else:
            unmatched.append(dict(r, reason=reason.get(rid, 'unsupported_type'),
                                  review_candidates_json=json.dumps(cands.get(rid, []), ensure_ascii=False)))
    linked = {id(b) for b, _ in match.values()}
    bbs_left = []
    for b in recs:
        if id(b) in linked:
            continue
        why = 'no_current_counterpart_in_matched_parent' if id(b) in seen else 'parent_not_linked'
        bbs_left.append(dict(bbs_cols(b), reason=why,
                             review_candidates_json=json.dumps(bbs_cands.get(id(b), []), ensure_ascii=False)))

    args.output.mkdir(parents=True, exist_ok=True)
    write_csv(args.output / 'matched.csv', MATCH_FIELDS, matched)
    write_csv(args.output / 'govstore_unmatched.csv', CUR_UNMATCHED, unmatched)
    write_csv(args.output / 'bbs_unmatched.csv', BBS_UNMATCHED, bbs_left)

    by_type = []
    for t in ORDER + ['country']:
        rows = [r for r in base if r['geo_type'] == t]
        m = [match[r['GeoAreaId']][1] for r in rows if r['GeoAreaId'] in match]
        by_type.append(dict(geo_type=t, total=len(rows), matched=len(m), unmatched=len(rows) - len(m),
                            **{f'by_{tier}': m.count(tier) for tier in ALL_TIERS}))
    bbs_levels = Counter(b.get('level', b['kind']) for b in recs)
    bbs_linked = Counter(b.get('level', b['kind']) for b in recs if id(b) in linked)
    summary = dict(
        current_csv_sha256=hashlib.sha256(args.current.read_bytes()).hexdigest(),
        bbs_csv_sha256=hashlib.sha256(args.bbs.read_bytes()).hexdigest(),
        current_rows=len(base), matched_current_rows=len(matched), unmatched_current_rows=len(unmatched),
        matched_by_tier=dict(Counter(match[r['GeoAreaId']][1] for r in base if r['GeoAreaId'] in match)),
        by_type=by_type,
        bbs_admin_rows=len(recs), bbs_linked_rows=len(linked), bbs_unlinked_rows=len(bbs_left),
        bbs_by_level={k: dict(total=v, linked=bbs_linked[k]) for k, v in bbs_levels.items()},
        current_unmatched_reasons=dict(Counter(u['reason'] for u in unmatched)),
        bbs_unlinked_reasons=dict(Counter(u['reason'] for u in bbs_left)),
        shared_bbs_links=sum(1 for v in users.values() if len(v) > 1),
    )
    (args.output / 'summary.json').write_text(json.dumps(summary, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    write_report(args.output / 'README.md', summary)
    print(json.dumps({k: summary[k] for k in ['current_rows', 'matched_current_rows', 'unmatched_current_rows',
                                              'matched_by_tier', 'bbs_admin_rows', 'bbs_linked_rows']}, indent=2))
    for t in by_type:
        print(f"  {t['geo_type']:<11} {t['matched']:>5}/{t['total']:<5}  " +
              ' '.join(f"{tier}={t['by_' + tier]}" for tier in ALL_TIERS))


def write_report(path, s):
    lines = ['# GovStore geo_areas ↔ BBS geocode (June 2017) crosswalk', '',
             f"Generated by `tools/crosswalk_bbs_geocode_2017.py`. Linked **{s['matched_current_rows']:,} of "
             f"{s['current_rows']:,}** current areas; **{s['unmatched_current_rows']:,}** are unmatched. "
             f"**{s['bbs_unlinked_rows']:,} of {s['bbs_admin_rows']:,}** BBS admin areas have no current counterpart.", '',
             'This is match coverage under the rules below, not measured accuracy. The BBS volumes are dated June 2017, '
             'so areas created or renamed later legitimately appear as unmatched on one side.', '',
             '## Files', '',
             '- `matched.csv` — current rows (nine original fields unchanged) with the linked BBS codes, names and `match_method`.',
             '- `govstore_unmatched.csv` — current rows without a link, with `reason` and up to three review candidates.',
             '- `bbs_unmatched.csv` — BBS zila/city/upazila-thana/paurashava/union-ward rows not linked to any current row.',
             '- `summary.json` — counts and input hashes.', '',
             'Review candidates are spelling similarity only and are never assignments. Import BBS codes as text to keep leading zeros.', '',
             '## Matching tiers', '',
             'Inside an already matched parent, a link needs a key that is unique on both sides:', '',
             '0. `reviewed` — explicit links in `REVIEWED_LINKS` for upazilas renamed or re-spelled since 2017 '
             '(e.g. Zianagar → Indurkani); each carries its reason in `notes`.',
             '1. `exact` — normalized English name (case, spaces, punctuation and type suffixes removed; ward numbers normalized).',
             '2. `alias` — reviewed spelling aliases (Chittagong/Chattogram, Comilla/Cumilla, Barisal/Barishal, Jessore/Jashore, '
             'Bogra/Bogura and a few district spellings), parenthetical alternatives for upazila/thana names, current '
             '`Sadar` = matched district name + SADAR, serial prefixes (`7no Sofapur`), ward number only, and direction '
             'words in any position (north/uttar, south/dakshin/dakkhin, east/purba, west/pashchim). The `bangla` and '
             '`skeleton` keys use the same direction rule.',
             '3. `bangla` — the repaired BBS Bangla name equals the current Bangla name. The repair is best-effort, so this '
             'tier only ever confirms exact equality.',
             '4. `skeleton` — English consonant skeleton (vowels, h/w/y removed; z→j, v→b, ph→f). Weakest tier: review or '
             'exclude it if you need strict links. Children of a skeleton match are matched normally.',
             '5. `ward_set` — city thanas only. Some volumes (Dhaka) never name their metropolitan thanas; the thana code '
             'appears only on ward rows, so `bbs_en_name` is blank. A thana is linked when its ward numbers and the BBS '
             'thana\'s ward numbers are each other\'s unique best overlap (Jaccard ≥ 0.5).',
             '6. `city_ward` — city wards whose thana was reorganised after 2017: matched by ward label across the whole '
             '(matched) city corporation, unique on both sides. The ward keeps its 2017 code; its current thana may differ.', '',
             'City thanas are matched against BBS thanas listed under the city and against upazila/thana rows referenced by '
             'that city\'s wards. One BBS area can be linked from two current types (e.g. a Sadar upazila and the city '
             'thana of the same code); `notes` names the other row.', '',
             '## Coverage by current type', '',
             '| Type | Current | Matched | ' + ' | '.join(ALL_TIERS) + ' | Unmatched |',
             '| --- | ---: | ---: | ' + ' | '.join('---:' for _ in ALL_TIERS) + ' | ---: |']
    for t in s['by_type']:
        lines.append(f"| {t['geo_type']} | {t['total']:,} | {t['matched']:,} | "
                     + ' | '.join(f"{t['by_' + tier]:,}" for tier in ALL_TIERS) + f" | {t['unmatched']:,} |")
    lines += ['', '## BBS admin rows by level', '', '| Level | Rows | Linked |', '| --- | ---: | ---: |']
    lines += [f'| {k} | {v["total"]:,} | {v["linked"]:,} |' for k, v in s['bbs_by_level'].items()]
    lines += ['', '## Unmatched reasons', '', '| Side | Reason | Rows |', '| --- | --- | ---: |']
    lines += [f'| current | {k} | {v:,} |' for k, v in s['current_unmatched_reasons'].items()]
    lines += [f'| BBS | {k} | {v:,} |' for k, v in s['bbs_unlinked_reasons'].items()]
    lines += ['', '`parent_not_matched` / `parent_not_linked` rows were never compared: resolving their parent first can '
              'unlock them.', '',
              '## Reproduce', '', '```text',
              'python packages/gov-store/geo-areas/tools/fetch_bbs_geocode_pdfs.py',
              'python packages/gov-store/geo-areas/tools/extract_bbs_geocode_pdfs.py   # needs PyMuPDF',
              'python packages/gov-store/geo-areas/tools/crosswalk_bbs_geocode_2017.py', '```', '',
              f"- Current CSV SHA-256: `{s['current_csv_sha256']}`",
              f"- BBS admin CSV SHA-256: `{s['bbs_csv_sha256']}`", '']
    path.write_text('\n'.join(lines), encoding='utf-8')


if __name__ == '__main__':
    main()
