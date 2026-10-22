#!/usr/bin/env python3
"""Link every boundary polygon to a GovStore area (GeoAreaId) by code, never by name.

Python 3.10+, standard library only. Implements docs/plans/geo-boundary-data-plan.md §6 and §7 step 2.
Run tools/fetch_boundary_sources.py first.

Inputs:
  <cache>/gb_adm4_2015/geoBoundaries-BGD-ADM4_simplified.geojson   2015 union polygons (names only)
  <cache>/codab_v03/bgd_adminpoints.geojson   level-4 points carry the 2015 P-codes
  <cache>/codab_v03/bgd_admin3.geojson        current upazilas / city corporations (parent fallback, evidence)
  src/database/data/geo_areas.csv, seed/geo_area_codes.csv
  boundaries/upazila_recode.csv               reviewed: old 2011 upazila code -> current upazila
  boundaries/review/boundary_decisions.csv    reviewed: one row per polygon no code rule resolves

Outputs (under src/database/data/boundaries/):
  crosswalk.csv                       one row per source polygon
  upazila_recode.csv                  drafts appended, confirmed rows kept
  review/boundary_decisions.csv       drafts appended, confirmed rows kept
  review/CROSSWALK_REPORT.md          counts, open items and the name QA list (names appear only here)

Every 2015 polygon gets its P-code from the one adminpoints point it contains; the run stops unless
that join is exactly one-to-one. --check runs everything without writing.
"""

import argparse
import csv
import difflib
import io
import json
import re
import sys
import tempfile
from collections import Counter, defaultdict
from pathlib import Path

DATA = Path(__file__).resolve().parents[1] / 'src/database/data'
OUT = DATA / 'boundaries'
REVIEW = OUT / 'review'
DEFAULT_CACHE = Path(tempfile.gettempdir()) / 'gov-store-boundaries'
BASE_FIELDS = ['GeoAreaId', 'hid', 'geo_type', 'parent_geo_code', 'geo_code', 'bn_name', 'domain', 'en_name', 'GeoLevel']
XW_FIELDS = ['level', 'source', 'source_id', 'source_pcode', 'GeoAreaId', 'parent_GeoAreaId', 'method', 'status',
             'note', 'reviewed_by', 'reviewed_on']
RECODE_FIELDS = ['old_upazila_code', 'old_name', 'new_GeoAreaId', 'new_name', 'evidence', 'confirmed',
                 'note', 'reviewed_by', 'reviewed_on']
DECISION_FIELDS = ['source', 'source_id', 'source_pcode', 'source_name', 'reason', 'parent_suggested',
                   'draft_decision', 'draft_target', 'draft_basis', 'decision', 'target', 'parent', 'note',
                   'reviewed_by', 'reviewed_on']
DECISIONS = {'link', 'no_govstore_area', 'held'}
LEVEL3 = ('upazilla', 'city')


class CrosswalkError(Exception):
    pass


def read_csv(path):
    if not path.exists():
        return []
    with path.open(encoding='utf-8-sig', newline='') as f:
        return list(csv.DictReader(f))


def write_csv(path, fields, rows):
    buf = io.StringIO()
    w = csv.DictWriter(buf, fields, lineterminator='\n', extrasaction='ignore')
    w.writeheader()
    w.writerows(rows)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(buf.getvalue(), encoding='utf-8')


# --- geometry (lon/lat, ray casting) --------------------------------------------

def polygons(geometry):
    """GeoJSON Polygon/MultiPolygon -> list of (bbox, rings)."""
    parts = [geometry['coordinates']] if geometry['type'] == 'Polygon' else geometry['coordinates']
    out = []
    for rings in parts:
        xs = [p[0] for p in rings[0]]
        ys = [p[1] for p in rings[0]]
        out.append(((min(xs), min(ys), max(xs), max(ys)), rings))
    return out


def in_rings(x, y, rings):
    inside = False
    for ring in rings:  # holes flip the parity back
        j = len(ring) - 1
        for i in range(len(ring)):
            xi, yi = ring[i][0], ring[i][1]
            xj, yj = ring[j][0], ring[j][1]
            if (yi > y) != (yj > y) and x < (xj - xi) * (y - yi) / (yj - yi) + xi:
                inside = not inside
            j = i
    return inside


