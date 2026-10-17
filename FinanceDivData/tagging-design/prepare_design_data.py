"""Reproduce/validate the provisional tagging design pack. No application/DB access.

Run with --write to generate the named design files, or --check to compare them
with their source-derived content. These are generated review worksheets: copy
them into a separately reviewed release before entering real approvals/mappings.
No external packages, network, credentials, migrations or database are used.
"""

from __future__ import annotations

import argparse
import collections
import csv
import hashlib
import io
import json
import math
from pathlib import Path
import re


HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
SOURCE = ROOT / "FinanceDivData"
PACKAGE = ROOT / "packages/gov-store/asset-tagging"
NODES = ROOT / "packages/gov-store/classification/src/Database/data/compiled_nodes.csv"
PDF = ROOT / "Policy and Procedure Manual for Property, Plant, Equipment and Other Assets finance division.pdf"
SOURCE_SHA = "83d798aff269704393f275f7af8fcd162f8dd69d61145f0469261c0fceb36b89"
AS_OF = "2026-10-15"


def sha(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open(encoding="utf-8-sig", newline="") as stream:
        return list(csv.DictReader(stream))


def csv_text(fields: list[str], rows: list[dict]) -> str:
    stream = io.StringIO(newline="")
    writer = csv.DictWriter(stream, fieldnames=fields, lineterminator="\n")
    writer.writeheader()
    writer.writerows(rows)
    return stream.getvalue()


def json_text(value: object) -> str:
    return json.dumps(value, ensure_ascii=False, indent=2) + "\n"


def expect(condition: bool, message: str) -> None:
    if not condition:
        raise ValueError(message)


STOP = set("a an and or the of for to in on with including other asset assets equipment system systems type types miscellaneous etc all general set".split())
TOKEN_ALIASES = {"computers": "computer", "printers": "printer", "laptops": "laptop", "desktops": "desktop", "tables": "table", "chairs": "chair", "racks": "rack", "servers": "server", "scanners": "scanner", "batteries": "battery", "machines": "machine", "vehicles": "vehicle", "tools": "tool", "buildings": "building"}


def tokens(label: str) -> set[str]:
    return {TOKEN_ALIASES.get(t, t) for t in re.findall(r"[a-z0-9]+", label.lower()) if len(t) > 1 and t not in STOP}


# Manually selected search leads, verified for existence/title/level in the local
# bundle below. These are NOT semantic approvals or upstream release assertions.
CURATED = {
    "090708": ["43211507"],  # Desktop Computer
    "090809": ["43211503"],  # Laptop -> Notebook computer
    "050826": ["56101703"],  # Secretariat Table -> Desks; needs actual use/specification
}


DECISIONS = [
    ("D01", "Applicability", "Organization Finance/Administration owner", "Confirm the intended register adopts this SOE/AB manual.", "production enabled scope", "Manual title; PDF pp.87-101"),
    ("D02", "Controlling Finance codes", "Finance/Administration owner and existing catalog publisher", "Resolve example conflicts, 11/23 label collision and questionable p.103 rows; approve usable scope with authority reference.", "affected rows", "PDF pp.89,91,99,100,103,106,110"),
    ("D03", "Sequence rule and continuity", "Register owner with developer", "Confirm installation-wide exact first14-prefix counter, 9999 limit and no reset; retain tag across custody/settings/model changes.", "enabled allocation", "Manual does not define suffix scope; architecture revision 7"),
    ("D04", "Office facts", "Current own office administrator", "Provide actual two-digit registration location/division assignments and authority reference using office metadata; no new issuer namespace.", "office enablement", "PDF pp.98-99 examples are not actual organization mappings"),
    ("D05", "Acquisition evidence and eligible units", "Receiving office and Finance owner", "Provide actual purchase date/evidence and individual-unit representation. Resolve unknown date conventions only where needed.", "affected units", "PDF pp.88,99,100-101,120"),
    ("D06", "Optional UNSPSC mapping", "Existing catalog/domain reviewer", "Verify semantic mapping and local UNSPSC provenance; store optional code only on Finance item. Never auto-approve candidate leads.", "approved crosswalk publication, not otherwise valid Finance issuance", "Local compiled bundle; finance_unspsc_review.csv"),
    ("D07", "Legacy rollout", "Developer and office custodians", "Reconcile existing/deleted labels, bridge duplicates and physical bindings; validate GRN issuance, current label printing and package-only managed-tag bypass controls; non-GRN issuance deferred.", "affected rollout scope", "Native model, observer and registration bridge"),
    ("D08", "Allocator deployment", "Infrastructure/deployment owner", "Use one authoritative allocator/DB; fence clones and restored backups. Independent installations require central coordination or approved new format.", "deployment enablement", "Revision-5 factory/counter design"),
]

FIELDS = [
    ("actor_and_scope", "issue", "yes_server", "GovAccess/native policy and active TenantContext", "existing", "service context plus registration snapshot", "authorize current office/company and all supplied IDs"),
    ("source_document_item_id", "issue", "yes_receipt", "locked saved receipt line", "existing UUID", "gov_asset_registrations.intake_item_id", "same document/type/state/office/company"),
    ("unit_index", "issue", "yes", "saved row_index", "existing EAV; new bridge field", "registration source-unit key", "stable ordinal, integral quantity and unique source unit"),
    ("model_id", "issue", "yes", "saved receipt product_id", "existing", "assets.model_id", "actual authorized individual asset model"),
    ("gov_code", "issue", "govt_tag_only (optional; absent means standard tag)", "active Finance row: product-type default or line-only choice", "490 provisional keys", "document meta; optional product-type Finance default in GovStore category metadata; registration gov_code (NULL for standard tags); snapshot", "six digit GGSSII; not physical asset identity; never blocks posting"),
    ("finance_source", "issue", "yes", "product_type / line_choice / none", "new", "document meta; registration snapshot", "line_choice only when the product type has no valid default"),
    ("format_version", "issue", "yes_server", "deployed allowlisted factory selection: fd18-v1 or native-v1", "implementation needed", "registration format_version", "unknown fails; no executable office upload"),
    ("acquisition_date", "issue", "govt_tag_only", "existing GRN Supplier Challan reference_date, else Purchase Order reference_date (D-09)", "existing reference fields; current capability does not copy to assets", "assets.purchase_date; snapshot", "encode MMYY only; Purchase receipts only until D-04; missing date = needs attention"),
    ("acquisition_reference", "issue", "govt_tag_only", "the challan or PO reference the date came from", "existing document references", "registration snapshot", "never invent a date; storekeeper may keep standard tags for the line instead"),
    ("tag_choice", "issue", "yes", "line tag panel: auto or keep standard tags", "new line meta", "document meta; snapshot reason", "standard choice recorded as chosen_by_storekeeper"),
    ("location_code", "issue", "yes_server", "office selection from the company location list", "14 source examples from one organization; real lists missing", "gov_metadata_values office subject (asset-tagging.office); company list (asset-tagging.codelists); snapshot", "two digit text chosen from a dropdown; one office per code per company; not BBS/CGA code"),
    ("division_code", "issue", "govt_tag_only", "office divisions selected from the company list; office default", "17 source examples; real mappings missing", "document meta selection; snapshot DD", "office-bound selection; not geographic division"),
    ("physical_asset_tag", "issue", "generated", "PHP formatter plus atomic counter (Finance tag) or native sequence under settings lock (standard tag)", "new service", "assets.asset_tag; existing bridge asset_tag with all-time uniqueness after preflight", "18-digit text or native prefix+number; all-time unique reserved value; immutable; typed tags rejected in enabled offices"),
    ("sequence", "issue", "generated", "global exact first14-prefix allocator", "new counter table", "gov_asset_tag_sequences", "0001..9999; no reset by office/company/version/century"),
    ("manufacturer_serial", "register", "where applicable", "physical manufacturer evidence", "existing serial_number EAV", "assets.serial", "not mandatory for furniture; not identity uniqueness key"),
    ("warranty_months", "register", "where applicable", "supplier warranty", "existing EAV", "assets.warranty_months", "nonnegative actual fact"),
    ("supplier_and_cost", "register", "existing receipt requirements", "actual receipt/source evidence", "existing native fields", "supplier_id and purchase_cost", "preserve current receipt rules; not format segments"),
    ("specification_and_manufacturer", "register", "where applicable", "actual product/physical unit", "native model/custom fields", "existing register/custom fields", "use metadata mappings rather than guessed custom column names"),
    ("unspsc_code", "classification", "optional", "reviewed existing commodity semantics", "470 unapproved leads; 224 covered items", "gov_finance_items.unspsc_code; snapshot", "nullable; multiple Finance items may share it; no reverse column"),
    ("office_geo_area", "context", "existing profile rules", "current GeoAreaService/profile", "existing current reference", "existing gov_location_profiles.geo_area_id", "unchanged; no 2013 import and no LL/DD substitution"),
    ("label", "label", "after committed issue", "saved native tag", "existing native printing", "existing label UI and audit", "reprint same identity; no label workflow tables"),
    ("legacy_tag", "migration", "when enabling existing scope", "native/bridge including deleted rows and physical checks", "reconciliation needed", "retained bridge reservation / collision deny-set", "preserve history; no arbitrary duplicate merge or automatic retag"),
    ("current_custody", "register", "current inventory rules", "native assignment/location", "existing", "existing native custody fields/history", "does not change encoded registration facts"),
    ("accounting_and_CGA_codes", "excluded_from_tagging", "no", "separate accounting/document modules if later required", "not required here", "none in tagging service", "depreciation, accounts and CGA file codes are not tag inputs"),
]


def build() -> tuple[dict[str, str], dict]:
    expect(sha(PDF) == SOURCE_SHA, "Source PDF hash differs; re-review provenance")
    raw = read_csv(SOURCE / "asset_code_catalog.csv")
    groups = read_csv(SOURCE / "group_codes.csv")
    nodes = read_csv(NODES)
    node_hash = sha(NODES)
    by_code = {r["code"]: r for r in nodes}
    expect(len(by_code) == len(nodes), "Duplicate UNSPSC code")
    for node in nodes:
        expect(bool(re.fullmatch(r"[0-9]{8}", node["code"])), "Invalid UNSPSC code width")
        level = int(node["level"])
        expect(1 <= level <= 4, "Unexpected UNSPSC level")
        if level == 1:
            expect(node["parent_code"] == "", "UNSPSC segment has unexpected parent")
        else:
            parent = by_code.get(node["parent_code"])
            expect(parent is not None and int(parent["level"]) == level - 1, "Missing/mismatched UNSPSC parent")
    keys = [r["group_code_from_annexure"] + r["subgroup_code"] + r["item_code"] for r in raw]
    expect(len(keys) == len(set(keys)), "Duplicate Finance group/subgroup/item key")
    group_set = {r["code"] for r in groups}
    expect({r["group_code_from_annexure"] for r in raw} == group_set, "Finance group coverage mismatch")
    for row in raw:
        for name in ["group_code_from_annexure", "subgroup_code", "item_code"]:
            expect(bool(re.fullmatch(r"[0-9]{2}", row[name])) and row[name] != "00", f"Invalid code: {row}")
        expect(103 <= int(row["source_pdf_page"]) <= 116, "Catalog page out of source range")

    buckets = collections.defaultdict(list)
    for row in raw:
        buckets[(row["group_code_from_annexure"], row["subgroup_code"])].append(row)
    collisions = []
    subgroups = []
    for (group, subgroup), rows in sorted(buckets.items()):
        labels = sorted({r["subgroup_name"] for r in rows})
        conflict = len(labels) > 1
        if conflict:
            collisions.append({"group_code": group, "subgroup_code": subgroup, "source_labels": labels, "source_pdf_pages": sorted({int(r["source_pdf_page"]) for r in rows})})
        subgroups.append({"subgroup_key": group + subgroup, "group_code": group, "subgroup_code": subgroup, "canonical_label_provisional": labels[0] if not conflict else "", "source_label_variants": " | ".join(labels), "source_pdf_pages": "|".join(str(p) for p in sorted({int(r["source_pdf_page"]) for r in rows})), "review_status": "blocked_label_collision" if conflict else "pending_authority_review", "blocking_issue_ids": "SOURCE-03" if conflict else "", "source_sha256": SOURCE_SHA})

    items = []
    for row, key in zip(raw, keys):
        issues = []
        if key.startswith("1123"):
            issues.append("SOURCE-03")
        if key == "010606" or (key.startswith("02") and int(row["item_code"]) >= 4):
            issues.append("SOURCE-04")
        items.append({"entry_key": key, "group_code": row["group_code_from_annexure"], "subgroup_code": row["subgroup_code"], "item_code": row["item_code"], "item_name": row["item_name"], "subgroup_name_source": row["subgroup_name"], "abbreviation_source": row["annexure_asset_abbreviation"], "source_pdf_page": row["source_pdf_page"], "source_manual_page": row["source_manual_page"], "review_status": "blocked_source_review" if issues else "pending_authority_review", "blocking_issue_ids": "|".join(issues), "source_sha256": SOURCE_SHA})

    # Build an inverted commodity-title index. Item-title overlap ranks search
    # leads, not semantic equivalence. Subgroup overlap is a small tie-breaker.
    # Physical assets should not be suggested construction/maintenance services
    # solely from title overlap. Allow goods plus real-estate/property segment 95;
    # intangible group 18 can also consider service codes, still pending review.
    commodities = [r for r in nodes if r["level"] == "4" and r["is_selectable"] == "1"]
    inverted = collections.defaultdict(set)
    node_tokens = {}
    for row in commodities:
        ts = tokens(row["title_en"])
        node_tokens[row["code"]] = ts
        for token in ts:
            inverted[token].add(row["code"])
    count = len(commodities)
    candidates = []
    crosswalk = []
    for item in items:
        query = tokens(item["item_name"])
        context = tokens(item["subgroup_name_source"])
        weights = {t: math.log(1 + count / (1 + len(inverted.get(t, ())))) for t in query}
        possible = set()
        for token in query:
            possible.update(inverted.get(token, ()))
        scored = []
        for code in possible:
            segment = int(code[:2])
            if item["group_code"] != "18" and not (10 <= segment <= 60 or segment == 95):
                continue
            matched = query & node_tokens[code]
            coverage = sum(weights[t] for t in matched) / max(sum(weights.values()), 1)
            precision = len(matched) / max(len(node_tokens[code]), 1)
            context_overlap = len(context & node_tokens[code]) / max(len(context), 1)
            score = .75 * coverage + .20 * precision + .05 * context_overlap
            if coverage >= .60 and score >= .50:
                scored.append((score, code, "title_token_overlap"))
        scored.sort(key=lambda r: (-r[0], r[1]))
        leads = []
        for code in CURATED.get(item["entry_key"], []):
            expect(code in by_code and by_code[code]["level"] == "4", f"Unknown curated commodity {code}")
            leads.append((None, code, "selected_search_lead_not_semantic_approval"))
        for lead in scored:
            if lead[1] not in {v[1] for v in leads}:
                leads.append(lead)
            if len(leads) >= 3:
                break
        for rank, (score, code, method) in enumerate(leads[:3], 1):
            node = by_code[code]
            candidates.append({"finance_entry_key": item["entry_key"], "finance_item_name": item["item_name"], "rank": rank, "unspsc_code": code, "unspsc_title": node["title_en"], "unspsc_level": node["level"], "unspsc_parent_code": node["parent_code"], "method": method, "lexical_score_not_probability": "" if score is None else f"{score:.6f}", "relation": "candidate", "approval_status": "not_approved", "finance_source_blockers": item["blocking_issue_ids"], "bundle_sha256": node_hash, "release_label_claim": "UNv260801", "release_provenance": "application_UI_label_unverified_upstream"})
        crosswalk.append({"finance_entry_key": item["entry_key"], "finance_item_name": item["item_name"], "source_pdf_page": item["source_pdf_page"], "finance_source_blockers": item["blocking_issue_ids"], "candidate_count": min(len(leads), 3), "selected_unspsc_code": "", "selected_unspsc_release": "", "relation_after_review": "", "qualifiers_or_no_equivalent_reason": "", "evidence_reference": "", "reviewed_by": "", "reviewed_at": "", "approval_status": "pending_review"})

    outputs = {}
    outputs["finance_groups.csv"] = csv_text(["group_code", "group_name", "source_pdf_page", "review_status", "source_sha256"], [{"group_code": r["code"], "group_name": r["name"], "source_pdf_page": r["source_pdf_page"], "review_status": "pending_authority_review", "source_sha256": SOURCE_SHA} for r in groups])
    outputs["finance_subgroups.csv"] = csv_text(list(subgroups[0]), subgroups)
    outputs["finance_items.csv"] = csv_text(list(items[0]), items)
    import_rows = [{"gov_code": r["entry_key"], "group_name": next(g["name"] for g in groups if g["code"] == r["group_code"]), "subgroup_name": r["subgroup_name_source"], "item_name": r["item_name"], "unspsc_code": "", "status": "held", "origin": "manual_extract", "source_reference": json.dumps({"pdf_sha256": SOURCE_SHA, "pdf_page": int(r["source_pdf_page"]), "issues": r["blocking_issue_ids"].split("|") if r["blocking_issue_ids"] else []}, separators=(",", ":")), "authority_reference": ""} for r in items]
    outputs["finance_items.import-draft.csv"] = csv_text(list(import_rows[0]), import_rows)
    outputs["unspsc_candidates.csv"] = csv_text(list(candidates[0]), candidates)
    outputs["finance_unspsc_review.csv"] = csv_text(list(crosswalk[0]), crosswalk)
    for filename, input_name, extra in [
        ("office_location_mapping.csv", "location_codes.csv", ["company_id", "office_location_id", "actual_registration_location_name", "effective_from", "order_reference"]),
        ("administrative_division_mapping.csv", "division_codes.csv", ["company_id", "office_location_id", "administrative_division_key", "actual_administrative_division_name", "effective_from", "order_reference"]),
    ]:
        rows = []
        for row in read_csv(SOURCE / input_name):
            rows.append({"source_example_code": row["code"], "source_example_label": row["name"], "source_pdf_page": row["source_pdf_page"], **{key: "" for key in extra}, "approved_code": "", "approval_status": "unmapped_source_example_not_live_organization"})
        outputs[filename] = csv_text(list(rows[0]), rows)
    empty_templates = {
        "organization_onboarding.csv": ["office_location_id", "legal_register_owner", "adoption_reference", "sequence_scope_reference", "single_allocator_reference", "legacy_preflight_reference"],
        "product_type_finance_mapping.csv": ["product_type_category_id", "governance_scope", "owning_company_id", "unspsc_code", "gov_code", "specification_reference", "reviewed_by", "status"],
        "legacy_reconciliation.csv": ["native_asset_id", "company_id", "office_location_id", "existing_native_tag", "physical_label_observed", "source_document_item_id", "proven_unit_index", "gov_code", "date_evidence", "duplicate_or_scope_conflict", "review_evidence", "reviewed_by", "approval_status"],
        "receipt_unit_intake.csv": ["source_document_item_id", "unit_index", "finance_source", "gov_code", "tag_choice", "standard_tag_reason", "division_code", "manufacturer_serial", "warranty_months"],
    }
    for name, fields in empty_templates.items():
        outputs[name] = csv_text(fields, [])
    decisions = [{"decision_id": d[0], "topic": d[1], "proposed_responsible_owner": d[2], "decision_required": d[3], "blocks": d[4], "evidence": d[5], "status": "open", "authority_reference": "", "approved_by": "", "approved_at": ""} for d in DECISIONS]
    outputs["policy-decisions.csv"] = csv_text(list(decisions[0]), decisions)
    outputs["source-issues.json"] = json_text({
        "status": "source_review_findings_not_authoritative_corrections",
        "scope_note": "Known findings only; not an exhaustive semantic review of all source items.",
        "issues": [
            {"id": "SOURCE-01", "description": "Furniture group is 05 in group list but 03 in worked example", "pdf_pages": [89, 99], "blocks": "use_of_worked_example_as_authority", "decision": "D02"},
            {"id": "SOURCE-02", "description": "Table subgroup is 08 in code list/Annexure 5 but 01 and item 19 Employee Table in worked example", "pdf_pages": [91, 99, 106], "blocks": "use_of_worked_example_as_authority", "decision": "D02"},
            {"id": "SOURCE-03", "description": "Group 11 subgroup 23 has both HUB and Server source labels", "pdf_pages": [110], "affected_item_keys": ["112331", "112332"], "blocks": "affected_subgroup_and_items", "decision": "D02"},
            {"id": "SOURCE-04", "description": "Questionable source pairings: Pond / IPS Rack and Land Improvement entries for sensors, Farming land, IPS Rack and Parks", "pdf_pages": [103], "affected_item_keys": ["010606", "020404", "020405", "020506", "020607", "020708"], "blocks": "affected_items_pending_semantic_review", "decision": "D02"}
        ],
        "resolved_derived_corrections": [{"description": "18-digit segment total and worked example previously described as 16 digits in derived documentation", "pdf_pages": [88, 99], "status": "derived_description_corrected_source_values_preserved"}]
    })
    fields = [{"field": r[0], "purpose": r[1], "required_when": r[2], "authoritative_source": r[3], "current_coverage": r[4], "proposed_target": r[5], "validation_or_limit": r[6]} for r in FIELDS]
    outputs["field-requirements.csv"] = csv_text(list(fields[0]), fields)

    example = {"source_document_item_id": "00000000-0000-4000-8000-000000000001", "unit_index": 0, "finance_source": "product_type", "gov_code": "090809", "tag_choice": "auto", "standard_tag_reason": None, "division_code": "09", "manufacturer_serial": None, "warranty_months": None}
    outputs["receipt-unit.example.json"] = json_text(example)
    contract = json.loads((PACKAGE / "contracts/receipt-unit.schema.json").read_text(encoding="utf-8"))
    expect(set(contract["required"]) <= set(example) <= set(contract["properties"]), "Receipt example fields disagree with revision-7 contract")
    expect(all(r["status"] == "held" and not r["unspsc_code"] and not r["authority_reference"] for r in import_rows), "Draft import must not invent activation or UNSPSC mapping")
    outputs["format-examples.json"] = json_text({"status": "demonstration_not_issued", "positive": [{"asset_tag": "090809102601090001", "gov_code": "090809", "purchase_date": "2026-10-01", "location_code": "01", "division_code": "09", "sequence": 1}], "negative": [{"input": "030119012201090001", "reason": "source_worked_example_conflicts_with_codebook; structurally 18 digits but not an approved item"}, {"input": "090809132601090001", "reason": "invalid_month"}, {"input": "090809102601090000", "reason": "zero_sequence"}, {"input": "90809102601090001", "reason": "lost_leading_zero_wrong_length"}], "organization_codes": "fictional_example_mappings_only"})

    policy = json.loads((PACKAGE / "config/tagging-policy.proposed.json").read_text(encoding="utf-8"))
    spec = json.loads((SOURCE / "tagging_specification.json").read_text(encoding="utf-8"))
    expect(policy["issuance_enabled"] is False and policy["approved_by"] is None, "Design policy must not enable issuance or fabricate approval")
    expect(sum(s["width_digits"] for s in spec["segments"]) == spec["total_width_digits"] == 18, "Finance segment total must be 18")
    expect(len(spec["worked_example"]["tag"]) == 18, "Source worked example length must be 18")
    sample = "090809102601090001"
    expect(re.fullmatch(policy["finance"]["pattern"], sample) is not None, "Valid Finance sample rejected")
    expect(re.fullmatch(policy["finance"]["pattern"], "090809132601090001") is None, "Invalid month accepted by pattern")
    expect(sample[:6] in keys and sample[6:10] == "1026" and sample[10:12] == "01" and sample[12:14] == "09" and sample[14:] == "0001", "Finance segment positions disagree")
    expect(spec["worked_example"]["tag"][:6] not in keys, "Worked example conflict expectation changed; re-review source")
    expect(all(c["unspsc_code"] in by_code and c["unspsc_level"] == "4" and c["approval_status"] == "not_approved" for c in candidates), "Candidate membership/approval invariant failed")
    expect(len({r["entry_key"] for r in items}) == len(crosswalk) == len(raw) == 490, "Finance coverage changed; re-review expected design counts")
    expect(len(groups) == 18 and len(buckets) == 171, "Group/subgroup count changed; re-review normalization")
    expect(len(collisions) == 1 and collisions[0]["group_code"] == "11" and collisions[0]["subgroup_code"] == "23", "Subgroup conflict set changed; re-review")
    candidate_items = len({c["finance_entry_key"] for c in candidates})
    report = {
        "as_of": AS_OF,
        "scope": "offline design/source validation only; no application, live database, policy approval or production issuance",
        "structural_checks": "passed",
        "production_readiness": "blocked_pending_policy_data_and_implementation",
        "source_pdf_sha256": SOURCE_SHA,
        "pdf_page_count": 170,
        "pdf_page_count_evidence": "pypdf checked during design review; this stdlib validator verifies exact file hash",
        "visual_review_pdf_pages": [88, 99, 103, 110],
        "finance": {"groups": len(groups), "subgroup_keys": len(buckets), "items": len(items), "duplicate_full_item_keys": len(keys)-len(set(keys)), "missing_or_invalid_two_digit_codes": 0, "tag_width_digits": 18, "subgroup_label_collisions": collisions, "flagged_source_item_rows": sum(bool(r["blocking_issue_ids"]) for r in items), "approved_item_rows": 0},
        "unspsc": {"nodes": len(nodes), "levels": dict(sorted(collections.Counter(r["level"] for r in nodes).items())), "duplicate_codes": 0, "bundle_sha256": node_hash, "release_label_claim": "UNv260801", "upstream_release_provenance": "unverified; label found in import UI, not encoded in CSV", "candidate_rows": len(candidates), "finance_items_with_candidates": candidate_items, "finance_items_without_candidates": len(items)-candidate_items, "approved_crosswalk_rows": 0, "candidate_method": "up to three title-token search leads per Finance item; three manually selected search leads; no semantic equivalence asserted"},
        "organization": {"location_source_examples": 14, "division_source_examples": 17, "completed_organization_onboarding": 0, "approved_live_office_maps": 0, "approved_product_type_maps": 0, "native_ids_in_templates": 0},
        "policy_decisions_open": len(decisions),
        "required_fields_documented": len(fields),
        "checks": ["source hash", "unique complete Finance keys", "group coverage", "subgroup label variants retained", "UNSPSC code widths and parent levels", "Finance width and segment offsets", "month pattern", "worked example conflict", "candidate existence/level/no approvals", "all Finance rows have review worksheet", "issuance disabled"],
        "not_verified": ["all-row semantic correctness", "manual applicability", "live organization/model/legacy inventory", "upstream UNSPSC release/rights", "reviewer-approved crosswalks", "application runtime tests", "MySQL concurrency", "UI and label/printer behavior", "CI or production readiness"]
    }
    outputs["validation-report.json"] = json_text(report)
    input_files = [PDF, SOURCE / "asset_code_catalog.csv", SOURCE / "group_codes.csv", SOURCE / "location_codes.csv", SOURCE / "division_codes.csv", SOURCE / "tagging_specification.json", NODES, PACKAGE / "config/tagging-policy.proposed.json", PACKAGE / "contracts/receipt-unit.schema.json", PACKAGE / "contracts/product-default.schema.json", PACKAGE / "data-model.json", Path(__file__).resolve()]
    manifest = {"as_of": AS_OF, "status": "provisional_design_data_not_importable_production_bundle", "generated_by": "prepare_design_data.py --write", "input_sha256": {p.relative_to(ROOT).as_posix(): sha(p) for p in input_files}, "generated_file_sha256": {name: hashlib.sha256(value.encode("utf-8")).hexdigest() for name, value in sorted(outputs.items())}, "csv_convention": "UTF-8, comma separated, all identifiers/codes are strings; import as text to preserve zeros", "upstream_sources_checked": [{"url": "https://www.undp.org/unspsc", "checked_date": AS_OF, "use": "UNSPSC purpose and current download location only; not release verification of local bundle"}, {"url": "https://eprocurement.nc.gov/training/selecting-commodity-codes", "checked_date": AS_OF, "use": "four core two-digit hierarchy levels"}], "source_values_silently_corrected": False, "production_issuance_enabled": False}
    outputs["design-data-manifest.json"] = json_text(manifest)
    return outputs, report


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--write", action="store_true", help="generate named design data; overwrites generated review worksheets")
    mode.add_argument("--check", action="store_true", help="verify source invariants and exact generated content without writes")
    args = parser.parse_args()
    outputs, report = build()
    for name, content in outputs.items():
        path = HERE / name
        if args.write:
            path.write_bytes(content.encode("utf-8"))
        else:
            expect(path.exists() and path.read_bytes() == content.encode("utf-8"), f"Generated file differs or is missing: {name}")
    print(json.dumps({"result": "generated_and_validated" if args.write else "validation_passed", "generated_files": len(outputs), "finance_items": report["finance"]["items"], "subgroup_keys": report["finance"]["subgroup_keys"], "candidate_rows": report["unspsc"]["candidate_rows"], "items_with_candidates": report["unspsc"]["finance_items_with_candidates"], "approved_crosswalk_rows": 0, "issuance_enabled": False}))


if __name__ == "__main__":
    main()
