#!/usr/bin/env python3
"""Offline, conservative crosscheck. Python 3.10+, standard library only.

Never writes the source files or connects to a database. Names are matched only
within an already matched parent. Fuzzy suggestions never become assignments.
"""

import argparse
import csv
import difflib
import hashlib
import json
import posixpath
import re
import unicodedata
import xml.etree.ElementTree as ET
import zipfile
from collections import Counter, defaultdict
from pathlib import Path

BASE_FIELDS = ['GeoAreaId', 'hid', 'geo_type', 'parent_geo_code', 'geo_code',
               'bn_name', 'domain', 'en_name', 'GeoLevel']
CODE_FIELDS = ['bbs_division_code', 'bbs_zila_code', 'bbs_upazila_thana_code',
               'bbs_city_paurasava_code', 'bbs_union_ward_code']
NS = {'s': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
KINDS = {'divison': 'division', 'district': 'district', 'upazilla': 'upazila_thana',
         'union': 'union', 'pourasabha': 'municipality', 'city': 'city',
         'city_thana': 'upazila_thana', 'pouro_ward': 'ward', 'thana_ward': 'ward'}
ORDER = ['divison', 'district', 'upazilla', 'city', 'city_thana',
         'pourasabha', 'union', 'pouro_ward', 'thana_ward', 'country']


def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def read_bbs(path):
    """Read the known six-column workbook without changing its ZIP/XML parts."""
    with zipfile.ZipFile(path) as z:
        shared = []
        if 'xl/sharedStrings.xml' in z.namelist():
            shared = [''.join(si.itertext()) for si in
                      ET.fromstring(z.read('xl/sharedStrings.xml')).findall('s:si', NS)]
        book = ET.fromstring(z.read('xl/workbook.xml'))
        sheets = book.findall('s:sheets/s:sheet', NS)
        if len(sheets) != 1:
            raise ValueError('Expected exactly one BBS worksheet; review workbook layout.')
        sheet = sheets[0]
        rid = sheet.attrib['{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id']
        relationships = ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))
        target = next(r.attrib['Target'] for r in relationships if r.attrib['Id'] == rid)
        target = target.lstrip('/') if target.startswith('/') else posixpath.normpath('xl/' + target)
        values = []
        for row in ET.fromstring(z.read(target)).findall('s:sheetData/s:row', NS):
            cells = [''] * 6
            for c in row.findall('s:c', NS):
                col = re.match(r'[A-Z]+', c.attrib['r'])[0]
                if len(col) != 1 or col > 'F':
                    continue
                if c.find('s:f', NS) is not None:
                    raise ValueError('Unexpected formula in BBS input.')
                v = c.find('s:v', NS)
                value = '' if v is None else (v.text or '')
                if c.attrib.get('t') == 's':
                    value = shared[int(value)]
                elif c.attrib.get('t') == 'inlineStr':
                    value = ''.join(c.find('s:is', NS).itertext())
                cells[ord(col) - 65] = value
            if any(cells):
                values.append((int(row.attrib['r']), cells))
        if [re.sub(r'\s+', ' ', x).strip() for x in values[0][1]] != [
                'Division', 'Zila', 'Upazila / Thana', 'City Corp. / Paurasava', 'Union/ Ward', 'Name']:
            raise ValueError('Unexpected BBS headers; review workbook layout.')
        records = []
        for rownum, cells in values[1:]:
            codes = []
            for x in cells[:5]:
                if x and not re.fullmatch(r'\d+(?:\.0+)?', x):
                    raise ValueError(f'Invalid BBS code at row {rownum}: {x!r}')
                code = f'{int(float(x)):02d}' if x else ''
                if len(code) > 2:
                    raise ValueError(f'BBS code exceeds two digits at row {rownum}')
                codes.append(code)
            d, zila, upz, urban, leaf = codes
            if not d or (upz and not zila) or (urban and not upz) or (leaf and not urban):
                raise ValueError(f'Incomplete BBS hierarchy at row {rownum}')
            if not zila:
                kind = 'division'
            elif not upz:
                kind = 'district'
            elif not urban:
                kind = 'upazila_thana'
            elif not leaf:
                kind = 'city' if re.search(r'CITY\s+CORP', cells[5], re.I) else (
                    'municipality' if re.search(r'PAURAS(?:H)?AV(?:A)?\b', cells[5], re.I)
                    else 'urban_part')
            else:
                kind = 'union' if urban == '99' else 'ward'
            records.append(dict(row=rownum, rows=[rownum], codes=codes,
                                name=cells[5], kind=kind, key='/'.join(codes)))
        props = ET.fromstring(z.read('docProps/core.xml'))
        metadata = {x.tag.split('}')[-1]: x.text for x in props}
        return sheet.attrib['name'], records, metadata


