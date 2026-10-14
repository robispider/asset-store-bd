# Gov-Store Office Membership — Human-Centred UX Redesign Plan

> **Scope:** every screen of `packages/gov-store/office-membership`: My office memberships (`user/index`), Staff
> management (`admin/staff`), the Emergency override console (`admin/override_console`), membership notices
> (`hooks/notices`) and the navbar office chip (`hooks/office-chip`), plus the five workflows behind them.
> **Status:** P0–P4 delivered, P5 partly (branch `feature/civic-ledger-theme`, uncommitted) · browser check, axe pass,
> live write journeys and bn-BD copy review (D5) pending · **Date:** 2026-10-14
> **Design artifact:** https://claude.ai/artifact/4i72wRFDSiMQw8TcUCkRiD · snapshot in
> [`office-membership-ux-redesign/`](office-membership-ux-redesign/README.md)
> **Reference model:** [`organization-ux-redesign-plan.md`](organization-ux-redesign-plan.md) (same kit, theme and method).

---

## 1. Summary

The services are careful: a pluggable clearance engine, consent-based handovers, row locks, clearance re-checked at
request, sign-off and claim, a notices outbox and an override audit log. **On screen, nobody can tell what happens
next.** The package scores **35 / 100** for human-centred design (§2).

The redesign keeps every service and rebuilds the screens on the `<x-gs::*>` kit and workflow kit, so they work in all
6 themes and in light and dark. It:

1. **Says what's next, and who does it.** Seven membership statuses with words, each with its next step.
2. **Makes errors speak.** Workflow refusals become messages under the field, with input kept.
3. **Turns the transfer into a visible journey.** Get ready → ask to leave → sign-off → welcome, tracked for all three people.
4. **Makes the override safe.** See the person's reach first; nothing preselected; one office at a time.
5. **Writes whole sentences in both languages.** No stitched fragments; Bangla without borrowed English.

## 2. Audit (2026-10-14)

| Heuristic | Score | Heuristic | Score |
|---|---|---|---|
| Speed for daily users | 5 | Control and undo | 3 |
| Error prevention | 5 | Consistency | 3 |
| Status is visible | 4 | Plain language (EN + বাংলা) | 3 |
| Recognition over recall | 4 | Accessibility | 3 |
| Focused layout | 4 | Error messages and recovery | 1 |

**Total 35 / 100** · 2 critical · 5 high · 5 medium.

### Keep

The clearance engine and its rules, consent-based handovers, the triple clearance check, the office chip (search,
recent offices, never renders the full list), the notices outbox, the override audit log, en-US/bn-BD key parity
(266 each).

### Findings

| # | Sev | Finding | Evidence | Fix (board) |
|---|---|---|---|---|
| F1 | C | Workflow refusals end on bare 404/409/422 pages. The translated messages exist and are unused. | `MembershipWorkflow` (`firstOrFail`, `abort(409\|422)`) · `membership_invalid_code`, `membership_already_member`, `admin_invalid_code` | Typed exceptions → `back()->withErrors()->withInput()` (4, 8) |
| F2 | C | Override: "Force strip all roles" preselected; picker renders every user; no preview; Force release ends **all** memberships; only `confirm()`. | `override_console.blade.php` · `overrideConsole()` · `forceOverride()` | Search → reach preview → one action, nothing preselected → reason + acknowledgement (9) |
| F3 | H | Kit bypassed: AdminLTE boxes, label ladders, 88 `om-inline-*` uses (66 rules). Three dead provisioning views. | `office-membership.css` · `views/provisioning/*` | Rebuild on the kit; delete dead views and ~70 keys (all) |
| F4 | H | `pending`, `rejected`, `inactive`, `suspended` render blank + "Locked". `valid_until` never shown. | `user/index` status ladder | `membership_*` in `status-map.php` + `<x-gs::status-badge>` (2, 3) |
| F5 | H | Personal code (6 chars, 24 h) and office code (8 chars, 30 days) look alike; hint shows a format never generated. | `user_join_code_placeholder` · `Str::random(8)` | One "Join another office" flow; distinct names and shapes (4) |
| F6 | H | Transfer disappears after "Request release"; sign-off box unstyled; claim from a bare dropdown, permanent, no summary. | `admin/staff` · `staff_claim_*` | Transfer tracker; sign-off and welcome dialogs (5, 7, 8) |
| F7 | H | Handover sentence stitched from 4 keys (breaks Bangla); "updates active database roles"; decline has no reason. | `user_handover_*` | Whole-sentence keys; optional decline reason (3, 6) |
| F8 | M | No way back: can't cancel a join request, withdraw a release request, or end additional access. | `routes/web.php` | Three routes reusing workflow locks (3, 5, 7) |
| F9 | M | Clearance not actionable: noun rule names, "assets 2, accessories 0…", no links. Release button red. | `rule_*_name` · `holdings_blocked` | `<x-gs::readiness>` with non-zero holdings and fixes (5) |
| F10 | M | Notices box first even when empty, raw timestamps; four unrelated widgets in one column. | `hooks/notices` · `user/index` | Waiting for you → offices → history as sentences (3) |
| F11 | M | Admin pages under "My office memberships" in breadcrumbs; concatenated titles; jargon (Home base, Floating, Claim, Executor, `strip_roles`); borrowed English in Bangla. | `routes/web.php` · `bn-BD/member.php` | Glossary and structure (2) |
| F12 | M | Icon-only `btn-xs` approve/reject with past-tense aria-labels; unlabelled cancel ×; colour-only clearance; no table captions. | `admin/staff`, `user/index` | Labelled buttons, 44 px, captions (all) |

