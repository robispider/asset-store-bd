# Gov-Store Custom Requests — Human-Centred UX Redesign Plan

> **Scope:** every screen of `packages/gov-store/custom-requests`: Request items (`catalog/index`), the draft basket
> (`basket/index`, `hooks/basket-nav`), My requests (`user/index`), Approvals (`admin/index`, `admin/show`), To issue
> (`fulfillment/index`, `fulfillment/show`, `components/substitution-modal`), the issue register
> (`fulfillment-register/*`), request policies (`admin/policies`), `components/notices` and `components/timeline-widget`,
> plus the request → approve → issue → receive → return workflow behind them.
> **Status:** P0–P6 implemented on 2026-10-15 (see §9 for what was verified and what is open). · **Branch:** `feature/civic-ledger-theme`
> **Design artifact:** https://claude.ai/artifact/1W6beCwxWeaRJQHiKqxd6e · snapshot in
> [`custom-requests-ux-redesign/`](custom-requests-ux-redesign/README.md)
> **Reference models:** [`office-membership-ux-redesign-plan.md`](office-membership-ux-redesign-plan.md) and
> [`organization-ux-redesign-plan.md`](organization-ux-redesign-plan.md): same kit, themes and method.

---

## 1. Summary

The services are careful. Approval is independent: nobody approves their own request, and two stages need two different
people. Approval reserves stock under row locks. Substitution is limited to the same category and never a dearer item.
Goods issue documents, returns through store documents, escalation and a notices outbox are all in place. **On screen,
the person who asked can't see their own request.** The package scores **33 / 100** for human-centred design (§2).

The redesign keeps every service and rebuilds the screens on the `<x-gs::*>` kit, so they work in all 6 themes and in
light and dark. It:

1. **Gives every request one story.** A new request page shows progress, each line's decision with the approver's
   note, who has it now, what happens next, and a dated history.
2. **Makes errors speak.** Workflow refusals become messages where the problem is, and the input is kept.
3. **Puts the facts beside each decision.** Approvers see stock, the stage and why two approvers are needed.
   Storekeepers get prefilled quantities and a scan list.
4. **Explains the hidden split.** A basket that becomes two requests says so before sending and after.
5. **Uses one vocabulary in two languages.** চাহিদাপত্র everywhere, whole sentences, no leaked keys.

## 2. Audit (2026-10-14/15)

Method: code read of every view, controller, service, lang file and the CSS; a walkthrough of the running app on the
seeded office (location 160) as an employee, a primary approver and a storekeeper (`snipeit-local-preview`, local
fixture accounts); in-browser contrast and target-size measurement; and the refusal paths in the services.

| Heuristic | Score | Heuristic | Score |
|---|---|---|---|
| Speed for daily users | 5 | Consistency | 3 |
| Error prevention | 4 | Plain language (EN + বাংলা) | 3 |
| Focused layout | 4 | Accessibility | 3 |
| Control and undo | 4 | Status is visible | 2 |
| Recognition over recall | 3 | Error messages and recovery | 2 |

**Total 33 / 100** · 2 critical · 5 high · 6 medium · 1 low.

### Measured

| Measure | Value |
|---|---|
| Issue items header (`box-solid bg-blue`), text contrast, light / dark | 2.19 : 1 / 1.50 : 1 (needs 4.5 : 1) |
| Height of Substitute, Withdraw and Confirm receipt buttons | 30 px (target 44 px) |
| `abort*()` calls in the package's services and controllers, each ending on a bare error page | 56 |
| Pages where a requester can open one of their own requests | 0 |
| Timeline implementations (`timeline-widget`, `admin/show`, `fulfillment-register/show`) | 3, all time-of-day only |
| Seeded rows with `approval_status = closed`, which no page labels | 58 |

### Keep

Independent two-stage approval and the self-approval guards; stock reservation on approval; the substitution rules;
goods issue documents and returns through store documents; basket quantity autosave and AJAX add; `RequestQueues`
shared by pages and menu badges; the durable notice outbox and the 3-weekday escalation; en-US/bn-BD key parity
(284 each).

### Findings

