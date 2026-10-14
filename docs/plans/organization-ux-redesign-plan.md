# Gov-Store Organization — Human-Centred UX Redesign Plan

> **Scope:** every screen of `packages/gov-store/organization`: office registry, create, onboard, office hub, lifecycle, local
> configuration, ICT jurisdictions, company (ministry) admins, government directory import, and the two staff-facing
> readiness pages.
> **Status:** Phases 0–3 delivered, Phase 4 partly (branch `feature/civic-ledger-theme`, uncommitted) · live UI
> check, axe pass and bn-BD copy review (D5) pending · **Date:** 2026-10-14
> **Design artifact:** https://claude.ai/artifact/UVEpbBykRxBvB9XzQD6C5A · snapshot in
> [`organization-ux-redesign/`](organization-ux-redesign/README.md)
> **Reference model:** `docs/plans/dynamic-theming-plan.md` (tokens, `<x-gs::*>` kit, compliance ratchet) and
> `docs/plans/navigation-menu-plan.md` (task-based IA, en/bn glossary).

---

## 1. Summary

The Organization package works and its services are sound: readiness checks, scoped geo search, duplicate checks, and
concurrency-guarded lifecycle changes. **The UI asks people to think like the database.** It scores **42 / 100** for
human-centred design (§2).

The theming migration of 2026-10-08 removed the package's colour literals. It did **not** move the views onto the
`<x-gs::*>` kit. They still use AdminLTE boxes, `label-*` badges, **88 one-off `org-inline-*` classes** and
**61 `!important` declarations** in `organization.css`. Because of that, office status ignores the `status_style`
variant, density and radius never apply, and the one kit component that renders validation errors (`<x-gs::form-row>`)
is never used.

**Design direction:** the Civic Ledger look ("calm, legible, bilingual by default") is now a real theme. A new
**workflow kit** (readiness, role slot, action row, choice card, trail) lives in `gov-store/theming`. The redesign
rebuilds every Organization screen on the kit, so it works in all 6 themes × light/dark. The redesign:

1. **Says what's next.** Five human status words replace the lifecycle codes, and each comes with its next step.
2. **Treats both languages equally.** Every string goes through `orglabel`, and nothing needs English input.
3. **Lets people fix things where they see them.** Every open readiness item is a button.
4. **Makes risky actions calm and deliberate.** Each lifecycle action gets its own explained dialog, plus undo where possible.
5. **Respects daily users.** Instant filters, whole-row links, 44 px targets, and phone layouts.

The services stay as they are. Two small service changes are needed (§7, D2 and D3).

---

## 2. Audit (2026-10-14)

### Score (heuristic review, out of 10)

| Heuristic | Score | Heuristic | Score |
|---|---|---|---|
| Status is visible | 6 | Control and undo | 4 |
| Error prevention | 6 | Consistency | 4 |
| Recognition over recall | 5 | Speed for daily users | 4 |
| Focused layout | 5 | Plain language (EN + বাংলা) | 3 |
| Accessibility | 3 | Error messages and recovery | 2 |

**Total 42 / 100** · 2 critical · 5 high · 5 medium.

### Keep

The readiness model ("goes live when 4 things are true"), the live duplicate check, jurisdiction-scoped area search,
typed confirmation with `expected_status` / `expected_geo_area_id` concurrency guards, full en-US/bn-BD key parity
(409 keys each), and the per-office activity log.

### Findings

Severity: **C** = users lose work or are blocked · **H** = visibly wrong or risky · **M** = friction.