def normalize(name, kind):
    text = unicodedata.normalize('NFKC', name).casefold().strip()
    suffix = {
        'division': r'\s+division$', 'district': r'\s+(?:district|distric|zila)$',
        'upazila_thana': r'\s+(?:upazilla|upazila|upazil|upazi|upaz|up|thana)$',
        'municipality': r'\s+(?:pourasabha|paurasava|paurashava|paurashav)$',
        'city': r'\s+city\s+(?:corporation|corp\.?)$',
        'union': r'\s+union$',
    }
    if kind in suffix:
        text = re.sub(suffix[kind], '', text)
    if kind == 'ward':
        # Whole-name check keeps PART/EXTENSION qualifiers significant.
        m = re.fullmatch(r'ward\s*(?:no\.?)?\s*[-.:]?\s*0*(\d+)', text)
        if m:
            return 'ward' + str(int(m[1]))
    return ''.join(x for x in text if x.isalnum())


def reconcile(csv_path, xlsx_path):
    with csv_path.open(encoding='utf-8-sig', newline='') as f:
        raw = list(csv.reader(f))
    if any(len(r) != 9 for r in raw):
        raise ValueError('CSV must be headerless with exactly nine fields per record.')
    base = [dict(zip(BASE_FIELDS, r)) for r in raw]
    for field in ('GeoAreaId', 'geo_code'):
        if len({r[field] for r in base}) != len(base):
            raise ValueError(f'Current CSV has duplicate {field}; cannot establish unique parents.')
    bycode = {r['geo_code']: r for r in base}
    sheet, bbs, metadata = read_bbs(xlsx_path)
    key_counts = Counter(b['key'] for b in bbs)
    duplicates = {k for k, n in key_counts.items() if n > 1}
    kinds = defaultdict(list)
    for b in bbs:
        if b['kind'] not in ('city', 'urban_part'):
            kinds[b['kind']].append(b)

    # A BBS city can have separate source rows under multiple thanas. Collapse
    # only repeated district + city-code + exact normalized city-name groups.
    cities = {}
    for b in bbs:
        if b['kind'] == 'city':
            d, zila, _, urban, _ = b['codes']
            k = (d, zila, urban, normalize(b['name'], 'city'))
            if k not in cities:
                cities[k] = dict(b, codes=[d, zila, '', urban, ''], rows=[],
                                 key=f'city:{d}/{zila}/{urban}/{k[-1]}')
            cities[k]['rows'].extend(b['rows'])
    kinds['city'] = list(cities.values())

    matches, reasons, suggestions, context_notes, methods = {}, {}, {}, {}, {}
    integrity = []
    for r in base:
        rid = r['GeoAreaId']
        if r['geo_type'] != 'country' and r['parent_geo_code'] not in bycode:
            reasons[rid] = 'missing_current_parent'
            integrity.append(dict(GeoAreaId=rid, issue='missing_current_parent',
                                  detail=r['parent_geo_code']))
        if r['hid'] and r['hid'] != '?':
            parts = r['hid'].strip('/').split('/')
            if parts[-1] != r['geo_code'] or (len(parts) > 1 and parts[-2] != r['parent_geo_code']):
                reasons[rid] = 'current_hierarchy_path_conflict'
                integrity.append(dict(GeoAreaId=rid, issue=reasons[rid], detail=r['hid']))
        seen, current = set(), r
        while current and current['geo_type'] != 'country':
            if current['geo_code'] in seen:
                raise ValueError(f'Cycle in current hierarchy near GeoAreaId {rid}')
            seen.add(current['geo_code'])
            current = bycode.get(current['parent_geo_code'])

    def scope(r):
        t = r['geo_type']
        kind = KINDS.get(t)
        pool = kinds[kind]
        if t == 'divison':
            return pool
        p = bycode.get(r['parent_geo_code'])
        if not p or p['GeoAreaId'] not in matches:
            return None
        parent = matches[p['GeoAreaId']]
        pc = parent['codes']
        expected_parent = {'district': 'divison', 'upazilla': 'district', 'city': 'district',
                           'city_thana': 'city', 'pourasabha': 'upazilla', 'union': 'upazilla',
                           'pouro_ward': 'pourasabha', 'thana_ward': 'city_thana'}
        if p['geo_type'] != expected_parent.get(t):
            reasons[r['GeoAreaId']] = 'unexpected_current_parent_type'
            return None
        if t == 'district':
            return [b for b in pool if b['codes'][0] == pc[0]]
        if t in ('upazilla', 'city'):
            return [b for b in pool if b['codes'][:2] == pc[:2]]
        if t == 'city_thana':
            # Match the thana only where the BBS urban code occurs beneath it.
            upz_codes = {tuple(b['codes'][:3]) for b in bbs
                         if b['codes'][:2] == pc[:2] and b['codes'][3] == pc[3]}
            return [b for b in pool if tuple(b['codes'][:3]) in upz_codes]
        if t in ('pourasabha', 'union'):
            return [b for b in pool if b['codes'][:3] == pc[:3]]
        if t == 'pouro_ward':
            return [b for b in pool if b['codes'][:4] == pc[:4]]
        if t == 'thana_ward':
            city = bycode[p['parent_geo_code']]
            city_match = matches[city['GeoAreaId']]
            return [b for b in pool if b['codes'][:3] == pc[:3]
                    and b['codes'][3] == city_match['codes'][3]]
        return []

    claimed = {}
    for t in ORDER:
        proposals = defaultdict(list)
        for r in (r for r in base if r['geo_type'] == t):
            rid = r['GeoAreaId']
            if rid in reasons:
                continue
            if t == 'country':
                reasons[rid] = 'country_not_in_bbs_workbook'
                continue
            pool = scope(r)
            if pool is None:
                reasons.setdefault(rid, 'parent_not_matched')
                continue
            norm = normalize(r['en_name'], KINDS[t])
            candidates = [b for b in pool if normalize(b['name'], b['kind']) == norm]
            method = 'unique_normalized_name_and_matched_parent'
            if not candidates and t == 'upazilla' and norm == 'sadar':
                # SADAR alone is accepted only when the BBS name is exactly the
                # already matched district's name + SADAR (not ADARSHA SADAR,
                # SADAR DAKSHIN, or another qualified administrative unit).
                parent = bycode[r['parent_geo_code']]
                district = matches[parent['GeoAreaId']]
                full = normalize(district['name'], 'district') + 'sadar'
                candidates = [b for b in pool if normalize(b['name'], b['kind']) == full]
                method = 'sadar_with_exact_matched_district_prefix'
            if len(candidates) == 1 and candidates[0]['key'] not in duplicates:
                proposals[candidates[0]['key']].append((r, candidates[0], method))
            else:
                reasons[rid] = ('duplicate_bbs_code_path' if any(b['key'] in duplicates for b in candidates)
                                else 'ambiguous_bbs_name_in_parent' if candidates
                                else 'no_exact_name_in_matched_parent')
                scored = sorted(((difflib.SequenceMatcher(None, norm, normalize(b['name'], b['kind'])).ratio(), b)
                                 for b in pool), key=lambda item: (-item[0], item[1]['row']))
                suggestions[rid] = [dict(excel_rows=b['rows'], name=b['name'],
                                         codes=b['codes'], similarity=round(score, 4),
                                         scope='matched_parent') for score, b in scored[:3]]
                if t == 'district' and not candidates:
                    elsewhere = [b for b in kinds['district'] if normalize(b['name'], 'district') == norm]
                    if elsewhere:
                        context_notes[rid] = 'Same district name exists under a different BBS division; not assigned.'
        for key, group in proposals.items():
            if len(group) != 1 or key in claimed:
                for r, b, method in group:
                    rid = r['GeoAreaId']
                    reasons[rid] = 'multiple_current_records_claim_same_bbs_area'
                    suggestions[rid] = [dict(excel_rows=b['rows'], name=b['name'], codes=b['codes'],
                                             similarity=1.0, scope='matched_parent')]
            else:
                r, b, method = group[0]
                matches[r['GeoAreaId']] = b
                methods[r['GeoAreaId']] = method
                claimed[key] = r['GeoAreaId']

    # If an unmatched parent prevents a comparison, still offer clearly marked
    # suggestions inside its nearest matched ancestor. These are never merged.
    for r in base:
        rid, t = r['GeoAreaId'], r['geo_type']
        if rid in matches or suggestions.get(rid) or t not in KINDS:
            continue
        parent = bycode.get(r['parent_geo_code'])
        while parent and parent['GeoAreaId'] not in matches:
            parent = bycode.get(parent['parent_geo_code'])
        if parent and parent['geo_type'] != 'country':
            anc = matches[parent['GeoAreaId']]
            length = {'division': 1, 'district': 2, 'upazila_thana': 3,
                      'municipality': 4}.get(anc['kind'], 2)
            pool = [b for b in kinds[KINDS[t]] if b['codes'][:length] == anc['codes'][:length]]
            norm = normalize(r['en_name'], KINDS[t])
            scored = sorted(((difflib.SequenceMatcher(None, norm, normalize(b['name'], b['kind'])).ratio(), b)
                             for b in pool), key=lambda item: (-item[0], item[1]['row']))
            suggestions[rid] = [dict(excel_rows=b['rows'], name=b['name'], codes=b['codes'],
                                     similarity=round(score, 4), scope='nearest_matched_ancestor_only')
                                for score, b in scored[:3]]

    fields = BASE_FIELDS + ['match_status', 'match_method', 'failure_reason'] + CODE_FIELDS + [
        'bbs_code_path', 'bbs_name', 'bbs_excel_rows', 'current_csv_line', 'current_parent_name',
        'notes', 'review_candidates_json']
    merged, failed = [], []
    for linenum, r in enumerate(base, 1):
        rid = r['GeoAreaId']
        b = matches.get(rid)
        out = dict(r, match_status='matched' if b else 'unmatched',
                   match_method=methods.get(rid, ''),
                   failure_reason='' if b else reasons.get(rid, 'unsupported_current_type'),
                   current_csv_line=linenum,
                   current_parent_name=bycode.get(r['parent_geo_code'], {}).get('en_name', ''),
                   notes=context_notes.get(rid, ''),
                   review_candidates_json=json.dumps(suggestions.get(rid, []), ensure_ascii=False) if not b else '')
        out.update(dict(zip(CODE_FIELDS, b['codes'] if b else [''] * 5)))
        out.update(bbs_code_path=b['key'] if b else '', bbs_name=b['name'] if b else '',
                   bbs_excel_rows=';'.join(map(str, b['rows'])) if b else '')
        merged.append(out)
        if not b:
            failed.append(out)
    linked_rows = {n for b in matches.values() for n in b['rows']}
    unused = [dict(b, reason='duplicate_bbs_code_path' if b['key'] in duplicates
                   else 'not_linked_by_conservative_rules') for b in bbs if b['row'] not in linked_rows]
    summary = {
        'method': 'Unique normalized English name + type + already matched immediate parent. Sadar may have its exact matched district name prefixed. No fuzzy assignments or name aliases.',
        'current_csv_sha256': sha256(csv_path), 'bbs_xlsx_sha256': sha256(xlsx_path),
        'bbs_sheet': sheet, 'bbs_workbook_metadata': metadata,
        'current_rows': len(base), 'bbs_rows': len(bbs), 'matched_current_rows': len(matches),
        'unmatched_current_rows': len(failed), 'matched_percent': round(100 * len(matches) / len(base), 2),
        'linked_bbs_source_rows': len(linked_rows), 'unlinked_bbs_source_rows': len(unused),
        'current_integrity_issues': integrity,
        'duplicate_bbs_paths': {k: [b['row'] for b in bbs if b['key'] == k] for k in sorted(duplicates)},
        'failure_reasons': dict(sorted(Counter(r['failure_reason'] for r in failed).items())),
        'match_methods': dict(sorted(Counter(methods.values()).items())),
        'by_type': [dict(geo_type=t, total=sum(r['geo_type'] == t for r in base),
                         matched=sum(r['geo_type'] == t and r['GeoAreaId'] in matches for r in base))
                    for t in ORDER],
        'interpretation': 'Coverage under conservative rules, not independently measured accuracy or proof that historical boundaries remain current.'
    }
    assert len(merged) == len(base) and len(matches) + len(failed) == len(base)
    assert len(linked_rows) + len(unused) == len(bbs)
    assert len({b['key'] for b in matches.values()}) == len(matches)
    return fields, merged, failed, unused, summary