| # | Sev | Finding | Evidence | Fix (board) |
|---|---|---|---|---|
| F1 | C | A requester can't open their own request. The number isn't a link and no page exists, so line decisions, approved quantities, approver notes, the current holder and the timeline are invisible. | `routes/web.php` has no requester show route · `user/index` | New request page (6) |
| F2 | C | Refusals end on bare 403/404/409/422 pages (over-issue, deciding twice, stale lines, self-approval). Others flash "Workflow error: :message" and lose input. | `ApprovalService`, `FulfillmentService`, `RequesterService`, `RequestInventory`, `RequestReturnService` · `govapprovalcontroller_flash_workflow_error` | Typed refusals, message in place, input kept (4, 8, 10) |
| F3 | H | Statuses are wrong or missing. "Issued" shows as a warning with a clock. `approval_status = closed` and `cannot_fulfill` have no label. Two status columns become a different ladder per page, and nothing says what's next. | ladders in `user/index`, `admin/index`, `fulfillment/index` · no `request_*` in `status-map.php` | One derived status plus a next-step sentence (2, 5, 6) |
| F4 | H | A basket silently splits by approval rule: "Service Request(s) [A, B] submitted!" | `BasketService::submitBasket` groups by `resolvePolicy()` | Split preview and explanation (4) |
| F5 | H | Approvers decide blind: no stock, no stage, no reason two approvers are needed, no age. When a line is declined or cut, the approver isn't prompted for a reason, so the requester often learns nothing. The chosen option is a faint border. "Review & Process" is hard-coded English. | `admin/show`, `admin/index:40` | Decision page (7, 8) |
| F6 | H | Issue items: header contrast as measured above, on an off-theme blue. Every quantity starts at 0. "Remaining" is danger red in the normal case. A full-width red Force Close sits by the main action, and the page uses browser `confirm()`. | `fulfillment/show`, `.metric-box--remaining`, `gov-requests.js` | Rebuilt issue page (10) |
| F7 | H | Policies: one rule set applies to every office, but the page never says so. Options read "AUTO_APPROVE (…)" with emoji. An unset category shows PRIMARY_ONLY as if saved. Item exceptions need a typed ID. No preview, and no record of who changed a rule. | `admin/policies` · `gov_approval_policies` has no office · `requests.configure` is national | Request rules (12) |
| F8 | M | Notices are "SR-… — Approved": no date, no link, never read (`read_at` is never set), the last 20 forever, above every queue. Approvers get "Submitted" for requests they already decided. | `components/notices`, `RequestNotices::forUser` | Updates with dates and links, marked read (5, 7, 9) |
| F9 | M | Times print `H:i` without dates. The register shows "20:28" in its timeline and "02:29 PM" for the same moment. "Executed by: Ripon" (first name only). | three timeline implementations | One `<x-gs::timeline>`, full names, dated local time (6, 11) |
| F10 | M | Words: "Gov Approvals Dashboard", "Pick & Issue", "Force Close", "Fulfillment Ledger", "1 lines", the leaked key `requestlabels::requests.request_type_equipment`. Bangla mixes চাহিদাপত্র (menu) with সার্ভিস রিকোয়েস্ট (pages). | `lang/*/requests.php` | Glossary, whole sentences (2) |
| F11 | M | Catalog: three panels before the first item. Quick chips are fixed English words with emoji. Search needs a separate button. The "Approved" count includes issued requests. Out-of-stock items disappear without explanation. | `catalog/index`, `CatalogService` `stock > reserved` | Catalog (3) |
| F12 | M | The kit is bypassed: AdminLTE boxes re-skinned by 187 lines of `.cr-theme … !important`, plus inline styles. Columns are mislabelled ("Item type" shows the request type). | `resources/css/custom-requests.css` | Rebuild on the kit (all) |
| F13 | M | The register loads every record with no search or paging. Goods issue lines read "Accessory 2" instead of the item. The storekeeper sees help text written for the requester. | `FulfillmentRegisterController::index` `->get()` | Register (11) |
| F14 | L | On phones, 6–7 column tables scroll sideways, buttons are 30 px, and actions hide in the status column. | at 375 px | Cards under 768 px (5b) |

## 3. Design direction