## 3. Design direction

Same as Organization: Civic Ledger look, every screen checked in all 6 themes × light/dark (each board has Theme and
Mode tweaks), WCAG 2.2 AA. Status vocabulary, glossary and IA are on board 2. Highlights:

- **Statuses:** Waiting for approval · Active (+ "Home office" tag, "until 31 Dec") · Leaving · awaiting sign-off ·
  Ready to transfer · Left · Not approved · Paused · Ended.
- **Words:** My offices, Home office / Additional office, Leave this office, Personal code / Office code, Hand over,
  Sign off, Welcome a transferred employee, Emergency changes. Bangla drafts need a native reviewer (D5).
- **Structure:** employees use *My offices* (user menu + office chip). Administrators use *My office › Staff* with
  tabs Staff · Waiting for approval · Leaving · Arriving · Past staff. National ICT uses *Access and security ›
  Emergency changes* with a Change history tab.

## 4. Boards

| Board | Screen | Replaces | Key behaviour |
|---|---|---|---|
| 1 | UX audit | — | Score, findings, transfer journey, principles |
| 2 | Statuses, words, structure | — | Real `.gs-badge` per status, `status-map` entries, glossary, sentence keys, IA |
| 3 / 3b | My offices (desktop; phone in Bangla) | `user/index`, `hooks/notices` | Waiting for you first; office cards with status, roles and leave readiness; cancel join with undo; history as sentences; office chip sheet on phone |
| 4 | Join another office | two right-column widgets | Choose "office code" or "administrator adds me"; code check shows the office before sending; expired-code error in place |
| 5 | Leave an office | Request release button + clearance column | Stepper; readiness with each held item and the role handover; consequences + acknowledgement; transfer tracker with withdraw |
| 6 | Hand over a role | delegate modal, incoming box | Searchable colleagues with context; note; what happens next; pending role slot; stale-change error |
| 7 | Office staff | `admin/staff` | Status tabs with counts; "N things wait on you"; labelled approve/decline with undo; Leaving shows clearance; Arriving lists people ready to transfer |
| 8 | Add, sign off, welcome | add-by-token, invite code, release, claim widgets | One Add staff dialog with three ways; office code dialog; sign-off with re-check; welcome with summary |
| 9 | Emergency changes | `admin/override_console` | Search a person and see their reach; nothing preselected; one office; also-remove-roles; reason + reference + acknowledgement; history as sentences |

## 5. Implementation phases

### Delivered (2026-10-14)