| # | Sev | Finding | Evidence | Fix (phase) |
|---|---|---|---|---|
| F1 | C | Form errors are invisible. No view uses `@error` / `$errors`. The ministry, parent and area selects never use `old()`, so a failed save wipes them. | `create`, `onboard`, `hub`, `configuration`, `jurisdictions`, `company-admins` views | `<x-gs::form-row>` everywhere, an error summary, and `old()` on every field (P1) |
| F2 | C | About 40 hard-coded English strings: hub role labels, "Operational Readiness Checklist", "Needs: ✓ Primary", "View Hub", "Onboard Office", "Executed by", both readiness pages in full, select2 placeholders. Lifecycle requires typing `CHANGE`. | `hub`, `index`, `readiness/*`, `jurisdictions`, `onboard`, `lifecycle` views · `OfficeLifecycleService.php:25` | All strings to `orglabel`. Confirmation becomes a checkbox (D2) (P1) |
| F3 | H | The package bypasses the theming kit (see §1). | `organization.css`, every view | Rebuild on `<x-gs::*>` (P1–P3) |
| F4 | H | Status words describe the database. "Configured" means "admin assigned, roles missing". The config page prints `strtoupper(lifecycle_status)`. | `OfficeReadinessService.php:51`, `configuration/index` | Office statuses in `status-map.php` (done, P0) + `<x-gs::status-badge>` (P1) |
| F5 | H | The waiting page always shows "Office Administrator Designated: Completed", whatever the state. The no-office page tells staff to fix things "inside Snipe-IT". | `readiness/waiting.blade.php`, `readiness/unassigned.blade.php` | Build from the readiness checklist and name a real contact (P1) |
| F6 | H | The lifecycle form is always visible under the hub tabs. One `<select>` mixes Suspend, Resume and Relocate, and the geo field shows even when it doesn't apply. Revokes are full-width red buttons with only a browser `confirm()`. | `provisioning/lifecycle`, `jurisdictions`, `company-admins` | Settings section + one dialog per action, quiet row actions, undo toast (P2–P3) |
| F7 | H | "Create" vs "Onboard" forces a technical choice up front: users must know whether a legacy Location exists. | `provisioning/index` | One "Add office" that searches existing locations first (P2) |
| F8 | M | Read-only fields look like inputs: disabled selects for ministry, parent and area in the hub, and an always-disabled "Delegate admin" select on create. | `hub`, `create` | `<x-gs::key-value>` + "why/how to change" text (P2) |
| F9 | M | The readiness checklist is built 3 times with 3 vocabularies (Ready/Missing · Configured/Missing · Assigned/Awaiting setup). Items aren't actionable. | `hub`, `configuration/index`, `readiness/waiting` | One `<x-gs::readiness>` (done, P0) used everywhere (P1) |
| F10 | M | Jargon and internals: "Spatial-First Principle", `gov_geo_areas`, raw `hid` path codes, "Snipe-IT's core catalog", "Apply office transition", "Starter catalog". | `orglabel.php`, hub Geography tab | Rewrite copy (§3.4); IDs behind "Technical details" (P1) |
| F11 | M | Registry: the KPI tiles aren't filters, filtering needs a Filter click, there's no "Not onboarded" filter, `btn-xs` targets, rows aren't links, and "Needs ✓/✗" is colour-led. | `provisioning/index` | `<x-gs::status-tabs>` with counts, instant filter, whole-row link, "Next step" column (P2) |
| F12 | M | No `aria-*` anywhere, decorative icons read aloud, colour-only signals, no table captions, small targets. | all views | WCAG 2.2 AA via the kit, plus an axe pass (P1–P4) |

### Journey today: from nothing to a live office