Civic Ledger look, and every board checked in all 6 themes × light/dark (each board has Theme and Mode tweaks).
WCAG 2.2 AA. Board 2 holds the status vocabulary, glossary and structure. Highlights:

- **One derived request status** (`request_*` in `status-map.php`): Waiting for approval · Waiting for final approval ·
  Approved · with the store · Partly issued · Issued · confirm receipt · Received · Closed · not all items given ·
  Not approved · Withdrawn · Return requested · Return being received · Needs office review. Each status has a next-step
  sentence that names the person responsible. Lines keep short labels on the request page.
- **Words:** Request (চাহিদাপত্র), Draft request, Send request, What is it for? / Why is it needed?, Approvals, First /
  Final approval, Approve fewer, Decline, To issue, Issue items, Give an alternative, Close the rest, I've received it,
  Issue register, Request rules (No approval needed · One approver · Two approvers). Bangla drafts need a native
  reviewer (D6).
- **Structure:** employee: Request items → Draft request → My requests (In progress · Needs you · Finished) → Request
  page. Approver: Approvals (Waiting for you · Overdue · Decided by you) → Decide. Storekeeper: To issue (Ready · Partly
  issued · Returns to receive) → Issue items, and Issue register → record. National administrator: Administration ›
  Rules & settings › Request rules.

## 4. Boards

| Board | Screen | Replaces | Key behaviour |
|---|---|---|---|
| 1 | UX audit | — | Score, measurements, personas, findings, journey, principles |
| 2 | Statuses, words, structure | — | Real `.gs-badge` per status, status-map keys, glossary (en/bn), sentence keys, IA |
| 3 | Request items | `catalog/index` | Live search in English or Bangla; type filter; "often requested here" from this office; availability in words; add with undo; draft rail; honest request counts |
| 4 | Draft request | `basket/index` | Stepper; quantities with stock; **split preview** naming the approvers; kind of request as choice cards; plain questions; optional details folded; error in place; review with "what happens next"; sent message explains the split |
| 5 / 5b | My requests (desktop; phone in Bangla) | `user/index`, `components/notices` | Tabs In progress · Needs you · Finished; one badge plus a next step; "I've received them" in the row; dated updates with links |
| 6 | Request page (new) | — | Header status; stepper Sent → Approved → Issued → Received; what happens next and who; lines with asked/approved/issued and approver notes; withdraw explained when unavailable; return dialog with required reason; dated history |
| 7 | Approvals | `admin/index` | Tabs Waiting for you · Overdue · Decided by you; stage tag; age and escalation date; stock warnings in the row |
| 8 | Decide | `admin/show` | Per line: stock, recent history, rule; Approve as asked / Approve fewer / Decline, with a reason field (optional, prompted) for the last two; why two approvers; live summary; stale-decision refusal shown in place (`staleError` tweak) |
| 9 | To issue | `fulfillment/index` | Tabs Ready · Partly issued · Returns to receive; needed-soonest first; search |
| 10 | Issue items | `fulfillment/show`, `substitution-modal` | Themed header; quantities prefilled; scan field plus a list of free serials; alternative dialog that explains the price rule; handed-to; summary of stock effect; "Close the rest" dialog that states what it cancels and needs a reason; over-issue refusal in place (`stockError` tweak) |
| 11 | Issue register and record | `fulfillment-register/*` | Filters, paging, item names, document links, status; record with the storekeeper's return task and a dated history |
| 12 | Request rules | `admin/policies` | "Applies to every office"; plain choices; threshold as one sentence; live example; inherited "Not set" state; who changed it; item exceptions by search |

## 5. Implementation phases

Each phase ships with its tests (§7) and keeps `compliance-baseline.json` at `{}`. Order follows severity.

### P0 Foundations (F3, F10, F12)

- `theming/status-map.php`: twelve `request_*` entries (keys and tones on board 2) plus labels in
  `theming/lang/{en-US,bn-BD}/appearance.php`.
- `Models\Request::statusKey()` derives the key from `approval_status`, `fulfillment_status`, `received_at`,
  `return_requested_at`, `return_document_id` and `office_id`, including stored `closed` and `cannot_fulfill`.
  `Support\RequestPresenter::nextStep(Request, User)` returns the whole-sentence next step, with names from
  `ApprovalRouting` and the store role.
