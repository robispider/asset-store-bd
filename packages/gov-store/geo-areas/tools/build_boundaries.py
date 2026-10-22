#!/usr/bin/env python3
"""Build the render-ready boundary files from the 2015 union polygons and the reviewed crosswalk.

Python 3.10+, standard library only, plus the pinned Mapshaper in tools/package.json (`npm install`
in tools/ first). Implements docs/plans/geo-boundary-data-plan.md §7 steps 3-4 and §8.

Every level is dissolved from the one 2015 union layer, grouped through the GovStore tree, so children
nest in their parents exactly and share their borders (plan P0.3: COD-AB v03 upazila borders drift
from the 2015 unions by a median 6.8%, so v03 polygons are not used).

Inputs:
  <cache>/gb_adm4_2015/geoBoundaries-BGD-ADM4.geojson   full-resolution 2015 polygons
  src/database/data/boundaries/crosswalk.csv, sources.json, geo_areas.csv, seed/geo_area_codes.csv

Outputs, under src/database/data/boundaries/<VERSION>/:
  level-1.json … level-4.json   WGS84 TopoJSON, one object per level, feature id = GeoAreaId
  children/<GeoAreaId>.json     {version, id, viewBox, outline, areas:[{id, kind, path}]} in the national frame
  manifest.json, BUILD_REPORT.md

Nothing is written unless every gate passes. The whole build runs twice and the two results must be
byte-identical (gate 6); --once skips the second run while tuning.
"""

import argparse
import csv
import hashlib
import io
import json
import math
import os
import shutil
import subprocess
import sys
import tempfile
import zlib
from collections import Counter, defaultdict
from pathlib import Path

TOOLS = Path(__file__).resolve().parent
DATA = TOOLS.parent / 'src/database/data'
OUT = DATA / 'boundaries'
DEFAULT_CACHE = Path(tempfile.gettempdir()) / 'gov-store-boundaries'
MAPSHAPER = TOOLS / 'node_modules/mapshaper/bin/mapshaper'
VERSION = 'bd-boundaries-2026-10-21.1'
BASE_FIELDS = ['GeoAreaId', 'hid', 'geo_type', 'parent_geo_code', 'geo_code', 'bn_name', 'domain', 'en_name', 'GeoLevel']

# Visvalingam interval (metres) per level, chosen for the view that draws that level:
# divisions in the country view, districts in a division, upazilas in a district, unions in an upazila.
TOLERANCE = {1: 1200, 2: 350, 3: 120, 4: 35}
LEVEL_TYPES = {1: ('division',), 2: ('district',), 3: ('upazilla', 'city'), 4: ('union', 'pourasabha')}
URBAN = {'city', 'pourasabha'}  # drawn, not drillable in v1 (plan D3)
# Size budget per children file, by the parent's level (plan §7 gate 5).
BUDGET = {0: 60_000, 1: 150_000, 2: 150_000, 3: 80_000}

# Transverse Mercator on WGS84, central meridian 90°E (the Bangladesh TM parameters of EPSG:3106 without
# its datum shift, which does not matter at map scale). Coordinates are rounded to UNIT metres in a fixed
# national frame whose origin is the north-west corner of the country, so every file shares one grid.
UNIT = 10
A, F, K0, LON0, X0 = 6378137.0, 1 / 298.257223563, 0.9996, math.radians(90.0), 500000.0
PROJ4 = '+proj=tmerc +lat_0=0 +lon_0=90 +k=0.9996 +x_0=500000 +y_0=0 +datum=WGS84 +units=m +no_defs'
ATTRIBUTION = ('Boundaries: Bangladesh Bureau of Statistics and OCHA, administrative boundaries of Bangladesh as of '
               '2015 (CC BY 3.0 IGO), via geoBoundaries (CC BY 4.0); P-codes from OCHA COD-AB v03 (CC BY 3.0 IGO). '
               'Higher levels are dissolved from the 2015 unions.')


class GateError(Exception):
    pass


def read_csv(path):
    with path.open(encoding='utf-8-sig', newline='') as f:
        return list(csv.DictReader(f))


