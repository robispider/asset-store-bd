> Execution note (2026-10-19): the owner approved implementation. This prompt is retained as the original build
> brief; delivered status and remaining acceptance checks are in `IMPLEMENTATION-PLAN.md` and the verification report.

# Build prompt: tracking programme monitor, code dashboard and office view

Paste everything below the line into a new agent session started at the repository root. It is self-contained.

---

You are building new read-only monitoring screens for the GovStore programme-tracking package in this Laravel 12 /
Snipe-IT repository. The design is finished. Your job is to plan the build against the real code, then implement it in
phases with tests, without breaking existing behaviour.

## Resolved owner decisions (2026-10-18)

Use design plan §11 and `IMPLEMENTATION-PLAN.md` when the saved HTML disagrees:

- Monitor is the landing page; existing editing tools live under a Manage tab.
- Programme access is mandatory to see any programme's deliveries. Office access alone grants no programme data.
- MONITOR members may read clearly labelled drafts; drafts stay out of live metrics.
- Price differences are displayed neutrally, without an automatic warning threshold.
- `tracking.office_directory_enabled` enables the all-programme office view and directory after completion and
  verification; keep it off while incomplete and retain it as a kill switch.
- Add named tracking `gov.can:` abilities through `GovAccess`, preserving existing narrower authorization and state
  checks. In particular, do not expand the current head/admin-only activation rule based on prototype wording.

## Read first, in this order

1. `docs/plans/tracking-monitor-redesign-plan.md`: **the design reference.** Screen specs (§4), metric definitions (§5),
   scope rules and the viewer matrix (§6), visual system and kit (§7), backend work (§8), phases (§9), tests (§10),
   resolved owner decisions (§11). Treat §5 and the updated §6 as requirements.
2. `docs/plans/tracking-monitor-redesign/artifact/ictd-lab-monitor.html`: **the visual source of truth.** Open it in a
   browser. Tabs: *Monitor*, *Offices*, *Route map & audit*, *Design notes*. Click a code for the code dashboard and an
   office tile for the office view. Use *Previewing as* on office pages to see the historical scope examples, and the theme and
   mode switches in the top bar. Its JavaScript (`analyse()`, `sliceOf()`, `officeAccess()`, `forecast()`) is a working
   reference for metric logic; update scope logic to match the owner's decisions. Port the logic to PHP; do not ship
   the prototype's code or CSS.
3. `AGENTS.md`, `docs/security/govstore-agent-security.md`, `docs/testing/local-live-testing.md`.
4. `packages/gov-store/theming/README.md` and `docs/plans/dynamic-theming-plan.md` (tokens, `<x-gs::*>` kit, Theme Lab,
   compliance ratchet). `docs/plans/organization-ux-redesign-plan.md` shows how a previous package redesign was
   phased, and how its kit components were added.
5. The package itself: `packages/gov-store/tracking/src` (routes, `InitiativeController`, `TrackingCodeController`,
   `EloquentTrackingProjectionRepository`, `InitiativeScope`, `TrackingAuthorizationService`, `ProgrammeVerifier`,
   views under `resources/views`, lang under `resources/lang`), and `tests/Feature/GovStore/TrackingWorkflowTest.php`.
6. Scope sources: `packages/gov-store/tenant-scope/src/Contexts/TenantContext.php` and
   `Http/Middleware/InitializeTenantContext.php` (`allowedLocationIds`, `allowedInventoryLocationIds`, company admin,
   jurisdiction).

## Hard constraints

- **Do not edit Snipe-IT core** (`app/`, `resources/views/`, `routes/`, root `phpunit.xml`, …). Everything lives in
  `packages/gov-store/*` and attaches through service providers, routes registered by the package, Blade precompilers
  and config. The owner merges upstream Snipe-IT regularly.
- **Theme contract only:** `--gs-*` tokens, `<x-gs::*>` components and `status-map.php` tones. No colour literals
  (`GovStoreThemeComplianceTest` ratchets them). New patterns become kit components in
  `packages/gov-store/theming/resources/views/components` plus `components/*.css`, each with a Theme Lab demo and tests.
  Check 6 themes × light/dark.