- Rewrite `lang/{en-US,bn-BD}/requests.php` as whole sentences using the glossary. Delete keys no code uses today
  (`locations_*`, `menu_injection_*`, `basket_widget_console_init`, `admin_show_event_*`, `*_abort_*`), and `quick_*`
  once P2 replaces the fixed chips. Map legacy request
  types (e.g. `equipment`) to a label instead of leaking the key.
- Replace the three timelines with one partial built on `<x-gs::timeline>`: full display name, and date and time in the
  app timezone.

### P1 Errors that speak (F2)

- `Exceptions\RequestRefused` extends `HttpException`, so status codes and existing tests stay the same. It carries a
  translated message and the form field it belongs to (e.g. `issue.{line}`, `items.{line}.qty`). Replace the
  user-reachable `abort*` calls in the services with it. Authorization 403/404 stay as they are.
- A controller concern turns `RequestRefused` into `back()->withErrors()->withInput()`, like office-membership's
  `RespondsWithRefusals` (D8: promote that concern to tenant-scope or copy it). Remove the "Workflow error: :message"
  wrapper.
- Specific messages: not enough free stock (with the number), already decided by someone else (with name and time),
  line no longer on the request, serial no longer free, asset record incomplete (keep the existing field list).

### P2 Requester screens (F1, F4, F8, F11, F14)

- **New route** `GET /gov-requests/my-requests/{id}` → `gov.requests.user.show`. Only the requester sees it, and only
  in the request's office (404 otherwise, matching existing scoping). New view `user/show` (board 6). Request numbers link
  to it everywhere they appear.
- `user/index` rebuilt: tabs, rows, "Needs you" counts, and updates with dates and links. Viewing it sets `read_at` on
  the user's notices.
- `catalog/index` rebuilt: live search (debounced; also matches Bangla names), segmented type filter, category select,
  "often requested here" from the office's last 90 days of request items, draft rail, honest counts. D1: show items
  with no free stock as unaddable rows.
- `basket/index` rebuilt as the draft request (board 4). The split preview uses `PolicyService::resolvePolicy()` per
  line and `ApprovalRouting` for names. The form is hidden while the draft is empty. The sent message uses
  `request_split_sent`. D2: link requests from one basket. The navbar shows "Draft · N" only while N > 0.
- Withdraw: a confirmation dialog in place of `confirm()`; the request page explains when withdrawing isn't possible.

### P3 Approver screens (F5)

- `admin/index`: tabs from `RequestQueues::awaitingApproval()` (Waiting for you), the escalated subset (Overdue: an
  `escalated` event, or older than `escalation_weekdays`), and Decided by you (paginated, not `limit(10)`).
- `admin/show` rebuilt as Decide (board 8): free stock per line (`RequestInventory::available`), the requester's last
  request for the same item, the stage, and the rule's reason (D3). A three-way decision per line.
- D5: reasons stay optional, as today. `ApprovalService` validation is unchanged (`items.*.notes` nullable). When a
  line is declined or reduced, the page opens the reason field and prompts for it, but saves without one. A given reason
  is shown to the requester on the request page.

### P4 Storekeeper screens (F6, F13)

- `fulfillment/index` → To issue (board 9): tabs, ordered by `required_by_date` then `approved_at`, with search.
- `fulfillment/show` → Issue items (board 10). Quantities are prefilled with `min(remaining, available)`. The scan field
  filters the serial list (progressive enhancement over the existing `issue[line][]`). The alternative dialog shows the
  rule and the price, so the search endpoint returns free count and price. "Close the rest" takes a reason from a list
  plus text, both stored in the event details. D7: the person the items were handed to goes in the event details.
  The page's own dialogs replace `confirm()`.
- `fulfillment-register/*` → Issue register (board 11): filters, `paginate(25)`, item names resolved from the goods
  issue lines, document links, return task written for the storekeeper.

### P5 Request rules (F7)