def sha256_bytes(data):
    return hashlib.sha256(data).hexdigest()


def dump(obj):
    return (json.dumps(obj, ensure_ascii=False, separators=(',', ':')) + '\n').encode('utf-8')


# --- GovStore tree -------------------------------------------------------------------

def load_areas():
    with (DATA / 'geo_areas.csv').open(encoding='utf-8-sig', newline='') as f:
        rows = [dict(zip(BASE_FIELDS, r)) for r in csv.reader(f)]
    codes = {r['GeoAreaId']: r for r in read_csv(DATA / 'seed/geo_area_codes.csv')}
    by_geo_code = {r['geo_code']: r['GeoAreaId'] for r in rows}
    areas = {}
    for r in rows:
        areas[r['GeoAreaId']] = dict(id=r['GeoAreaId'], type='division' if r['geo_type'] == 'divison' else r['geo_type'],
                                     parent=by_geo_code.get(r['parent_geo_code']), en=r['en_name'],
                                     status=codes[r['GeoAreaId']]['code_status'])
    return areas


# --- sources -------------------------------------------------------------------------------

def verify_sources(cache):
    """Gate 2: every source archive matches sources.json and every extracted member matches its CRC."""
    import zipfile
    sources = json.loads((OUT / 'sources.json').read_text(encoding='utf-8'))['sources']
    for src in sources:
        folder = cache / src['id']
        archive = folder / src['file']
        if not archive.exists():
            raise GateError(f"gate 2: {archive} missing; run tools/fetch_boundary_sources.py")
        h = hashlib.sha256()
        with archive.open('rb') as f:
            for block in iter(lambda: f.read(1 << 20), b''):
                h.update(block)
        if h.hexdigest() != src['sha256']:
            raise GateError(f"gate 2: {src['file']} SHA-256 differs from sources.json")
        with zipfile.ZipFile(archive) as z:
            for name in src.get('extract', []):
                crc = 0
                with (folder / name).open('rb') as f:
                    for block in iter(lambda: f.read(1 << 20), b''):
                        crc = zlib.crc32(block, crc)
                if crc != z.getinfo(name).CRC:
                    raise GateError(f'gate 2: extracted {name} does not match the archive; rerun the fetch')
    return sources


# --- Mapshaper ---------------------------------------------------------------------------------

def join_table(areas):
    """shapeID -> l4 (union/paurashava drawn at level 4), l3 (upazila/city), l2, l1, from the crosswalk."""
    out = io.StringIO()
    w = csv.writer(out, lineterminator='\n')
    w.writerow(['shapeID', 'l4', 'l3', 'l2', 'l1'])
    for r in read_csv(OUT / 'crosswalk.csv'):
        if r['source'] != 'gb_adm4_2015':
            continue
        gid, l3 = r['GeoAreaId'], r['parent_GeoAreaId']
        if not l3 or areas.get(l3, {}).get('type') not in LEVEL_TYPES[3]:
            raise GateError(f"crosswalk row {r['source_id']} has no upazila/city parent")
        l4 = gid if gid and areas[gid]['type'] in LEVEL_TYPES[4] else ''
        l2 = areas[l3]['parent']
        l1 = areas[l2]['parent']
        if areas[l2]['type'] != 'district' or areas[l1]['type'] != 'division':
            raise GateError(f'GovStore tree above {l3} is not district/division')
        w.writerow([r['source_id'], l4, l3, l2, l1])
    return out.getvalue()