def contains(parts, x, y):
    return any(b[0] <= x <= b[2] and b[1] <= y <= b[3] and in_rings(x, y, rings) for b, rings in parts)


def load_features(path):
    return json.loads(path.read_text(encoding='utf-8'))['features']


# --- GovStore ---------------------------------------------------------------------

def load_govstore():
    with (DATA / 'geo_areas.csv').open(encoding='utf-8-sig', newline='') as f:
        rows = [dict(zip(BASE_FIELDS, r)) for r in csv.reader(f)]
    codes = {r['GeoAreaId']: r for r in read_csv(DATA / 'seed/geo_area_codes.csv')}
    by_geo_code = {r['geo_code']: r['GeoAreaId'] for r in rows}
    areas = {}
    for r in rows:
        c = codes[r['GeoAreaId']]
        areas[r['GeoAreaId']] = dict(
            id=r['GeoAreaId'], type='division' if r['geo_type'] == 'divison' else r['geo_type'],
            parent=by_geo_code.get(r['parent_geo_code']), en=r['en_name'], bn=r['bn_name'],
            code=c['bbs_code'], legacy=c['bbs_code_legacy'], status=c['code_status'],
            bbs_en=json.loads(c['bbs_attributes'] or '{}').get('bbs_en_name', ''))
    children = defaultdict(list)
    for a in areas.values():
        if a['parent']:
            children[a['parent']].append(a['id'])
    return areas, children


def level3_of(areas, gid):
    while gid and areas[gid]['type'] not in LEVEL3:
        gid = areas[gid]['parent']
    return gid


def unique_index(areas, types, key):
    """key(area) -> GeoAreaId, skipping duplicates and keys two areas share."""
    seen = defaultdict(list)
    for a in areas.values():
        if a['type'] in types and a['status'] != 'duplicate' and a['legacy']:
            k = key(a['legacy'])
            if k:
                seen[k].append(a['id'])
    return {k: v[0] for k, v in seen.items() if len(v) == 1}


def norm(name):
    name = re.sub(r'\b(union|paurashava|pourashava|pourasabha|upazilla|upazila|parishad|no)\b', ' ', name.lower())
    return re.sub(r'[^a-z]', '', name)


def similarity(a, b):
    return difflib.SequenceMatcher(None, norm(a), norm(b)).ratio()


# --- crosswalk ----------------------------------------------------------------------