- **Authorize on the server** for every new route and every ID in it (initiative, tracking code, location). Scope rules
  are in design §6; implement them once (a `TrackingOfficeAccess`-style service) and reuse it everywhere. Hidden buttons
  are not authorization. Programmes the viewer has no key for must not appear in any count.
- **Read-only:** the new screens write nothing. Keep existing editing flows reachable and working.
- **All strings are translation keys** in `govtracking::general` with en-US **and** bn-BD values.
- **Charts:** one hue plus status tokens with icon and label; never categorical colour (theme chart palettes fail CVD
  checks). Inline SVG with `aria-label` plus an accessible table of the same numbers.

## Known traps (from earlier gov-store work)

- Package CSS registered with `GsTheme::assets()->css()` sits in `@layer gs-packages`, so unlayered Bootstrap element
  rules (`label`, `legend`, `h2`, `section {display:block}`, `.form-control`) win. Use `!important` only on exactly those
  properties.
- Blade: an inline `@php($x = …)` before a `@php … @endphp` block breaks the template; a multi-line component attribute
  such as `:items="collect(...)->map(fn …)"` is not parsed. Build arrays in a `@php` block first. After rendering, check
  compiled output for raw `<x-gs::` tags.
- In stub-layout view tests, drop the custom-requests composer with
  `app('view')->getDispatcher()->forget('composing: layouts.default')` (dot form).
- `Asset::assignedTo()` is `morphTo('assigned', …)`; eager-loading `assignedTo` leaves it null. Load holders per type.
- `gov_tracking_associations` has no date, value or supplier. Get them from the polymorphic `associatable`
  (`InventoryMovement`, `TrackingDelivery`, `Asset`) and its goods receipt.
- `gov_tracking_codes` has no activation date (design B3).
- The full GovStore suite needs `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/GovStore`
  (`php artisan test` drops `-d`).
- For live checks, sign in at `http://127.0.0.1:8010` (the `snipeit-local-preview` launch config), not `localhost` or
  `snipeit.local`. Discover current users and roles from the database as the testing guide describes. Initiative 4
  "ICTD Lab" has 7 codes and one receipt. Initiatives 25–30 (experiment data) have ~220 associations each, which suits
  busy-state checks.

## How to work

1. **Plan first.** Enter plan mode. Read the files above and the real code, then write an implementation plan that maps
   every design section to concrete files (controllers, services, routes, views, kit components, lang keys, migrations,
   tests) and follows design §9's phases. Start from `IMPLEMENTATION-PLAN.md`, recheck the current code, and list any
   remaining disagreements. The six §11 decisions are resolved; do not ask them again. Wait for plan approval.
2. **Phase 0 (correctness) first** (design §2.2 D1–D5, D7; §8 B3–B5), each with a test. Re-verify D2 (double-counted
   receipt) before changing anything: a replay-once test already exists, so the row may be legacy data.
3. Then phases 1 → 5 in order: kit + read model, code dashboard, programme monitor, office view (programme and code
   level), then the all-programme office view and directory behind a config flag.
4. After each phase: run the focused tests, then the GovStore suite; walk the new screens live as at least a head or
   officer, a company admin, a storekeeper and a user with no access; check one dark theme and phone width. Record what
   you verified in the plan doc's status line, as `docs/plans/organization-ux-redesign-plan.md` does.
5. Commit per phase on a feature branch with clear messages. Do not push or open a PR unless the owner asks.

## Done means

- The three views match the artifact's layout and behaviour in all 6 themes, light and dark, and at 400 px width.
- Every §5 metric and every §6 viewer row and rule is covered by a passing feature or unit test.
- Defects D1–D5 are fixed and D7 is surfaced, with tests.
- `TrackingWorkflowTest`, the Theming tests and the full GovStore suite pass.
- No Snipe-IT core file changed. No colour literals added. bn-BD strings present for every new key.
- The plan doc's status line and phase table say what shipped, what was verified live, and what is open.