def write_csv(path, fields, records):
    with path.open('w', encoding='utf-8-sig', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=fields, extrasaction='raise')
        writer.writeheader()
        writer.writerows(records)


def write_report(path, summary):
    s = summary
    lines = [
        '# Geography crosscheck', '',
        f"Matched **{s['matched_current_rows']:,} / {s['current_rows']:,} current records "
        f"({s['matched_percent']:.2f}%)** under conservative automatic rules. "
        f"**{s['unmatched_current_rows']:,}** current records remain unmatched. "
        'This is match coverage, not independently measured accuracy or proof that historical boundaries remain current.', '',
        '## Files', '',
        '- `geo_areas_merged.csv`: every original CSV row, in original order, with its nine original fields unchanged. '
        'BBS fields are appended only for accepted matches; unmatched rows have blank BBS fields.',
        '- `geo_areas_failed.csv`: the unmatched subset, with failure reasons and up to three review candidates in JSON. '
        'Candidate similarity is a spelling score, not a probability of correctness. Candidates are never assignments.',
        f"- `bbs_unmatched.csv`: {s['unlinked_bbs_source_rows']:,} original workbook rows not linked to an accepted current record. "
        'These are unlinked source records, not necessarily new areas.',
        '- `summary.json`: counts, hashes, workbook metadata and integrity findings.',
        '- `merge_summary.xlsx`, when present: a readable snapshot of this run. The Python program regenerates the CSVs, JSON and this report; '
        'a workbook must be regenerated separately after changing the inputs.', '',
        'The CSV outputs are UTF-8 with a BOM for Bangla display in Excel. Import BBS codes as **text** to preserve leading zeros. '
        'These are review/crosswalk files. The existing nine-column importer does not import the appended BBS fields.', '',
        '## Matching rules', '',
        '1. Traverse current `parent_geo_code` relationships by type; require each immediate parent to have an accepted match.',
        '2. Compare normalized English names inside that matched parent and compatible BBS type. '
        'Normalization changes case, spaces and punctuation, strips administrative type suffixes (including observed truncated suffixes), '
        'and normalizes a complete ward number. Directional and PART qualifiers remain significant.',
        '3. For a current upazila named only Sadar, allow exactly the already matched BBS district name followed by Sadar. '
        'Do not equate Adarsha Sadar, Sadar Dakshin, or other qualified names.',
        '4. Require a unique candidate and a unique current claimant. Reject ambiguous names, duplicate BBS paths, missing current parents '
        'and supplied path/parent conflicts. No substantive spelling aliases, fuzzy assignments or cross-division reassignment.',
        '5. BBS rural unions use city/paurasava code 99. Municipalities and urban wards retain their source contexts. '
        'A city may have multiple BBS rows under different thanas; group only the same district, city code and normalized city name. '
        'All contributing workbook row numbers are retained. City-thana matching requires that city code beneath the BBS thana.', '',
        'BBS code components are separate two-character strings. `bbs_code_path` is a crosscheck key, not a newly asserted official identifier. '
        'For grouped cities it starts with `city:`; its upazila/thana component is blank because there may be several. '
        'Ward labels can differ from BBS ward codes; preserve the supplied code instead of deriving it from the label. '
        '`GeoAreaId`, `geo_code`, `parent_geo_code`, `hid`, names and types are never replaced.', '',
        '## Coverage by current area type', '',
        '| Type | Current | Matched | Unmatched | Coverage |',
        '| --- | ---: | ---: | ---: | ---: |',
    ]
    for row in s['by_type']:
        total, matched = row['total'], row['matched']
        lines.append(f"| {row['geo_type']} | {total:,} | {matched:,} | {total-matched:,} | {100*matched/total:.2f}% |")
    lines += ['', '## Unmatched reasons', '', '| Reason | Current rows |', '| --- | ---: |']
    lines += [f'| {reason} | {count:,} |' for reason, count in s['failure_reasons'].items()]
    lines += [
        '', 'A parent failure holds its descendants for review; it does not establish that all those descendants are absent from BBS. '
        'The country record has no counterpart in the workbook and is intentionally unmatched.', '',
        '## Source differences and open review', '',
        '- The current data has eight divisions, including Mymensingh. The workbook has seven divisions. '
        'Its Mymensingh-area districts occur under Dhaka; current division relationships are preserved and those branches are held for review.',
        '- Workbook core metadata records creation on 27 June 2014 and modification on 30 June 2014. '
        'These timestamps do not establish the publication date or effective date of every boundary. '
        'Publisher, release and reuse terms were not independently verified.',
        '- Substantive differences such as Cumilla / COMILLA, Chattogram / CHITTAGONG, Bogura / BOGRA, '
        'Jashore / JESSORE, and Dhaka South / DHAKA DAKSHIN need an explicit reviewed name crosswalk. '
        'Reviewing parent names can unlock further exact descendant comparisons; a higher accurate percentage cannot be claimed from spelling similarity alone.',
        '- Nine current ward records (GeoAreaId 7829 through 7837) reference missing parent geo_code 58845. '
        'They remain unchanged and unmatched.',
        '- Workbook rows 1066 and 1067 repeat BAIRAG (PART) at 20/15/04/99/19. '
        'Duplicate source paths are excluded from assignments rather than silently selecting a row.',
        '- The current data includes BAKERGANJ municipality under Agailjhara; it is not reassigned merely because the same name exists elsewhere. '
        'Such cases require current hierarchy review.', '',
        f"Accepted links cover {s['linked_bbs_source_rows']:,} workbook source rows for {s['matched_current_rows']:,} current records; "
        'the difference comes from cities represented under several BBS thanas.', '',
        '## Reproduce', '',
        'From the repository root, run with Python 3.10 or newer (standard library only):', '',
        '```text', 'python packages/gov-store/geo-areas/tools/reconcile_bbs_geocodes.py', '```', '',
        'Use `--current`, `--bbs` and `--output` to supply other input/output paths. '
        'The program is offline and never connects to a database. It does not change either source file. '
        'The current CSV and the existing database import are unaffected.', '',
        '## Source fingerprints', '',
        f"- Current CSV SHA-256: `{s['current_csv_sha256']}`",
        f"- BBS workbook SHA-256: `{s['bbs_xlsx_sha256']}`", '',
    ]
    path.write_text('\n'.join(lines), encoding='utf-8')