def run_mapshaper(cache, work):
    node = shutil.which('node')
    if not node or not MAPSHAPER.exists():
        raise GateError('node or mapshaper missing: run `npm install` in packages/gov-store/geo-areas/tools')
    src = cache / 'gb_adm4_2015/geoBoundaries-BGD-ADM4.geojson'
    cmd = [node, str(MAPSHAPER), '-quiet',
           '-i', str(src), 'snap', 'name=adm4',
           '-join', str(work / 'join.csv'), 'keys=shapeID,shapeID', 'string-fields=shapeID,l4,l3,l2,l1',
           '-clean', 'target=adm4',
           '-dissolve', '+', 'name=l0', 'target=adm4']
    for lvl in (1, 2, 3):
        cmd += ['-dissolve', f'l{lvl}', '+', f'name=l{lvl}', 'target=adm4']
    cmd += ['-filter', "l4 !== ''", '+', 'name=l4src', 'target=adm4',
            '-dissolve', 'l4', '+', 'name=l4', 'target=l4src']
    for lvl in (1, 2, 3, 4):
        (work / f's{lvl}').mkdir()  # mapshaper writes into a folder only if it exists
        # Simplifying the shared arcs keeps each child and its parent outline on the same border.
        cmd += ['-simplify', 'visvalingam', f'interval={TOLERANCE[lvl]}', 'keep-shapes', 'target=adm4',
                '-o', f's{lvl}/', f'target=l{lvl},l{lvl - 1}', 'format=geojson', 'precision=0.000001',
                '-o', f'level-{lvl}.json', f'target=l{lvl}', 'format=topojson', f'id-field=l{lvl}',
                'quantization=100000']
    env = dict(os.environ, NODE_OPTIONS='--max-old-space-size=8192')
    res = subprocess.run(cmd, cwd=work, env=env, capture_output=True, text=True)
    if res.returncode:
        raise GateError(f'mapshaper failed: {res.stderr.strip() or res.stdout.strip()}')


# --- projection and SVG paths ----------------------------------------------------------------------

E2 = F * (2 - F)
EP2 = E2 / (1 - E2)
M1 = 1 - E2 / 4 - 3 * E2 ** 2 / 64 - 5 * E2 ** 3 / 256
M2 = 3 * E2 / 8 + 3 * E2 ** 2 / 32 + 45 * E2 ** 3 / 1024
M3 = 15 * E2 ** 2 / 256 + 45 * E2 ** 3 / 1024
M4 = 35 * E2 ** 3 / 3072


def tm(lon, lat):
    """WGS84 lon/lat -> Transverse Mercator easting/northing in metres (Snyder 1987, eq. 8-9 to 8-10)."""
    phi = math.radians(lat)
    s, c, t = math.sin(phi), math.cos(phi), math.tan(phi)
    n = A / math.sqrt(1 - E2 * s * s)
    tt, cc, aa = t * t, EP2 * c * c, c * (math.radians(lon) - LON0)
    m = A * (M1 * phi - M2 * math.sin(2 * phi) + M3 * math.sin(4 * phi) - M4 * math.sin(6 * phi))
    x = K0 * n * (aa + (1 - tt + cc) * aa ** 3 / 6 + (5 - 18 * tt + tt * tt + 72 * cc - 58 * EP2) * aa ** 5 / 120) + X0
    y = K0 * (m + n * t * (aa ** 2 / 2 + (5 - tt + 9 * cc + 4 * cc * cc) * aa ** 4 / 24
                           + (61 - 58 * tt + tt * tt + 600 * cc - 330 * EP2) * aa ** 6 / 720))
    return x, y


def polygon_parts(geometry):
    if geometry is None:
        return []
    return [geometry['coordinates']] if geometry['type'] == 'Polygon' else geometry['coordinates']


def ring_area(ring):
    return sum(x0 * y1 - x1 * y0 for (x0, y0), (x1, y1) in zip(ring, ring[1:] + ring[:1])) / 2


def shape_of(feature, frame):
    """GeoJSON feature -> list of polygons, each a list of integer rings (first outer) in the frame."""
    e0, n0 = frame
    out = []
    for rings in polygon_parts(feature['geometry']):
        poly = []
        for i, ring in enumerate(rings):
            pts = []
            for lon, lat in ring:
                x, y = tm(lon, lat)
                p = (round((x - e0) / UNIT), round((n0 - y) / UNIT))
                if not pts or p != pts[-1]:
                    pts.append(p)
            if len(pts) > 1 and pts[0] == pts[-1]:
                pts.pop()
            if len(pts) >= 3 and ring_area(pts) != 0:
                poly.append(pts)
            elif i == 0:
                break  # the outer ring vanished at this resolution: drop the whole part (an islet)
        if poly:
            out.append(poly)
    return out