- **P0.** Nine `membership_*` statuses in `theming/status-map.php` with en/bn labels (`released_home` = "Ready to
  transfer", plus `cancelled` for a withdrawn join request). `OfficeMembership::statusKey()` picks the key.
  `views/provisioning/*` and `hooks/notices` deleted. `lang/{en-US,bn-BD}/member.php` rewritten as whole sentences
  (430 keys each, parity and placeholders checked; dead provisioning and old-layout keys removed).
- **P1.** `Exceptions\MembershipRefused` (an `HttpException`, so 404/409/422 statuses and existing tests are unchanged)
  carries a translated message and its form field. `RespondsWithRefusals::attempt()` turns it, and other packages' 409s
  with a message, into `back()->withErrors()->withInput()`; `RequireGovAbility` still rolls those back. Status badges
  and "Active until" everywhere. Admin pages no longer sit under "My offices" in breadcrumbs. Notices render as
  sentences with relative dates (`MembershipNotices::present()`); a decline reason or handover note is quoted.
- **P2.** My offices (`user/index`), Join another office (`user/join`, office code is checked and the office shown before
  sending; spaces, dashes and case ignored), Leave an office (`user/leave`: stepper, readiness from rule results,
  roles to hand over, consequences, acknowledgement, transfer tracker), hand-over dialog with colleague context and
  leaving colleagues disabled. New routes: `join.form`, `cancel-join`, `leave`, `destinations`, `withdraw-release`.
  `ClearanceResult` gained optional `items` and `summary()`; the asset and role rules fill them.
- **P3.** Staff page with five tabs and counts, "N things wait on you", search and paging; labelled Approve / Decline
  (optional reason → `approval_note`); sign-off dialog re-checks readiness; welcome dialog with summary and a required
  "has reported for work"; Add staff dialog with three ways; office code dialog. New route: `admin.end` (end additional
  access, only after clearance).
- **P4.** Emergency changes: person search (20 per page, never the full user list), reach preview (offices, roles,
  items held), nothing preselected, one office per release with "also remove their roles" pre-ticked, reason, optional
  reference, acknowledgement, history tab as sentences with filters. Submitting still goes through the national review
  with typed `CHANGE` (AGENTS.md), unchanged. After a review, success and failure return to the console, not `back()`.
- **Decisions applied:** D1, D2, D3 (`destination_location_id`, notice to the receiving administrator), D4 (`reference`
  and `details` on `gov_override_audit_logs`), D6. Migration
  `2026_10_14_000001_add_membership_destination_and_override_reference` (additive, nullable) is applied to the local
  `snipeit` database.
- **P5 (part).** Six new tests in `OfficeMembershipWorkflowTest` (refusal statuses and messages, controller keeps input,
  cancel/withdraw/end access, per-office emergency release, every status has en/bn labels, whole bn sentences, labelled
  role actions). Full GovStore suite: 357 tests pass. Compliance baseline still `{}`. Every redesigned GET page
  rendered through the real HTTP kernel against the local database as an employee, an office administrator and a super
  user (200, no missing strings); a non-super user still gets 403 on Emergency changes.
- **Live tests (2026-10-14).** Suite `office-membership` (`tests/live/subtests/membership/`, run with
  `npm run test:live -- run --suite office-membership`): My offices, Join validation, Leave readiness, Staff tabs and a
  refused Add staff, and role boundaries pass twice on the seeded dataset, compared with the database through five new
  read-only bridge checks (`membership.*`). `membership.emergency-changes-observation` needs a super user (none in the
  dataset): it passes with `--target local`, which signs in with the target's super-admin login. The write journey `membership.handover-decline` (suite `journeys`) is blocked by the runner's mail-sink
  preflight on this installation (SMTP), although membership mail is disabled and unscheduled.
- **Pending:** visual check in a signed-in browser in all 6 themes × light/dark and at phone width, axe pass, live
  write journeys (join → approve; leave → sign off → welcome), bn-BD copy review (D5).
  Not built from the boards: undo after approving a join (use End access), "Remind" buttons, printing the office code.

### Original phases

1. **P0 Foundations.** `membership_*` entries in `theming/status-map.php` + `gs-theme::appearance.status.*` (en/bn).
   Whole-sentence keys. Delete `views/provisioning/*` and their keys.
2. **P1 Correctness and language** (F1, F4, F7, F11). Workflow exceptions carry a translated message; controllers
   redirect back with errors and input. Status badges everywhere; `valid_until` shown. Breadcrumbs and titles fixed.
   Notices rendered as sentences with relative dates.
3. **P2 Employee screens** (F5, F6, F8, F9, F10). Boards 3–6. New routes: cancel join request, withdraw release
   request. Readiness built from rule results with per-holding detail (rules return counts, not only a sentence).
4. **P3 Administrator screens** (F6, F8). Boards 7–8. New route: end additional access. Arriving tab = released home
   memberships in the ministry (today's `floatingUsers`), searchable and paged.
5. **P4 Emergency changes** (F2). Board 9. Paged person search endpoint; reach preview; per-office release (D1, D2).
6. **P5 Mobile, accessibility, tests.** Card layouts under 768 px, axe pass, feature tests (errors keep input, every
   status renders, bn-BD sentences), live journey: join → approve, and leave → sign off → welcome.

## 6. Decisions (D1–D4 and D6 applied as recommended on 2026-10-14)

| # | Decision | Recommendation |
|---|---|---|
| D1 | Force release ends one office instead of all memberships | Yes. Today one click can drop someone from every office. |
| D2 | Force release also removes that office's roles by default | Yes, as a pre-ticked option. Otherwise a released person keeps a role. |
| D3 | Optional "Where are you going?" on a leave request (new nullable column) | Yes. Lets the receiving administrator expect the person and fills the Arriving tab. |
| D4 | Reference number on override log (new nullable column) | Yes, optional field. |
| D5 | Bangla copy owner | Shared with Organization D5 and the navigation plan. |
| D6 | Keep Staff as its own page, linked from the Organization hub | Yes. The hub keeps its read-only staff list and "Manage staff" link. |