- `admin/policies` rebuilt (board 12): a banner that rules apply to every office, plain choice cards, the threshold as
  one sentence, a live example, an honest "Not set, uses the default" state, item exceptions by name search (national
  scope), and who last changed each rule (D4). The breadcrumb moves under Administration › Rules & settings.

### P6 Kit clean-up, mobile, accessibility (F12, F14)

- Delete the `.cr-theme` overrides. Keep only package layout rules, registered through `GsTheme::assets()->css()`
  (gs-packages layer, tokens only). Remove emoji and inline styles.
- Card rows under 768 px, 44 px targets, captions on every table, labelled icon buttons, and status that never relies
  on colour alone. Run an axe pass on every screen.
- Trim `public/js/gov-requests.js` to add, autosave, search and dialogs. That file is outside the package: keep the
  path or move it under the package's published assets (D9).

## 6. Decisions

| # | Decision | Recommendation |
|---|---|---|
| D1 | Show items with no free stock in the catalog, not addable | Yes. Today they vanish and people assume the store never had them. |
| D2 | Link requests created from one basket (nullable `submission_batch` UUID) | Yes. The request page can say "sent with SR-…". |
| D3 | Store why the rule applied (`resolved_reason` JSON: source, threshold hit) | Yes. Explanations stay true after rules change. |
| D4 | Record who changed a rule (`updated_by` on `gov_approval_policies`) | Yes. Rules affect every office. |
| D5 | Require a reason when declining or reducing a line | **No (decided 2026-10-15): keep existing behaviour.** The reason stays optional; the page prompts for it. No service or test change. |
| D6 | Bangla copy owner | All Bangla in the boards and plan is a draft. A native reviewer signs it off before P0 ships the rewritten `bn-BD/requests.php`; shared with Organization and Office Membership D5. |
| D7 | Record who the items were handed to | Yes, in the event details. No new column. |
| D8 | Where the refusal concern lives | Promote office-membership's `RespondsWithRefusals` to tenant-scope; both packages use it. |
| D9 | `public/js/gov-requests.js` sits outside the package | Publish it from the package (`vendor:publish` tag), keeping the URL. |
| D10 | Register CSV download (board 11) | Later; not needed for the redesign. |

Migrations for D2–D4 are additive and nullable, in line with the README's schema rules: back up and review before
applying to an existing deployment, and never reset the development database.

## 7. Tests

### 7.1 Feature tests (PHPUnit, SQLite)

New file `tests/Feature/GovStore/CustomRequestsUxTest.php`, plus updates to `CustomRequestsWorkflowTest.php` (26
tests). Run with `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`.