def build(cache):
    areas, children = load_govstore()
    union_by = unique_index(areas, ('union',), lambda l: l if len(l) == 8 else '')
    ward_by = unique_index(areas, ('thana_ward',), lambda l: l[:4] + l[6:] if len(l) == 10 else '')
    thana_by = unique_index(areas, ('city_thana',), lambda l: l[:4] + l[6:] if len(l) == 8 else '')
    upazila_by = unique_index(areas, ('upazilla',), lambda l: l if len(l) == 6 else '')
    city_by = unique_index(areas, ('city',), lambda l: l if len(l) == 6 else '')

    v03_dir, gb_dir = cache / 'codab_v03', cache / 'gb_adm4_2015'
    for p in (v03_dir / 'bgd_adminpoints.geojson', v03_dir / 'bgd_admin3.geojson',
              gb_dir / 'geoBoundaries-BGD-ADM4_simplified.geojson'):
        if not p.exists():
            raise CrosswalkError(f'{p} is missing; run tools/fetch_boundary_sources.py first.')

    decisions = {(d['source'], d['source_id']): d for d in read_csv(REVIEW / 'boundary_decisions.csv')}
    recodes = read_csv(OUT / 'upazila_recode.csv')
    xw, drafts, report = [], [], defaultdict(list)

    def decided(source, source_id):
        d = decisions.get((source, source_id))
        if d and d['decision']:
            if d['decision'] not in DECISIONS or not d['reviewed_by']:
                raise CrosswalkError(f'review row {source}/{source_id}: decision needs one of {sorted(DECISIONS)} '
                                     'and reviewed_by')
            if d['decision'] == 'link' and d['target'] not in areas:
                raise CrosswalkError(f'review row {source}/{source_id}: target {d["target"]!r} is not a GeoAreaId')
            return d
        return None

    # Level 3: v03 upazilas / city corporations (parent fallback and evidence only).
    v03 = []
    for f in load_features(v03_dir / 'bgd_admin3.geojson'):
        p = f['properties']
        pc = p['adm3_pcode']
        d, dist, city, upa = pc[2:4], pc[4:6], pc[6:8], pc[8:10]
        gid = upazila_by.get(d + dist + upa) if city == '00' else city_by.get(d + dist + city) if upa == '00' else None
        row = dict(level=3, source='codab_v03', source_id=pc, source_pcode=pc, GeoAreaId=gid or '',
                   method='code' if gid else '', status='linked' if gid else '')
        if not gid:
            dec = decided('codab_v03', pc)
            if dec:
                row.update(GeoAreaId=dec['target'] if dec['decision'] == 'link' else '', method='reviewed',
                           status='linked' if dec['decision'] == 'link' else dec['decision'], note=dec['note'],
                           reviewed_by=dec['reviewed_by'], reviewed_on=dec['reviewed_on'])
            else:
                drafts.append(dict(source='codab_v03', source_id=pc, source_pcode=pc, source_name=p['adm3_name'],
                                   reason='v03 code not in GovStore', parent_suggested=''))
        if row['GeoAreaId']:
            row['parent_GeoAreaId'] = areas[row['GeoAreaId']]['parent']
        xw.append(row)
        v03.append((pc, p['adm3_name'], row['GeoAreaId'], polygons(f['geometry'])))
    v03_gids = Counter(r['GeoAreaId'] for r in xw if r['GeoAreaId'])
    if any(n > 1 for n in v03_gids.values()):
        raise CrosswalkError(f'v03 polygons share a GeoAreaId: {[g for g, n in v03_gids.items() if n > 1]}')

    def v03_at(x, y):
        hits = [v for v in v03 if contains(v[3], x, y)]
        return hits[0] if len(hits) == 1 else None

    # Level 4: recover each 2015 polygon's P-code from the single point it contains.
    points = [f for f in load_features(v03_dir / 'bgd_adminpoints.geojson') if f['properties']['admin_level'] == 4]
    shapes = [(f['properties']['shapeID'], f['properties']['shapeName'], polygons(f['geometry']))
              for f in load_features(gb_dir / 'geoBoundaries-BGD-ADM4_simplified.geojson')]
    hits = defaultdict(list)
    for f in points:
        x, y = f['geometry']['coordinates'][:2]
        inside = [i for i, s in enumerate(shapes) if contains(s[2], x, y)]
        if len(inside) != 1:
            raise CrosswalkError(f"point {f['properties']['adm4_pcode']} lies in {len(inside)} polygons")
        hits[inside[0]].append(f)
    if len(hits) != len(shapes) or any(len(h) != 1 for h in hits.values()):
        raise CrosswalkError('P-code recovery is not one-to-one; the sources changed.')

    confirmed_recodes = [r for r in recodes if r['confirmed'] == 'yes']
    recode_targets = defaultdict(list)
    for r in confirmed_recodes:
        if r['new_GeoAreaId'] not in areas or areas[r['new_GeoAreaId']]['type'] != 'upazilla':
            raise CrosswalkError(f"upazila_recode {r['old_upazila_code']}: {r['new_GeoAreaId']} is not an upazila")
        recode_targets[r['old_upazila_code']].append(r['new_GeoAreaId'])

    pending, linked_ids, unresolved = [], set(), []
    for i, (shape_id, shape_name, _) in enumerate(shapes):
        props = hits[i][0]['properties']
        x, y = hits[i][0]['geometry']['coordinates'][:2]
        pc = props['adm4_pcode']
        c = pc[2:]
        if similarity(shape_name, props['adm4_name']) < 0.8:
            report['P-code recovery: polygon and point names differ (QA only)'].append(
                f"{shape_id} {shape_name} ↔ {pc} {props['adm4_name']}")
        here = v03_at(x, y)
        # Parent when no GovStore area owns the polygon: the v03 area holding its point, else its 2015 upazila.
        fallback = here[2] if here and here[2] else upazila_by.get(c[:6], '')
        row = dict(level=4, source='gb_adm4_2015', source_id=shape_id, source_pcode=pc, _name=props['adm4_name'],
                   _v03=here, _fallback=fallback)
        dec = decided('gb_adm4_2015', shape_id)
        if dec:
            gid = dec['target'] if dec['decision'] == 'link' else ''
            parent = level3_of(areas, gid) if gid else dec['parent'] or fallback
            if parent not in areas or areas[parent]['type'] not in LEVEL3:
                raise CrosswalkError(f'review row gb_adm4_2015/{shape_id}: parent {parent!r} is not an upazila or city')
            row.update(GeoAreaId=gid, method='reviewed', status='linked' if gid else dec['decision'],
                       note=dec['note'], reviewed_by=dec['reviewed_by'], reviewed_on=dec['reviewed_on'],
                       parent_GeoAreaId=parent)
        elif c in union_by:
            row.update(GeoAreaId=union_by[c], method='code', status='linked')
        elif c in ward_by:
            row.update(GeoAreaId=ward_by[c], method='code', status='linked', note='city ward (drawn in its city, D3)')
        elif c[:6] in thana_by:
            thana = thana_by[c[:6]]
            row.update(method='city_ward', status='held', parent_GeoAreaId=level3_of(areas, thana),
                       note=f'2015 polygon in city thana {thana} with no GovStore ward of that number (renumbered '
                            'ward or absorbed union); drawn in its city, wards deferred (D3)')
        else:
            row['_old'] = c[:6]
            pending.append(row)
        xw.append(row)
        if row.get('GeoAreaId'):
            linked_ids.add(row['GeoAreaId'])

    # Code conflicts: the 2015 code and the GovStore legacy code agree, but the name clearly belongs to another
    # area under the same upazila (2011 vs 2017 union numbering swaps). These go to review instead of linking.
    for row in xw:
        if row['level'] != 4 or row.get('method') != 'code' or areas[row['GeoAreaId']]['type'] != 'union':
            continue
        a = areas[row['GeoAreaId']]
        if max(similarity(row['_name'], a['en']), similarity(row['_name'], a['bbs_en'])) >= 0.5:
            continue
        alts = [k for k in children[a['parent']] if k != a['id'] and areas[k]['type'] in ('union', 'pourasabha')
                and areas[k]['status'] != 'duplicate'
                and max(similarity(row['_name'], areas[k]['en']), similarity(row['_name'], areas[k]['bbs_en'])) >= 0.8]
        if alts:
            linked_ids.discard(a['id'])
            row.update(GeoAreaId='', method='', status='', _old=row['source_pcode'][2:8], _candidates=alts,
                       _reason=f"code {row['source_pcode']} points to {a['id']} {a['en']} but the name matches "
                               + ', '.join(f"{k} {areas[k]['en']}" for k in alts))
            pending.append(row)

    # Urban blocks (union number 99, or 00 for a second paurashava): the paurashava of their upazila.
    blocks = defaultdict(list)
    for row in pending:
        if row['source_pcode'][-2:] in ('99', '00') and row['_old'] in upazila_by:
            blocks[upazila_by[row['_old']]].append(row)
    for upa, rows in blocks.items():
        pouras = [k for k in children[upa] if areas[k]['type'] == 'pourasabha' and areas[k]['status'] != 'duplicate'
                  and k not in linked_ids]
        if len(rows) == 1 and len(pouras) == 1:
            rows[0].update(GeoAreaId=pouras[0], method='urban_block', status='linked')
            linked_ids.add(pouras[0])
        else:
            for r in rows:
                r['_candidates'] = pouras
                r['_reason'] = f'urban block: {len(rows)} block(s), {len(pouras)} paurashava(s) in upazila {upa}'

    # Upazila recode: same district, same 2-digit union number, under the reviewed new upazila.
    for row in pending:
        if row.get('GeoAreaId') or row['_old'] not in recode_targets:
            continue
        c = row['source_pcode'][2:]
        cands = [k for new in recode_targets[row['_old']] for k in children[new]
                 if areas[k]['type'] == 'union' and areas[k]['status'] != 'duplicate' and k not in linked_ids
                 and areas[k]['legacy'][:4] == c[:4] and areas[k]['legacy'][-2:] == c[-2:]]
        if len(cands) == 1:
            new = level3_of(areas, cands[0])
            ok = row['_v03'] and row['_v03'][2] == new
            if ok:
                row.update(GeoAreaId=cands[0], method='upazila_recode', status='linked')
                linked_ids.add(cands[0])
            else:
                row['_reason'] = (f'upazila recode candidate {cands[0]} fails the containment check '
                                  f'(point in v03 {row["_v03"][0] if row["_v03"] else "none"})')

    # Everything else needs a review row; parent = the v03 area containing the point.
    for row in pending:
        if row.get('GeoAreaId'):
            continue
        parent = row['_fallback']
        row.update(method='', status='', parent_GeoAreaId=parent)
        unresolved.append(row)
        cands = row.get('_candidates')
        if cands is None:
            cands = [k for k in children.get(parent, [])
                     if areas[k]['type'] in ('union', 'pourasabha') and areas[k]['status'] != 'duplicate'
                     and k not in linked_ids]
        scored = sorted(((similarity(row['_name'], areas[k]['en']), k) for k in cands), reverse=True)
        draft = dict(source='gb_adm4_2015', source_id=row['source_id'], source_pcode=row['source_pcode'],
                     source_name=row['_name'], reason=row.get('_reason', 'no code rule applies'),
                     parent_suggested=f"{parent} {areas[parent]['en']}" if parent else '')
        if scored and scored[0][0] >= 0.75 and (len(scored) == 1 or scored[0][0] - scored[1][0] >= 0.15):
            k = scored[0][1]
            draft.update(draft_decision='link', draft_target=k,
                         draft_basis=f"name {areas[k]['en']!r} {scored[0][0]:.2f}; unlinked {areas[k]['type']} "
                                     f"under {parent}; point in v03 {row['_v03'][0] if row['_v03'] else '-'}")
        else:
            draft.update(draft_decision='no_govstore_area',
                         draft_basis='no unlinked GovStore area with a close name under the parent; candidates: '
                                     + '; '.join(f"{k} {areas[k]['en']} {s:.2f}" for s, k in scored[:3]))
        drafts.append(draft)

    # Parents for linked polygons come from the GovStore tree.
    for row in xw:
        if row.get('GeoAreaId') and not row.get('parent_GeoAreaId'):
            row['parent_GeoAreaId'] = (level3_of(areas, row['GeoAreaId']) if row['level'] == 4
                                       else areas[row['GeoAreaId']]['parent'])

    # Recode drafts: old upazila codes whose unresolved unions now sit in another GovStore upazila.
    known = {(r['old_upazila_code'], r['new_GeoAreaId']) for r in recodes}
    moved = Counter((r['_old'], r['_v03'][2]) for r in unresolved
                    if r['source_pcode'][-2:] not in ('99', '00') and r['_v03'] and r['_v03'][2]
                    and r['_v03'][2] != upazila_by.get(r['_old']) and areas[r['_v03'][2]]['type'] == 'upazilla')
    old_names = {r['_old']: r['_v03'] for r in pending}
    for (old, new), n in sorted(moved.items()):
        if (old, new) in known:
            continue
        old_gid = upazila_by.get(old)
        recodes.append(dict(old_upazila_code=old, old_name=areas[old_gid]['en'] if old_gid else '',
                            new_GeoAreaId=new, new_name=areas[new]['en'],
                            evidence=f'{n} unresolved 2015 union point(s) of {old} lie inside the v03 polygon of {new}',
                            confirmed=''))
        known.add((old, new))

    # Gates.
    per_level = defaultdict(Counter)
    for row in xw:
        if row.get('GeoAreaId') and row['status'] == 'linked' and row['method'] != 'reviewed':
            per_level[row['level']][row['GeoAreaId']] += 1
    doubles = [g for lvl in per_level.values() for g, n in lvl.items() if n > 1]
    if doubles:
        raise CrosswalkError(f'code rules linked one GeoAreaId to several polygons: {doubles[:10]}')
    for row in xw:
        if row['level'] == 4 and not row.get('parent_GeoAreaId') and row['status'] != '':
            raise CrosswalkError(f"polygon {row['source_id']} has no parent area")

    # Report.
    for row in xw:
        if row['level'] == 4 and row.get('method') in ('code', 'upazila_recode') and row.get('GeoAreaId'):
            a = areas[row['GeoAreaId']]
            if max(similarity(row['_name'], a['en']), similarity(row['_name'], a['bbs_en'])) < 0.6:
                report['QA: linked by code but the names differ (check the code, not the name)'].append(
                    f"{row['source_pcode']} {row['_name']} → {a['id']} {a['en']} ({a['type']}, {row['method']})")
    for row in xw:
        if row['level'] == 4 and row.get('status') == 'held' and row.get('method') == 'city_ward':
            report['City ward polygons held (D3)'].append(f"{row['source_pcode']} {row['_name']} → city "
                                                          f"{row['parent_GeoAreaId']}")
    drawn = {r['GeoAreaId'] for r in xw if r['level'] == 4 and r.get('GeoAreaId')}
    for a in sorted(areas.values(), key=lambda a: int(a['id'])):
        if a['type'] in ('union', 'pourasabha') and a['status'] != 'duplicate' and a['id'] not in drawn:
            report['GovStore unions / paurashavas without a polygon (table only)'].append(
                f"{a['id']} {a['en']} ({a['type']}, {a['status']}, {a['code']})")

    # Confirmed rows are kept; unconfirmed drafts are rebuilt each run, so stale ones disappear.
    by_dec = {k: d for k, d in decisions.items() if d['decision']}
    for d in drafts:
        by_dec.setdefault((d['source'], d['source_id']), d)
    return dict(xw=xw, decisions=list(by_dec.values()), recodes=recodes, report=report, areas=areas,
                open=[d for d in by_dec.values() if not d.get('decision')],
                open_recodes=[r for r in recodes if r['confirmed'] not in ('yes', 'no')])