def shape_area(shape):
    return sum(abs(ring_area(p[0])) - sum(abs(ring_area(h)) for h in p[1:]) for p in shape)


def path_of(shape):
    """Relative SVG path; holes are separate subpaths, so draw with fill-rule evenodd."""
    out = []
    for poly in shape:
        for ring in poly:
            out.append(f'M{ring[0][0]} {ring[0][1]}l')
            prev = ring[0]
            sep = ''
            for p in ring[1:]:
                for d in (p[0] - prev[0], p[1] - prev[1]):
                    out.append(f'{d}' if d < 0 or not sep else f' {d}')
                    sep = ' '
                prev = p
            out.append('z')
    return ''.join(out)


def bbox(shapes):
    xs = [x for s in shapes for p in s for r in p for x, _ in r]
    ys = [y for s in shapes for p in s for r in p for _, y in r]
    return min(xs), min(ys), max(xs), max(ys)


# --- build ---------------------------------------------------------------------------------------

def build_once(cache, areas):
    work = Path(tempfile.mkdtemp(prefix='gov-store-boundaries-build-'))
    try:
        (work / 'join.csv').write_text(join_table(areas), encoding='utf-8')
        run_mapshaper(cache, work)
        def load(p):
            doc = json.loads(p.read_text(encoding='utf-8'))  # a layer without attributes is a GeometryCollection
            return doc.get('features') or [dict(properties={}, geometry=g) for g in doc.get('geometries', [])]
        country = load(work / 's1/l0.json')
        if len(country) != 1:
            raise GateError('the national dissolve is not a single feature')
        xs, ys = zip(*(tm(lon, lat) for poly in polygon_parts(country[0]['geometry']) for lon, lat in poly[0]))
        frame = (math.floor(min(xs) / 10000) * 10000, math.ceil(max(ys) / 10000) * 10000)
        country_id = next(a['id'] for a in areas.values() if a['type'] == 'country')

        files, shapes_per_level, gate = {}, {}, defaultdict(list)
        for lvl in (1, 2, 3, 4):
            children = {}
            for f in load(work / f's{lvl}/l{lvl}.json'):
                gid = f['properties'][f'l{lvl}']
                if gid in children:
                    raise GateError(f'gate 1: GeoAreaId {gid} appears twice at level {lvl}')
                if gid not in areas or areas[gid]['type'] not in LEVEL_TYPES[lvl]:
                    raise GateError(f'gate 1: level {lvl} shape id {gid!r} is not a GovStore {LEVEL_TYPES[lvl]}')
                shp = shape_of(f, frame)
                if not shp:
                    raise GateError(f'gate 3: {gid} ({areas[gid]["en"]}) collapsed at level {lvl}')
                children[gid] = shp
            shapes_per_level[lvl] = children
            if lvl == 1:
                parents = {country_id: shape_of(country[0], frame)}
            else:
                parents = {f['properties'][f'l{lvl - 1}']: shape_of(f, frame)
                           for f in load(work / f's{lvl}/l{lvl - 1}.json')}
            groups = defaultdict(list)
            for gid in children:
                groups[areas[gid]['parent']].append(gid)
            for pid, kids in groups.items():
                if pid not in parents:
                    raise GateError(f'gate 4: {kids[:3]} have no parent shape {pid} at level {lvl}')
                outline = parents[pid]
                pa, ka = shape_area(outline), sum(shape_area(children[k]) for k in kids)
                if ka > pa * 1.005:
                    raise GateError(f'gate 4: children of {pid} cover {ka / pa:.3f} of its area (overlap)')
                px0, py0, px1, py1 = bbox([outline])
                for k in kids:
                    x0, y0, x1, y1 = bbox([children[k]])
                    if x0 < px0 - 2 or y0 < py0 - 2 or x1 > px1 + 2 or y1 > py1 + 2:
                        raise GateError(f'gate 4: {k} reaches outside its parent {pid}')
                bx0, by0, bx1, by1 = bbox([outline] + [children[k] for k in kids])
                pad = max(20, round(max(bx1 - bx0, by1 - by0) * 0.01))
                doc = dict(version=VERSION, id=int(pid),
                           viewBox=f'{bx0 - pad} {by0 - pad} {bx1 - bx0 + 2 * pad} {by1 - by0 + 2 * pad}',
                           outline=path_of(outline),
                           areas=[dict(id=int(k), kind='urban' if areas[k]['type'] in URBAN else 'area',
                                       path=path_of(children[k])) for k in sorted(kids, key=int)])
                data = dump(doc)
                files[f'children/{pid}.json'] = data
                gate['sizes'].append((len(data), lvl - 1, pid))
                gate['cover'].append((lvl, pid, ka / pa))
            files[f'level-{lvl}.json'] = (work / f'level-{lvl}.json').read_bytes()
        return files, shapes_per_level, frame, gate
    finally:
        shutil.rmtree(work, ignore_errors=True)