| Phase | Test | Proves |
|---|---|---|
| P0 | `test_every_request_status_key_has_tone_icon_and_both_labels` | `status-map` and en/bn `appearance.status.*` parity for `request_*` |
| P0 | `test_status_key_derivation` (data provider) | every approval × fulfilment × received × return × office combination, including stored `closed` and `cannot_fulfill`, maps to exactly one key |
| P0 | `test_request_pages_render_without_missing_keys_in_both_languages` | every GET page as requester, approver, storekeeper and super user renders 200 with no `requestlabels::` or `gs-theme::` text, in en-US and bn-BD (legacy `request_type` included) |
| P0 | `test_bn_strings_are_whole_sentences_and_use_the_glossary` | no সার্ভিস রিকোয়েস্ট / ফুলফিলমেন্ট; placeholders match en |
| P0 | `test_timeline_shows_full_names_and_dated_local_times` | one timeline partial; date present; app timezone |
| P1 | `test_over_issue_returns_to_the_page_with_the_message_and_input` | redirect back, `errors.issue.{line}` names the free quantity, old input kept, no stock change |
| P1 | `test_stale_decision_is_explained_not_a_409_page` | second approver after a decision gets a sentence naming who decided |
| P1 | `test_refusal_status_codes_are_unchanged_for_json` | `RequestRefused` keeps 409/422 for `expectsJson()` |
| P2 | `test_requester_can_open_only_their_own_request` | 200 for owner; 404 for colleague, other office and approver via this route |
| P2 | `test_request_page_shows_lines_approved_quantities_notes_and_next_step` | content from a reduced and declined decision |
| P2 | `test_basket_split_is_previewed_and_explained` | draft page lists both groups with approver names; flash uses `request_split_sent` with both numbers |
| P2 | `test_viewing_my_requests_marks_notices_read` | `read_at` set; unread count drops |
| P2 | `test_catalog_lists_items_without_free_stock_as_unaddable` (D1) | row present, add refused by service |
| P2 | `test_empty_draft_hides_the_send_form` | no submit form, link to catalog |
| P3 | `test_decline_and_reduce_without_a_reason_still_save` (D5) | existing behaviour kept: decision commits with `notes` null; a given reason reaches the request page |
| P3 | `test_decide_page_shows_stock_stage_and_rule_reason` | free stock, "first of two", reason from D3 |
| P3 | `test_overdue_tab_matches_escalation` | count equals escalated pending requests |
| P4 | `test_issue_quantities_are_prefilled_to_what_can_be_issued` | `min(remaining, available)` |
| P4 | `test_close_the_rest_requires_a_reason_and_records_it` | reason in event details and shown to requester |
| P4 | `test_register_is_paginated_and_names_items` | 25 per page; item names, not class names |
| P5 | `test_rules_page_says_rules_apply_everywhere_and_shows_unset_categories` | banner; "Not set" for categories without a policy |
| P5 | `test_saving_a_rule_records_who_changed_it` (D4) | `updated_by` set |
| P6 | `test_request_views_have_no_colour_literals_inline_styles_or_emoji` | complements `GovStoreThemeComplianceTest` |
| P6 | `test_tables_have_captions_and_icon_buttons_have_names` | markup scan of rendered pages |

### 7.2 Live tests (JSON runner, `tests/live`)

New suite `tests/live/suites/custom-requests.json` (read-only), new journeys in `journeys.json`, and updated
locators in `tests/live/ui-contracts/requests.json`. Add stable hooks to the new views (`data-request-number`,
`data-line-id`, `data-status-key`) so locators don't depend on Bootstrap classes. Follow
[`live-tests-agent-guide.md`](../testing/live-tests-agent-guide.md); fixtures declare requirements, never identities.

| Journey | Kind | Steps and proof |
|---|---|---|
| `requests.my-requests-observation` | seeded, read-only | employee opens My requests; tab counts and each row's `data-status-key` equal a new `request.list` observation |
| `requests.request-page-observation` | seeded, read-only | employee opens a request; asked/approved/issued per line and the next-step holder equal `request.state`; a colleague's request URL returns 404 |
| `requests.catalog-progress-observation` | seeded, read-only (update) | new locators; counts by derived status |
| `requests.approvals-observation` | seeded, read-only | approver's three tab counts equal `request.queues` (extended with `overdue`, `decidedByMe`) |
| `requests.to-issue-observation` | seeded, read-only | storekeeper's tab counts and order (needed-by, then approved) equal the observation |
| `requests.register-observation` | seeded, read-only | first page shows 25 rows; item names equal goods issue lines (new `register.page` observation) |
| `requests.rules-observation` | read-only, super user (`--target local`) | banner present; unset categories say "Not set"; policy rows equal `policy.state` |
| `requests.authorization-boundaries` | seeded, read-only (extend) | the new requester route for another user's request is 404; storekeeper can't open Decide |
| `requests.basket-validation` | mutation (update) | empty purpose shows the error under the field and keeps the other values; no request created |
| `requests.submit-approve` | mutation (update) | new locators; the request page shows "Waiting for approval" with the approver's name |
| `requests.split-approve-issue-receive` | mutation, new | two items with different rules → draft shows 2 groups → send → 2 numbers in the message → approve one → storekeeper issues with prefilled quantity → employee confirms receipt → statuses `request_issued` then `request_received`; stock moves once |
| `requests.decline-with-reason` | mutation, new | approver declines one line with a reason and reduces another without one; both save; requester sees the given reason on the request page and no note on the other |
| `requests.over-issue-refusal` | mutation, new | storekeeper submits more than is free (edited input); error in place names the free quantity; no goods issue document created |