def write(b):
    key = lambda r: (r['source'], int(r['level']), r['source_pcode'], r['source_id'])
    write_csv(OUT / 'crosswalk.csv', XW_FIELDS, sorted(b['xw'], key=key))
    write_csv(OUT / 'upazila_recode.csv', RECODE_FIELDS,
              sorted(b['recodes'], key=lambda r: (r['old_upazila_code'], r['new_GeoAreaId'])))
    write_csv(REVIEW / 'boundary_decisions.csv', DECISION_FIELDS,
              sorted(b['decisions'], key=lambda d: (d['source'], d['source_pcode'], d['source_id'])))
    status = Counter((r['level'], r['source'], r['method'] or '-', r['status'] or 'open') for r in b['xw'])
    lines = ['# Boundary crosswalk report', '', 'Generated by `tools/build_boundary_crosswalk.py`. Names appear only '
             'in this report; every link is by code, recode table or a reviewed decision.', '',
             '| Level | Source | Method | Status | Polygons |', '| ---: | --- | --- | --- | ---: |']
    lines += [f'| {k[0]} | {k[1]} | {k[2]} | {k[3]} | {v:,} |' for k, v in sorted(status.items())]
    lines += ['', f"Open review rows: {len(b['open'])} · unconfirmed recode rows: {len(b['open_recodes'])}"]
    for title, items in b['report'].items():
        lines += ['', f'## {title} ({len(items)})', '']
        lines += [f'- {x}' for x in items]
    (REVIEW / 'CROSSWALK_REPORT.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
    return status


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--cache', type=Path, default=DEFAULT_CACHE, help=f'source cache (default {DEFAULT_CACHE})')
    ap.add_argument('--check', action='store_true', help='run without writing')
    args = ap.parse_args()
    try:
        b = build(args.cache)
    except CrosswalkError as e:
        sys.exit(f'Crosswalk refused: {e}')
    if not args.check:
        status = write(b)
        for k, v in sorted(status.items()):
            print(k, v)
    print(f"open review rows {len(b['open'])}, unconfirmed recode rows {len(b['open_recodes'])}")


if __name__ == '__main__':
    main()