def coverage(areas, shapes_per_level, crosswalk):
    out = {}
    for lvl, types in LEVEL_TYPES.items():
        live = [a for a in areas.values() if a['type'] in types and a['status'] != 'duplicate']
        drawn = sum(1 for a in live if a['id'] in shapes_per_level[lvl])
        out[f'level_{lvl}'] = dict(types=list(types), areas=len(live), with_shape=drawn,
                                   percent=round(100 * drawn / len(live), 1))
    out['source_polygons'] = dict(Counter(r['status'] for r in crosswalk if r['source'] == 'gb_adm4_2015'))
    return out


def report(areas, shapes_per_level, crosswalk, gate, manifest):
    lines = [f'# Boundary build report — {VERSION}', '', ATTRIBUTION, '',
             '## Coverage', '', '| Level | GovStore types | Areas | With shape | % |', '| ---: | --- | ---: | ---: | ---: |']
    for lvl in (1, 2, 3, 4):
        c = manifest['coverage'][f'level_{lvl}']
        lines.append(f"| {lvl} | {', '.join(c['types'])} | {c['areas']:,} | {c['with_shape']:,} | {c['percent']} |")
    lines += ['', 'Source polygons by crosswalk status: '
              + ', '.join(f'{k} {v:,}' for k, v in sorted(manifest['coverage']['source_polygons'].items())), '',
              '## Files', '', f"{len(manifest['files']):,} files, {sum(f['bytes'] for f in manifest['files'].values()) / 1e6:.1f} MB.", '',
              '| Children of | Files | Largest | Budget |', '| --- | ---: | ---: | ---: |']
    names = {0: 'country', 1: 'division', 2: 'district', 3: 'upazila'}
    for lvl in (0, 1, 2, 3):
        sizes = sorted((s for s in gate['sizes'] if s[1] == lvl), reverse=True)
        if sizes:
            lines.append(f'| {names[lvl]} | {len(sizes)} | {sizes[0][0] / 1000:.1f} KB ({sizes[0][2]} '
                         f'{areas[sizes[0][2]]["en"]}) | {BUDGET[lvl] / 1000:.0f} KB |')
    lines += ['', '| Level file | Size |', '| --- | ---: |']
    lines += [f"| level-{lvl}.json | {manifest['files'][f'level-{lvl}.json']['bytes'] / 1e6:.2f} MB |" for lvl in (1, 2, 3, 4)]

    partial = sorted((c for c in gate['cover'] if c[2] < 0.98), key=lambda c: c[2])
    lines += ['', f'## Parents whose children cover less than 98% of their shape ({len(partial)})', '',
              'The rest of the shape is 2015 area with no GovStore child: forest ranges, unions absorbed into a city, '
              'renumbered city wards, or unions GovStore lists elsewhere.', '']
    lines += [f'- {pid} {areas[pid]["en"]} (level {lvl - 1}): {c * 100:.1f}%' for lvl, pid, c in partial]

    for lvl, label in ((3, 'Upazilas / city corporations'), (4, 'Unions / paurashavas')):
        missing = sorted((a for a in areas.values() if a['type'] in LEVEL_TYPES[lvl] and a['status'] != 'duplicate'
                          and a['id'] not in shapes_per_level[lvl]), key=lambda a: int(a['id']))
        lines += ['', f'## {label} without a shape ({len(missing)})', '',
                  'Listed by the API without `path`; the table fallback shows them.', '']
        lines += [f"- {a['id']} {a['en']} ({a['type']}, {a['status']})" for a in missing]
    held = [r for r in crosswalk if r['source'] == 'gb_adm4_2015' and r['status'] in ('held', 'no_govstore_area')]
    lines += ['', f'## Source polygons drawn only as part of their parent ({len(held)})', '']
    lines += [f"- {r['source_pcode']} → parent {r['parent_GeoAreaId']} ({r['status']}): {r['note']}" for r in held]
    lines += ['', 'All six build gates passed.', '']
    return '\n'.join(lines).encode('utf-8')


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--cache', type=Path, default=DEFAULT_CACHE, help=f'source cache (default {DEFAULT_CACHE})')
    ap.add_argument('--once', action='store_true', help='skip the second build (gate 6) while tuning')
    ap.add_argument('--check', action='store_true', help='run the gates without writing')
    args = ap.parse_args()
    try:
        sources = verify_sources(args.cache)
        areas = load_areas()
        crosswalk = read_csv(OUT / 'crosswalk.csv')
        files, shapes, frame, gate = build_once(args.cache, areas)
        if not args.once:
            again, _, _, _ = build_once(args.cache, areas)
            diff = [k for k in files if files[k] != again.get(k)] + [k for k in again if k not in files]
            if diff:
                raise GateError(f'gate 6: two builds differ in {len(diff)} files, e.g. {diff[:3]}')
        over = [(size, pid) for size, lvl, pid in gate['sizes'] if size > BUDGET[lvl]]
        if over:
            raise GateError(f'gate 5: {len(over)} children files exceed their budget, e.g. '
                            + ', '.join(f'{pid} {areas[pid]["en"]} {size / 1000:.0f} KB' for size, pid in sorted(over)[-5:]))
    except GateError as e:
        sys.exit(f'Build refused: {e}')

    manifest = dict(
        version=VERSION, dataset_version=json.loads((DATA / 'seed/manifest.json').read_text(encoding='utf-8'))['version'],
        attribution=ATTRIBUTION,
        sources=[{k: s[k] for k in ('id', 'title', 'publisher', 'url', 'sha256', 'licence', 'retrieved_on')} for s in sources],
        method='2015 union polygons (P-codes recovered from COD-AB v03 adminpoints) joined to GovStore through '
               'crosswalk.csv; levels 1-3 dissolved through the GovStore tree; Mapshaper 0.6.121 Visvalingam '
               'simplification on shared arcs',
        tolerance_m={str(k): v for k, v in TOLERANCE.items()},
        frame=dict(projection=PROJ4, unit_m=UNIT, origin_easting=frame[0], origin_northing=frame[1],
                   axes='x = (easting - origin_easting) / unit_m, y = (origin_northing - northing) / unit_m',
                   fill_rule='evenodd'),
        kinds=dict(area='drillable administrative area', urban='city corporation or paurashava; not drillable in v1'),
        coverage=coverage(areas, shapes, crosswalk),
        files={k: dict(bytes=len(v), sha256=sha256_bytes(v)) for k, v in sorted(files.items())})
    if args.check:
        print(json.dumps(manifest['coverage'], indent=2))
        print('All gates pass.')
        return
    target = OUT / VERSION
    if target.exists():
        shutil.rmtree(target)
    (target / 'children').mkdir(parents=True)
    for name, data in files.items():
        (target / name).write_bytes(data)
    (target / 'manifest.json').write_bytes(json.dumps(manifest, ensure_ascii=False, indent=2).encode('utf-8') + b'\n')
    (target / 'BUILD_REPORT.md').write_bytes(report(areas, shapes, crosswalk, gate, manifest))
    print(json.dumps(manifest['coverage'], indent=2))
    print(f'{len(files)} files, {sum(len(v) for v in files.values()) / 1e6:.1f} MB -> {target}')


if __name__ == '__main__':
    main()