New read-only bridge observations in `scripts/live-tests/bridge/live-test-adapter.php` and
`tests/live/observations/contracts.json`: `request.list`, `register.page`, `policy.state`, plus `overdue` and
`decidedByMe` in `request.queues`. Update `tests/live/feature-catalog.json` and run `npm run test:live:contract`. Write
journeys retain their requests (no reversal lifecycle) and report them by number, as `submit-approve` does today.

### 7.3 Visual and accessibility

- Add the redesigned request pages to `scripts/live-tests/theme-visual` (`npm run test:themes`): 6 themes × light/dark
  at desktop and 375 px, with a contrast assertion on page headers and badges (≥ 4.5 : 1).
- An axe pass per screen (no serious or critical issues) recorded in the verification note for each phase.

## 8. Next step

Approve the decisions in §6, then start with P0 + P1. They fix the critical findings' foundations without changing any
screen's layout, and every later phase depends on them.

## 9. Implementation record (2026-10-15)

### Implemented

- **P0** `request_*` status-map entries with en/bn labels; `Request::statusKey()` and `tabKey()`; `Support\RequestPresenter`
  (names, next-step sentences, line labels, approver notes, rule reasons, steps, notices, one dated history);
  `lang/{en-US,bn-BD}/requests.php` rewritten as whole sentences (567 keys each, unused keys removed); legacy request
  types show "Older request"; one `partials.history` replaces the three timelines.
- **P1** `TenantScope\Exceptions\WorkflowRefusal` and `TenantScope\Http\Controllers\Concerns\RespondsWithRefusals`
  (D8); office-membership now extends them. `CustomRequests\Exceptions\RequestRefused` replaces the user-reachable
  `abort*` calls in the approval, fulfilment, requester, return, basket and inventory services; status codes unchanged,
  JSON callers keep them. "Workflow error: :message" is gone.
- **P2** new `GET /gov-requests/my-requests/{id}` (`gov.requests.user.show`, requester and office only, 404 otherwise);
  My requests tabs and dated, linked updates that mark `read_at`; catalog live search, type segments, "often requested
  here", unaddable rows for promised stock (D1) with a service refusal; draft request with split preview, plain
  questions, errors in place and a review step; "Request these again" (`gov.requests.again`); navbar "Draft · N"
  only while N > 0.
- **P3** Approvals tabs (Waiting for you · Overdue · Decided by you, paginated) from `RequestQueues::overdue()` and
  `decidedBy()`; Decide with free stock, the requester's last issue of the item, the stored rule reason (D3), a
  three-way choice and optional prompted reasons (D5); stale decisions come back naming who decided.
- **P4** To issue tabs (Ready · Partly issued · Returns to receive) with search and needed-by ordering; Issue items with
  a themed header, prefilled `min(remaining, free)`, scan field over free serials, alternative search that states the
  price rule (search endpoint returns free count, price and why not), handed-to (D7) and Close the rest with a reason
  code; register filters, 25 per page, item names and document links.
- **P5** Request rules: national banner, plain choices, limits with a live example, "Not set", who changed it (D4,
  `updated_by`), item exceptions by name (national, not office stock) and removal (`gov.requests.admin.policies.destroy`,
  added to `NationalChangeReview`).
- **P6** the 187-line `.cr-theme` re-skin is replaced by layout-only `.cr-*` rules on tokens; no emoji or inline styles;
  44 px targets; tables become labelled cards under 768 px; every table has a caption. `gov-requests.js` now lives in
  the package and is published to its old URL (`vendor:publish --tag=gov-requests-assets`, D9).
- **D2–D4** migration `2026_10_15_000001_add_request_explanations` (nullable `submission_batch`, `resolved_reason`,
  `gov_approval_policies.updated_by`), applied to the local development database only.

### Verified

- `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`: 397 tests pass, including the 26 workflow tests
  and 40 new `CustomRequestsUxTest` cases (the 24 tests of §7.1, one with a 17-case data provider). The theme compliance
  baseline stays `{}`.
- Every package view compiles; `node scripts/live-tests/cli.mjs validate`: 69 definitions, 0 errors;
  `npm run test:live:contract` passes.