| Stage | Pain point (user's words) |
|---|---|
| 1 · Add | "Create or Onboard? I don't know which one I need." |
| 2 · Place | "I typed Savar and got three results. Which one is mine?" (results show no hierarchy) |
| 3 · Lead | "The admin field is greyed out. Why?" |
| 4 · Roles | "The dropdown is empty. Nobody's been added yet." |
| 5 · Staff | "It says the admin step is done, but I still can't order anything." |
| 6 · Live | "Nothing told me it worked." |

---

## 3. Design direction

### 3.1 Principles

1. **Tell people what's next.** Every screen answers "what do I do now?" before "what is the data?".
2. **Two languages, one experience.** Every word, number and confirmation works the same in বাংলা and English.
3. **Fix it where you see it.** A missing role, an unverified area, an empty staff list: each has its button right there.
4. **Make risky actions calm.** Explain consequences in plain words, ask once, and offer undo where possible.
5. **Respect the daily user.** Instant filters, keyboard-friendly, dense where it helps, never cluttered.

### 3.2 Look: Civic Ledger theme (delivered)

`packages/gov-store/theming/themes/civic-ledger/` — draft v1.0.0, extends `default`.

- Paper ground `#F5F6F3`, white surfaces, a **white neutral header**, deep civic green `#0B5E45` for primary action only,
  and a **blue focus ring** `#1D4F91` that can't be mistaken for "selected".
- Status colours use separate OKLCH lightness levels. `gs-theme:validate` reports **3 warnings**, fewer than any shipped
  theme, and the remaining pairs (blue/red, blue/amber) stay distinct for colour-vision deficiencies.
- Noto Sans + Noto Sans Bengali + IBM Plex Mono. Variants: `header neutral · sidebar dark · density regular ·
  table rules · surfaces card · controls outlined · radius lg · status_style tinted`.
- The theme is a look, not a requirement. **Every redesigned screen must also work in the other 5 themes and in dark
  mode.** The canvas boards have Theme and Mode tweaks to check this.

### 3.3 Status vocabulary (delivered in `status-map.php`)

| `lifecycle_status` | Badge (en) | Badge (bn) | Tone · icon | Next step shown |
|---|---|---|---|---|
| `unconfigured` | Not onboarded | যুক্ত হয়নি | `status-archived` · `fa-map-location-dot` | Place it on the map |
| `provisioned` | Needs administrator | প্রশাসক প্রয়োজন | `status-pending` · `fa-user-plus` | Choose an administrator |
| `configured` | Setting up | সেটআপ চলছে | `info` · `fa-list-check` | First missing role |
| `operational` | Live | চালু | `status-ready` · `fa-circle-check` | — |
| `suspended` | Paused | স্থগিত | `status-undeployable` · `fa-circle-pause` | Resume when ready |

Render with `<x-gs::status-badge :status="'office_'.$profile->lifecycle_status" />`.

### 3.4 Glossary (copy rules)

| Today | Use instead |
|---|---|
| Provision / Provisioned | Add office / Needs administrator |
| Configured / Operational | Setting up / Live |
| Suspend request intake | Pause new requests |
| Relocate within authorized territory | Move to another area |
| Apply office transition | (the action's own verb: Pause requests, Move office) |
| Primary Approver (Supervisor) · Final Approver | Approver · Second approver (optional) |
| Storekeeper (Inventory Officer) | Storekeeper |
| Starter catalog | Starter items |
| Geographic area / Territory tag / Spatial | Area / Location |
| Executed by | by |
| ICT Officer Jurisdiction | Area access / Area ICT officer |
| Company admin | Ministry administrator |

The Bangla glossary needs a native reviewer (D5). Draft terms are on the canvas (boards 3b, 8).

### 3.5 Information architecture

One **Organization** section with three tabs, replacing six unrelated pages:

- **Offices:** registry → office hub (People and roles · Staff · Office details · Location · Activity · Settings).
- **Administrators:** "Area ICT officers" and "Ministry administrators" as two tabs of one page, with a side drawer to give access.
- **Government directory:** the import console, with a preview/dry-run summary before import.

The local `gov-store/office` configuration page becomes the same office hub, scoped to the user's own office. One screen
serves both roles; its actions are filtered by permission.

---

## 4. Screen specifications (canvas boards)

| Board | Screen | Replaces | Kit components | Key behaviour |
|---|---|---|---|---|
| 3 | Offices registry | `provisioning/index` | `page-header`, `status-tabs`, `alert`, `table`, `progress`, `status-badge`, `empty-state` | Tabs All · Needs your action · Setting up · Live · Paused · Not onboarded, with counts. Instant search across en/bn names. The whole row links to the hub (unonboarded rows go to Add office). A "Next step" column. A "N offices are waiting on you" banner. |
| 3b | Registry on a phone | — | same, card layout under 768 px | No sideways scroll; status, progress and next step stay visible. |
| 4 | Add office (5 steps) | `create` + `onboard` | `stepper` (vertical), `form-row`, `choice-card`, `trail`, `alert`, `key-value`, `empty-state` | 1 Find existing (search legacy locations → "Set up this one" or "Create new") · 2 About (en name, bn name, type as choice cards) · 3 Location (results show the full trail, the duplicate warning links to the existing office) · 4 Reports to (both optional) · 5 Check and create ("Change" per section, "What happens next"). Success screen → "Add staff". |
| 5 | Office hub | `hub`, `configuration/index`, `lifecycle` | `page-header` (meta + status), `readiness`, `role-slot`, `table`, `key-value`, `alert`, `trail`, `timeline`, `action-row` | Readiness card at the top whose open item is the primary action. Role slots show their holder or an empty dashed slot with "Choose from N staff". Read-only details explain how to change them. Location asks "Yes, this is correct / It's wrong". Activity reads as sentences. Settings has Pause, Move, and Close/merge (disabled with its reason). |
| 6 | Pause / Move dialogs | `lifecycle` form | dialog surface, `trail`, `btn` | Pause: what stops, what continues, what locks, reason (required, counter) and an "I understand" checkbox. Move: current vs new area with trail, area search scoped to the user's areas, consequences, reason. |
| 7 | Administrators | `jurisdictions`, `company-admins` | `page-header`, `status-tabs`, `table`, `avatar`, `alert` | Quiet "Manage" row action instead of red Revoke. A drawer with person and area pickers and a plain-language preview ("Rahim will be able to … in Gazipur District: 5 upazilas, 24 offices"). Undo toast after removal. |
| 8 | Staff before go-live | `readiness/waiting`, `readiness/unassigned` | `readiness` (readonly), `avatar`, `btn` | Waiting: real checklist, named contact with email, "we'll email you when it's live". No office: what to do in 3 steps, a copyable username, and the area ICT officer's contact. |

---

## 5. Workflow kit (delivered in Phase 0)

| Component | Purpose | Files |
|---|---|---|
| `<x-gs::readiness :items title caption compact readonly>` | "N of M done" + meter + rows; open items carry their fix; optional items don't count | `theming/resources/views/components/readiness.blade.php` |
| `<x-gs::role-slot role description person meta required optional action-label action-href>` | One responsibility and its holder; empty slots are dashed and lead with the action; "Needed" said in words | `…/role-slot.blade.php` |
| `<x-gs::action-row title description unavailable id>` | Settings / danger-zone row; `unavailable` explains why, linked via `aria-describedby="{id}-why"` | `…/action-row.blade.php` |
| `<x-gs::choice-card name value label description icon checked type>` | Large labelled radio/checkbox; the native input stays in the tab order | `…/choice-card.blade.php` |
| `<x-gs::trail :items>` | Division › District › Upazila path with optional bn; separators hidden from screen readers | `…/trail.blade.php` |

Styles: `theming/components/16-workflow.css` (`@layer gs-components`, tokens only). Lang keys (`kit.readiness_*`,
`kit.needed`, `kit.optional`, `status.office_*`) in en-US and bn-BD. Theme Lab: section 7 "Office setup (workflow kit)".
Tests: `tests/Feature/GovStore/Theming/WorkflowKitComponentsTest.php` (8 tests). The theming suite passes with 108 tests,
including compliance. Three tests that hard-code the theme list were updated for `civic-ledger`.

---

## 6. Implementation phases

Each phase ships on its own. Organization services keep their public behaviour unless a decision in §7 says otherwise.

### Phase 0 — Theme and kit ✅ (2026-10-14)

Civic Ledger theme, workflow kit, office statuses, Lab demo, tests (§3.2, §5). **Remaining:** commit and open a PR from
`feature/civic-ledger-theme`, then review in `/gov/theme-lab/civic-ledger` (needs a superuser).

### Phase 1 — Correctness and language (fixes F1, F2, F4, F5, F9, F10) ✅ (2026-10-14)

Delivered: every form uses `<x-gs::form-row>` with `old()` on every field and an error summary
(`partials/errors`). The new string file `lang/{en-US,bn-BD}/office.php` (key parity tested) covers all redesigned
screens, and controller messages are translated. Status everywhere comes from `<x-gs::status-badge>`. One readiness
partial (`partials/readiness`) is used by the hub, My office and the staff waiting page (read-only, built from real
state). Jargon is rewritten; area codes sit behind "Technical details". Validation field names are translated.

Original tasks:

1. Replace every form group with `<x-gs::form-row>` and add `old()` to every field, select2 included (preselected option).
2. Add an error summary (`<x-gs::alert tone="danger">` listing `$errors` with anchors) at the top of each form.
3. Move every hard-coded string to `orglabel` (en-US + bn-BD), including JS placeholders (`@json(__())`).
4. Status: `<x-gs::status-badge :status="'office_'.$status" />` everywhere. Delete the per-view `@if` badge ladders.
5. Readiness: one partial `govorg::partials.readiness` building `<x-gs::readiness>` items from
   `OfficeReadinessService` output (`has_users`, `has_office_admin`, `has_primary_approver`, `has_storekeeper`), with
   action hrefs per permission. Use it in the hub, configuration and waiting views (`readonly` for staff).
6. Rewrite `readiness/waiting` and `readiness/unassigned` from real data (fixes the hard-coded "Completed").
7. Copy pass per §3.4. Move the hierarchy `hid` and area codes into a `<details>` "Technical details".

**Accept:** a failed create keeps every value and shows the message under the field; a bn-BD session shows no English on
any Organization page; the waiting page matches the checklist; compliance baseline stays `{}`.

### Phase 2 — Registry, Add office and hub layout (fixes F3, F7, F8, F11) ✅ (2026-10-14)

Delivered: the registry has status tabs with server counts, a "Needs your action" filter, instant filters, whole-row
links, a Setup column with the next step (`OfficeRegistryViewModel::nextStep`), and en/bn area names. Add office
(`create`) starts by searching existing locations ("Set up this one" → onboard) and then shows the shared
`partials/office-form`. `onboard` uses the same form. The always-disabled administrator select is gone. The duplicate
check links to the existing office. The area search (`gov.org.provisioning.geo-search`, server-scoped) returns each
result's parent path. The hub has a section nav, a readiness card, role slots with in-place pickers, staff, details
(read-only with how-to-change notes and a rename form), location confirmation, starter items, activity sentences and
settings. Every Organization GET route has a breadcrumb. `organization.css` went from 398 lines with 61 `!important`
and 88 `org-inline-*` classes to a layout-only sheet; `!important` remains only to reset Bootstrap element margins.

Original tasks:

1. Registry → `page-header` + `status-tabs` (server-side counts per status, plus "Needs your action" =
   unconfigured ∪ provisioned ∪ configured) + `table` with a "Next step" column; debounced GET search (no Filter button);
   whole-row link.
2. Merge `create` and `onboard` into one stepped flow (D1). Step 1 searches unprovisioned Locations. Keep both POST
   endpoints, chosen by the path the user takes.
3. Duplicate check: return `{id, name, status}` and link "Open that office instead".
4. Hub → sections as on board 5. Show ministry, parent and area as `<x-gs::key-value>` with "how to change" text.
   Role slots open a person picker limited to local staff, with "Add staff" as the empty state.
5. Delete `organization.css` rules that restyle AdminLTE (`.govorg-theme .box …`) once no view uses them. Drop the
   `org-inline-*` classes; layout comes from kit classes or a small `org-*` layout sheet with no colours.

**Accept:** 0 `org-inline-*` classes, `organization.css` only does layout, and every screen checked in Theme Lab focus
view across 6 themes × 2 modes.

### Phase 3 — Safe actions (fixes F6) ✅ (2026-10-14)

Delivered: Settings uses `<x-gs::action-row>` for Pause/Resume, Move and Close/merge (disabled with its reason).
Each action has its own Bootstrap modal with plain consequences, a required reason and an acknowledgement checkbox. A
dialog that fails validation reopens with its message. D2 is applied: `OfficeLifecycleService` now validates
`confirmation` as `accepted`. National changes (area access, ministry administrators, directory import) still go
through tenant-scope's `NationalChangeReview` page **unchanged**, including its typed confirmation, as AGENTS.md
requires. So D3 is implemented as a **Restore access** button after a removal. It submits the previous assignment,
which goes through the same national review. Red full-width revoke buttons and `confirm()` are gone.

Original tasks:

1. Settings section with `<x-gs::action-row>` for Pause/Resume, Move and Close/merge (disabled, with reasons from
   clearance counts).
2. One dialog per action (board 6). Keep `expected_status` / `expected_geo_area_id`. Replace the typed `CHANGE` with an
   acknowledgement checkbox (D2).
3. Administrators page (board 7): two tabs, a drawer form, a quiet "Manage" menu with Remove. On removal, redirect with a
   flash that offers **Undo** (re-grant within the session) (D3).

**Accept:** no destructive control shows by default, every risky action names its consequences, no `confirm()` left.

### Phase 4 — Mobile, accessibility and verification ◐ (partly, 2026-10-14)

Delivered: wide tables scroll inside `gs-table-wrap`, layouts use wrapping flex columns (no fixed widths), and the kit
supplies 44 px coarse-pointer targets. Tests: `tests/Feature/GovStore/OrganizationUxTest.php` (6 tests: en/bn parity,
next-step order, tab counts and filters, area paths in both languages, form keeps values and shows errors, read-only
staff checklist). `OrganizationWorkflowTest` was updated for the checkbox confirmation and the new dialogs. The full
GovStore suite passes: 351 tests, 5,882 assertions, with `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`
(see AGENTS.md). Compliance baseline still `{}`.
Live journeys: suite `organization` (`tests/live/subtests/organization/`, run with `npm run test:live -- run --suite
organization`) checks the registry, hub, Pause dialog guard, Add office validation, My office and role/area boundaries
against the database. Five journeys pass on the seeded dataset. `organization.national-admins-observation` is blocked
there because the dataset has no super user. All of them are read-only: no office is created, paused or moved.
**Pending:** an axe/Lighthouse pass, registry card layout under 768 px (tables scroll today), a write journey for
nothing → live office and Pause/Resume, bn-BD live coverage (no session language switch exists), and the bn-BD copy
review (D5). The staff waiting / no-office pages can't be reached live, because `EnsureOfficeIsOperational` is not
registered on any route.

Original tasks:

1. Registry and hub card layouts under 768 px (board 3b). No horizontal page scroll.
2. Axe / Lighthouse pass on every screen in light and dark. Target WCAG 2.2 AA, 44 px targets on coarse pointers (the kit
   already enforces this).
3. Feature tests per screen: old input survives validation, bn-BD rendering, status badges, readiness actions by
   permission, lifecycle dialog validation.
4. Live journey test (see `json-live-journey-testing-plan.md`): nothing → live office, in en and bn.

---

## 7. Decisions needed

| # | Decision | Recommendation |
|---|---|---|
| D1 | Merge Create and Onboard into one "Add office" flow? | **Applied.** Search existing locations first. Both store endpoints kept. |
| D2 | Replace `confirmation: required\|in:CHANGE` with an acknowledgement checkbox? | **Applied** for office lifecycle (`accepted`). National review keeps its typed confirmation (AGENTS.md). |
| D3 | Undo for revoking ICT officer / ministry admin access? | **Applied as "Restore access"**: re-submits the previous assignment through the national review. |
| D4 | Publish Civic Ledger and make it `GS_THEME_DEFAULT`? | Publish after Lab review. Choosing the default is an organisation decision; `institutional-green` stays default until then. |
| D5 | Bangla copy owner | A native reviewer signs off on the §3.4 glossary and the bn strings before Phase 1 merges (shared with navigation plan D3). |

---

## 8. Artifact

- **Live canvas:** https://claude.ai/artifact/UVEpbBykRxBvB9XzQD6C5A (private until shared from its Share menu).
- **Snapshot:** [`docs/plans/organization-ux-redesign/artifact/`](organization-ux-redesign/artifact/) (9 boards +
  `canvas.json`); see its [README](organization-ux-redesign/README.md) for how the boards load the kit CSS.
- Boards 2–8 use the real `.gs-*` markup and the built kit and theme CSS. When the kit changes, rebuild
  (`php artisan gs-theme:build`), re-upload the bundle to the canvas, and update the `/_blob/…` link.