def main():
    data = Path(__file__).resolve().parents[1] / 'src/database/data'
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--current', type=Path, default=data / 'geo_areas.csv')
    parser.add_argument('--bbs', type=Path, default=data / 'BBS_Geocode_list_upz.xlsx')
    parser.add_argument('--output', type=Path, default=data / 'bbs-reconciliation')
    args = parser.parse_args()
    inputs = [args.current.resolve(), args.bbs.resolve()]
    if args.output.resolve() in inputs or args.output.resolve() == data.resolve():
        parser.error('Use a separate output directory.')
    fields, merged, failed, unused, summary = reconcile(args.current, args.bbs)
    args.output.mkdir(parents=True, exist_ok=True)
    write_csv(args.output / 'geo_areas_merged.csv', fields, merged)
    write_csv(args.output / 'geo_areas_failed.csv', fields, failed)
    write_csv(args.output / 'bbs_unmatched.csv', ['bbs_excel_row', 'bbs_type', *CODE_FIELDS,
                                               'bbs_code_path', 'bbs_name', 'reason'],
              [dict(bbs_excel_row=b['row'], bbs_type=b['kind'], **dict(zip(CODE_FIELDS, b['codes'])),
                    bbs_code_path=b['key'], bbs_name=b['name'], reason=b['reason']) for b in unused])
    (args.output / 'summary.json').write_text(json.dumps(summary, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    write_report(args.output / 'README.md', summary)
    # This JSON is also consumed by the optional review-workbook builder.
    print(json.dumps({k: summary[k] for k in ['current_rows', 'bbs_rows', 'matched_current_rows',
                                           'unmatched_current_rows', 'matched_percent', 'by_type']}, indent=2))


if __name__ == '__main__':
    main()