- Walked by hand on `snipeit-local-preview` as the seeded storekeeper, requester and primary approver: To issue, Issue
  items (prefill, live summary, no-price alternative explained), register and record, My requests, the request page,
  Request items (live search, add with undo, navbar draft), Draft request (validation in place, review), Approvals and
  Decide ("Approve fewer" opens the quantity and the reason prompt); Approvals at 375 px in dark mode without sideways
  scroll. During the walk one request, SR-2026-000298, was sent by mistake and then withdrawn from the UI; it remains
  in the local database as a withdrawn request with its history.

### Open

- D6: the Bangla strings are a draft until a native reviewer signs them off.
- The JSON live runner was not run. `catalog-progress-observation`, `basket-validation` and `submit-approve` have new
  locators and two read-only journeys were added (`my-requests-observation`, `request-page-observation`, plus the
  `request.list` bridge observation); all are marked `defined`. The other journeys of §7.2 (approvals, to-issue,
  register, rules observations and the three new mutation journeys) are not written yet.
- §7.3: theme screenshots across 6 themes and the axe pass were not run; Request rules was not walked live because no
  local super user is available (it is covered by feature tests).


## 10. Request flow redesign (2026-10-15, second pass)

The owner did not like the first pass of Request items and the draft. A clickable prototype
(https://claude.ai/artifact/WRiwngcwXmvfUoYurFfrJK) was approved with a Cards / List switch, then built.

### What changed for the requester

- **Request items:** cards or a list (the choice is kept for the session), kind tabs with live counts, "Only what is
  free now", "You usually ask for" (the person's own last 180 days, else the office's), and on every item who has to
  approve it (`PolicyService::explainMany`, two queries per page). One **Add**; the item then shows a quantity. The
  draft panel is re-rendered from the server after every add, change and removal (`BasketController::fragments`),
  with Undo; on phones it becomes a bar at the bottom. This fixes the panel that stayed "empty" after an add.
- **Checkout** (`basket/index`): three real steps (Items · Details · Review and send) that check themselves. "Why is it
  needed?" names its reader and is optional when nothing needs approval (`request_refused_reason_needed` otherwise).
  "When do you need it?" is No rush · Within a week · By a date. Review shows the request as a requisition form.
- **Sent** (`gov.requests.sent`, new): the submission's request numbers, status, progress and next step.

### Security

- **Draft per office:** `draft_baskets.office_id` (migration `2026_10_15_000002_scope_drafts_to_offices`, applied to the
  local development database only). `DraftBasket::openFor()` scopes every read; an older draft without an office is
  claimed by the office it is next used in.
- **Review snapshot:** the review posts `BasketService::snapshot()` (item, quantity, rule, asks-more-than-free). If the
  draft changed since, nothing is sent (409, field `reviewed`) and the page lists what changed.
- **Sent once:** a one-time `submit_token` held with `Cache::add`; a repeat shows the first submission's Sent page.
- **Declaration:** required (`declared`), recorded as `declared_at` and `declaration` in the submitted event.
- **Rate limits:** `throttle:gov-requests-draft` (120/min) on add/update/remove, `throttle:gov-requests-send` (6/min).
- Every submission now has a `submission_batch`, so the Sent page lists exactly that submission.

### Verified

- `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`: 407 tests pass, 10 of them new in
  `CustomRequestsUxTest` (cards/list, fragments, drafts per office, review snapshot, one-time token, declaration,
  reason rule, checkout markup, Sent page access, rate limits). `CommitteeTest`'s stand-in `draft_baskets` table
  gained `office_id`.
- `node scripts/live-tests/cli.mjs validate` (0 errors) and `npm run test:live:contract` pass after updating
  `catalog.json`, `requests.json`, `basket-validation`, `submit-approve` and `catalog-progress-observation`.
  The JSON live runner was not run.
- Walked on `snipeit-local-preview` (desktop, 375 px, dark): add / change / remove with Undo, the panel updating,
  step validation, the review snapshot refusal, a double-pressed Send creating one request (SR-2026-000301, then
  withdrawn from its request page).

### Open

- Bangla strings for this pass are drafts for the native reviewer (D6).
- `packages/gov-store/experimentation/tests` need their separate experiment database and were not run.
