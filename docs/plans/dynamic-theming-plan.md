# Dynamic Theming — Implementation Plan

> **Package:** `gov-store/theming` (`packages/gov-store/theming`) · **Namespace:** `GovStore\Theming`
> **Status:** `govstore-theming` merged to `master`. Phases 0–5, 7 (charts) and 8 delivered. Phase 6 (gov-store view migration) is **in progress, package by package** — see the migration-order checklist in §15.4 for current status; `tracking` is the first package fully migrated (2026-10-07). Of the three print-view conversions, the two in `tracking` (`initiatives/report.blade.php`, `tracking_codes/view_task_component.blade.php`) are done; `store-operations/.../operations/print.blade.php` remains. The woff2 font binaries still remain. See `packages/gov-store/theming/README.md`. Decisions D1–D9 resolved, C1 pending (§21) · **Date:** 2026-10-07
> **Design source:** Claude artifact *"National Asset Register — Theme Directions"* (Theme 1 Institutional Green, Theme 2 Digital Blue, Theme 3 Executive Neutral)

---

## 1. Summary

Add user-selectable, file-based visual themes to the National Asset Register **without editing any Snipe-IT core file**. Each theme ships a light **and** a dark mode, can change layout character (sidebar, density, table style, surfaces, typography) through declared *variants*, and every gov-store package renders fully on theme tokens and the shared UI kit.

The theme is attached to Snipe-IT's layouts **when Blade compiles them** (`Blade::precompiler`), so there is zero per-request cost and zero diff in core files.

### Goals

| # | Goal |
|---|---|
| G1 | Users can pick a theme (and Light / Dark / System mode) besides the default; the organisation, each company (ministry/tenant) and each office can set their own default. |
| G2 | Zero edits to Snipe-IT core files; upstream merges stay conflict-free for theming. |
| G3 | Themes are **file-based and developer-owned**: a theme is a folder of files (JSON tokens, optional CSS, previews) that **developers build and ship with the code**, reviewed in git like any other change. Nothing can be uploaded or designed inside the running application. |
| G4 | Every theme **must** provide light and dark modes; validated automatically. |
| G5 | All gov-store packages are fully compliant with themes and layout variants. |
| G6 | A developer can add a new theme by adding one folder (mostly JSON) and releasing it — no PHP, routes or Blade. In the application, every admin — super admin included — only **chooses** among the shipped themes. |
| G7 | Breakage after an upstream merge shows up as failing tests, not user reports. |
| G8 | Theme administration and the Theme Lab are governed by gov-store roles **and** permissions, not by environment. |

### Non-goals

- No in-browser theme editor, no Theme Studio, no theme upload, no database-stored themes. Themes exist only as files in the release.
- No theme creation by any application user, including the super admin.
- No edits to Snipe-IT page files. Snipe-IT pages **are** themed — every page uses the selected theme (§7.4) — but through the layout hook and adapter, never by changing a Snipe-IT Blade file.
- No new page *structure* on Snipe-IT screens (e.g. status tabs on core *Assets › List All*). Themes change how Snipe-IT pages look, not what is on them; structural patterns live in gov-store screens.
- No changes to Snipe-IT's Settings › Branding or profile screens. How their colour fields interact with themes is defined in §7.4.

---

## 2. The upstream constraint

### 2.1 Why it matters

| Upstream file | Upstream commits, last 12 months |
|---|---|
| `resources/views/layouts/default.blade.php` | **157** |
| `resources/views/account/profile.blade.php` | 14 |
| `webpack.mix.js` | 6 |
| `app/Http/Middleware/CheckColorSettings.php` | 4 |

The main layout is upstream's most-churned file, and theming is the area upstream is actively rebuilding (CSS custom properties, `light-dark()`, the Nov-2025 migration replacing `skin` with link colours). Today our drift in theming-related core files is ~40 lines across 3 files. This plan adds **none**.

### 2.2 Rules this plan follows

1. **No edits** to files under `app/`, `resources/`, `config/`, `routes/`, `database/`, `public/js|css`, `webpack.mix.js` that come from upstream.
2. **No reliance on upstream columns** that upstream is phasing out (`users.skin`, `settings.skin`, `allow_user_skin`).
3. **One root change only:** a `require` line for `gov-store/theming` in `composer.json` (already a maintained, drifted file). Provider registration via composer auto-discovery, so `config/app.php` is untouched.
4. **New files** in shared directories (`tests/Feature/GovStore/Theming/*`, `scripts/live-tests/theme-visual/*`, one script line in `package.json`) are allowed — new files never conflict.
5. **Graceful degradation:** if an upstream change removes an anchor the hook needs, the app falls back to stock Snipe-IT styling and a test fails.

### 2.3 Core-file touch ledger (target end state)

| File | Change |
|---|---|
| `composer.json` | + `"gov-store/theming": "dev-master"` |
| `package.json` | + `"test:themes"` script line |
| Any other Snipe-IT file | **none** |

---

## 3. Current state (facts from the codebase)

| Area | Reality |
|---|---|
| Theme variables | Inline `<style>` in `layouts/default.blade.php` (lines ~45–125): `:root`, `[data-theme="light"]`, `[data-theme="dark"]` with `--main-theme-color`, `--color-bg`, `--color-fg`, `--box-bg`, `--link-color`, `--table-stripe-bg`, `--text-*`, `--sidenav-*`, `--main-footer-*`, etc. |
| Light/dark | `data-theme` on `<html>`, toggled by a script near the end of `<body>` (≈ line 2189) that stores the choice in `localStorage` only → dark users see a light flash on every load. |
| `!important` | 157 occurrences in the core layout. |
| Per-user colours | `CheckColorSettings` middleware shares `nav_link_color`, `link_light_color`, `link_dark_color` (user overrides settings). |
| Brand colour | `settings.header_color` (global only). |
| CSP | Inline scripts use `nonce="{{ csrf_token() }}"`. |
| Layouts | `default`, `basic` (login; no `@stack('css')`), `setup`, `edit-form`, `debug`. |
| Charts | Chart.js v2.9.4; colours from `Helper::defaultChartColors()` and status-label colours. |
| Gov-store packages | 12 packages, path repositories, each with its own view namespace (`storeops`, `govtracking`, `gov-classification`, `govstore`, `govorg`, `govmem`, `govscope`, …). Existing UI is injected through response-rewriting middlewares (`InjectStoreOperationsUi`, `InjectMembershipUi`). |
| Gov-store styling debt | 24 views contain `<style>` blocks; ~820 hex colour literals (3- and 6-digit) in package views/CSS; 3 print views. |
| Access control | `tenant-scope` owns gov-store authorisation: abilities in config `govstore-abilities` (ability → `roles`, `national`, `enforce`), turned into Gates by `TenantScopeServiceProvider`, decided by `GovAccess`. Roles: `authenticated`, `superuser`, `company_admin`, `ict_officer`, `office_admin`, office responsibilities (`storekeeper`, approvers, …) and time-limited office grants (`gov_access_grants.role_slug`). Permission strings come from responsibility → profile mappings in `govstore-permissions`, checked with `TenantContext::hasPermission()`. Abilities appear automatically in the existing Access matrix page. |
| Tenant context | `TenantContext` (per request): `companyId` (ministry/tenant), `locationId` (working office, switchable per session), `isGlobal`, `isCompanyAdmin`. Set by `InitializeTenantContext` middleware. |
| Tests | PHPUnit suites `tests/Unit`, `tests/Feature` (gov-store tests live in `tests/Feature/GovStore/`). Playwright is installed; live tests under `scripts/live-tests/`. |
| Framework | Laravel 12.65 — `Blade::precompiler()` and `Blade::getPath()` confirmed in vendor. |

Hex-literal debt per package (migration order, §15):

| Package | Hex literals |
|---|---|
| store-operations | 274 |
| tracking | 225 |
| classification | 145 |
| custom-requests | 77 |
| organization | 65 |
| office-membership | 30 |
| tenant-scope | 3 |
| user-onboarding | 1 |
| committee, experimentation, geo-areas, metadata | 0 |

---

## 4. Architecture

```
                   ┌──────────────────────────── Snipe-IT core (untouched) ───────────────────────────┐
                   │  layouts/default.blade.php   layouts/basic.blade.php   layouts/setup.blade.php   │
                   └───────────────┬──────────────────────────────────────────────────────────────────┘
                                   │ compiled once by Blade
                                   ▼
          ┌──────────── gov-store/theming ───────────────────────────────────────────────────────────┐
          │ LayoutHook (Blade::precompiler)  ── inserts ──►  <html data-skin data-theme data-gs-*>    │
          │                                                 @include('gs-theme::head')  before </head>│
          │                                                 @include('gs-theme::foot')  before </body>│
          │                                                                                          │
          │ ThemeRepository ─ discovers themes/*/theme.json (shipped in code), resolves `extends`    │
          │ ThemeResolver   ─ enforced scope → user → office → company → organisation → config       │
          │ Access          ─ `theming.*` abilities (roles) + permission strings, via tenant-scope   │
          │ ThemeCompiler   ─ tokens.*.json → CSS (build time), OKLCH derivations, contrast checks   │
          │ AssetRegistry   ─ packages register their CSS/JS; bundled into the build                 │
          │ UI kit          ─ <x-gs::*> Blade components                                             │
          │ gs-theme.js     ─ token reader, mode sync, Chart.js plugin                               │
          │ Appearance page ─ /gov/appearance   Assignments ─ /gov/appearance/assignments            │
          │ Theme Lab       ─ /gov/theme-lab                                                         │
          └──────────────────────────────────────────────────────────────────────────────────────────┘
                                   ▲ uses tokens + kit
          ┌────────────────────────┴─────────────────────────────────────────────────────────────────┐
          │ store-operations · tracking · classification · custom-requests · organization · …        │
          └──────────────────────────────────────────────────────────────────────────────────────────┘
```

### 4.1 Package layout

```
packages/gov-store/theming/
├─ composer.json                       # extra.laravel.providers → auto-discovery
├─ config/gs-theme.php
├─ config/abilities.php                 # theming.* abilities merged into govstore-abilities
├─ database/migrations/
│  ├─ 2026_10_xx_create_gs_theme_preferences_table.php
│  └─ 2026_10_xx_create_gs_theme_assignments_table.php      # organisation / company / office defaults
├─ routes/web.php
├─ lang/en-US/appearance.php
├─ lang/bn-BD/appearance.php
├─ src/
│  ├─ ThemingServiceProvider.php
│  ├─ Compiler/LayoutHook.php
│  ├─ Themes/{Theme.php, ThemeRepository.php, ThemeResolver.php, Manifest.php}
│  ├─ Build/{ThemeCompiler.php, TokenResolver.php, ColorMath.php, ContrastChecker.php}
│  ├─ Assets/AssetRegistry.php
│  ├─ Access/ThemeAccess.php           # role OR permission decisions, scope checks
│  ├─ Http/Controllers/{AppearanceController.php, AssignmentController.php, ThemeLabController.php, DevAssetController.php}
│  ├─ Models/{ThemePreference.php, ThemeAssignment.php}
│  ├─ Console/{BuildThemes.php, MakeTheme.php, ValidateThemes.php, ComplianceReport.php}
│  └─ Facades/GsTheme.php
├─ adapter/
│  ├─ snipeit-vars.css                 # token → Snipe-IT variable mapping (unlayered)
│  ├─ snipeit.css                      # @layer gs-adapter: selector overrides for hard-coded core rules
│  └─ variants.css                     # every layout variant, implemented once
├─ components/                         # UI kit CSS (@layer gs-components)
├─ resources/
│  ├─ views/{head,foot}.blade.php
│  ├─ views/appearance/*.blade.php
│  ├─ views/lab/*.blade.php
│  ├─ views/components/*.blade.php     # anonymous components → <x-gs::…>
│  └─ js/gs-theme.js
├─ fonts/                              # self-hosted woff2 (OFL), referenced by key
├─ status-map.php                      # status → tone + icon for badges/steppers
├─ compliance-baseline.json            # ratchet allowlist for gov-store compliance
└─ themes/
   ├─ default/                         # "Snipe-IT Classic": stock look + Branding colours; root of inheritance
   ├─ institutional-green/
   ├─ digital-blue/
   └─ executive-neutral/
```

### 4.2 Naming conventions

| Thing | Convention |
|---|---|
| CSS custom properties | `--gs-{category}-{role}[-{state}]` e.g. `--gs-color-primary-hover` |
| CSS classes | `.gs-*` (never styled by Snipe-IT) |
| HTML attributes | `data-skin`, `data-theme` (Snipe-IT's), `data-gs-{variant}` |
| Blade components | `<x-gs::name>` (anonymous namespace `gs` → `gs-theme::components`) |
| Views | `gs-theme::…` |
| Artisan | `gs-theme:{build,make,validate,compliance}` |
| Config / env | `config('gs-theme.*')`, `GS_THEME_*` |
| Tables | `gs_theme_*` |

---

## 5. Integration: the compile-time layout hook

### 5.1 Mechanism

`Blade::precompiler()` callbacks run on the raw source of every Blade file **once, at compile time**; the result is cached in `storage/framework/views`. The hook checks `Blade::getPath()` and acts only on the target layouts.

```php
// src/Compiler/LayoutHook.php  (sketch)
final class LayoutHook
{
    public const VERSION = 'gs-theme-hook:v1';

    private const TARGETS = [
        'resources/views/layouts/default.blade.php',
        'resources/views/layouts/basic.blade.php',
        'resources/views/layouts/setup.blade.php',
    ];

    public function __invoke(string $source): string
    {
        $path = str_replace('\\', '/', (string) Blade::getPath());
        if (! Str::endsWith($path, self::TARGETS)) {
            return $source;
        }

        // 1. <html …> : server-resolved mode + skin + variants
        $source = preg_replace_callback('/<html\b[^>]*>/i', function ($m) {
            $tag = preg_replace('/\sdata-theme="[^"]*"/', '', $m[0]);
            return Str::replaceLast('>', ' {!! app(\'gs.theme\')->htmlAttributes() !!}>', $tag);
        }, $source, 1, $htmlCount);

        // 2. head assets: just before the admin Custom CSS block, else before </head>
        $head = "@include('gs-theme::head')\n";
        $customCss = '/@if\s*\(\s*\(\s*\$snipeSettings\s*\)\s*&&\s*\(\s*\$snipeSettings->custom_css\s*\)\s*\)/';
        $source = preg_match($customCss, $source)
            ? preg_replace($customCss, $head.'$0', $source, 1)
            : Str::replaceLast('</head>', $head.'</head>', $source);

        // 3. foot script, last </body>
        $source = Str::replaceLast('</body>', "@include('gs-theme::foot')\n</body>", $source);

        return "{{-- ".self::VERSION." --}}\n".$source;
    }
}
```

Registered in `ThemingServiceProvider::boot()`:

```php
Blade::precompiler(app(LayoutHook::class));
```

### 5.2 What gets inserted

| Anchor | Inserted | Purpose |
|---|---|---|
| `<html …>` | `data-skin="institutional-green" data-theme="dark" data-gs-sidebar="dark" data-gs-density="compact" …` (replaces core's hard-coded `data-theme="light"`) | Server-side theme + mode → no flash for explicit modes |
| immediately before the Custom CSS block (`@if (($snipeSettings) && ($snipeSettings->custom_css))`), falling back to before `</head>` | `gs-theme::head` → `<link>` to built kit CSS, theme CSS, package CSS; `<link rel="preload">` fonts; inline nonce'd mode script (for `system` mode and `localStorage` sync) | Theme styles load **after** core inline styles (theme beats Snipe-IT defaults) and **before** the admin's Custom CSS (Custom CSS stays the final word, §7.4) |
| before `</body>` | `gs-theme::foot` → `gs-theme.js` (nonce'd), Chart.js plugin, toggle sync | Runtime helpers |

### 5.3 Operational notes

- **Deploy:** `php artisan view:clear` (already covered by `optimize:clear`) after enabling or upgrading the hook. `php artisan view:cache` runs precompilers too.
- **Graceful fallback:** if an anchor is missing, that insertion is skipped; the page renders with stock Snipe-IT styles.
- **Detection:** the contract test (§16.2) compiles each target layout and asserts the `VERSION` marker and all three insertions are present.
- **Admin Custom CSS** (Settings › Branding) is loaded **after** the theme in both `default` and `basic` layouts, so it can still adjust any theme — same role it has today.
- **Existing response-rewriting middlewares** in other packages are left as is; migrating them to this hook is a separate, optional follow-up.

---

## 6. Token system

### 6.1 Format

Tokens are authored in **W3C Design Tokens (DTCG)** JSON, one file per mode: `tokens.light.json`, `tokens.dark.json`. This lets designers round-trip with Figma via Tokens Studio.

```json
{
  "seed": {
    "primary": { "$type": "color", "$value": "#14532D" },
    "accent":  { "$type": "color", "$value": "#15803D" },
    "neutral": { "$type": "color", "$value": "#1F2937" },
    "danger":  { "$type": "color", "$value": "#B42318" }
  },
  "color": {
    "bg":            { "$type": "color", "$value": "#F7F9F8" },
    "surface":       { "$type": "color", "$value": "#FFFFFF" },
    "primary":       { "$type": "color", "$value": "{seed.primary}" },
    "primary-hover": { "$type": "color", "$value": "{seed.primary}",
                       "$extensions": { "gs.derive": { "l": -0.06 } } },
    "on-primary":    { "$type": "color", "$value": "#FFFFFF" }
  },
  "font": {
    "sans":    { "$type": "fontFamily", "$value": "{fonts.noto-sans}" }
  },
  "radius": {
    "md":      { "$type": "dimension", "$value": "6px" }
  }
}
```

### 6.2 Three levels

| Level | Who edits | Examples |
|---|---|---|
| **Seed** | Designer, always | `seed.primary`, `seed.accent`, `seed.neutral`, `seed.danger` |
| **Semantic** | Designer, optional (defaults derived from seeds via `default` theme) | `color.surface`, `color.text-muted`, `color.border`, `color.status-pending` |
| **Component** | Rarely; defaults reference semantic tokens | `table.row-h`, `sidebar.active-marker`, `kpi.value-font` |

### 6.3 Derivations (resolved at build time)

`"$extensions": {"gs.derive": {...}}` adjusts a referenced colour in **OKLCH** (`l`, `c`, `h`, `alpha`, or `mix: {with, amount}`). `ColorMath` resolves derivations at build so the emitted CSS contains literal colours — this is what makes contrast validation possible (§8.3). A theme can therefore be "4 seed colours"; every hover, subtle tint, border and dark shade is derived, and any derived token can be overridden explicitly.

### 6.4 Semantic token catalogue (contract)

| Group | Tokens |
|---|---|
| Ground | `color-bg`, `color-surface`, `color-surface-raised`, `color-surface-sunken`, `color-border`, `color-border-strong`, `color-overlay` |
| Text | `color-text`, `color-text-muted`, `color-text-subtle`, `color-text-inverse`, `color-link`, `color-link-hover`, `color-focus-ring` |
| Brand | `color-primary`, `-hover`, `-active`, `-subtle`, `color-on-primary`, `color-accent`, `color-on-accent` |
| Feedback | `color-{success,warning,danger,info}`, `-subtle`, `color-on-{…}` |
| Asset status | `color-status-{deployed,ready,pending,undeployable,archived}` |
| Document status | `color-doc-{draft,ready,posted,cancelled,approved,rejected}` |
| Shell | `header-bg`, `header-fg`, `header-fg-muted`, `sidebar-bg`, `sidebar-fg`, `sidebar-fg-muted`, `sidebar-section-fg`, `sidebar-active-bg`, `sidebar-active-fg`, `sidebar-active-marker`, `footer-bg`, `footer-fg` |
| Table | `table-head-bg`, `table-head-fg`, `table-row-alt`, `table-row-hover`, `table-row-selected`, `table-border`, `table-total-rule` |
| Charts | `chart-1` … `chart-10`, `chart-grid`, `chart-label` |
| Typography | `font-sans`, `font-display`, `font-mono`, `font-bengali`, `font-size-base`, `font-size-sm`, `line-height-base`, `heading-weight` |
| Shape | `radius-sm`, `radius-md`, `radius-lg`, `border-width` |
| Density | `row-h`, `control-h`, `space-1` … `space-6` |
| Elevation | `shadow-1`, `shadow-2` |

All are emitted as `--gs-<group>-<name>` (e.g. `--gs-color-primary-hover`, `--gs-table-row-h`).

### 6.5 Light / dark emission

```css
[data-skin="institutional-green"]                     { /* tokens.light.json */ }
[data-skin="institutional-green"][data-theme="dark"]  { /* tokens.dark.json  */ }
```

Selectors are **not** `html`-qualified, so the Theme Lab can scope any theme × mode to a wrapper element (§14).

---

## 7. Adapter (the only layer that knows Snipe-IT)

### 7.1 Cascade strategy

| Layer | File | Mechanism | Why it wins |
|---|---|---|---|
| 1. Variable mapping | `adapter/snipeit-vars.css` | **Unlayered**, `[data-skin] { --main-theme-color: var(--gs-header-bg); --box-bg: var(--gs-color-surface); … }` | Loaded after core inline styles; most core rules already read these variables, so most theming happens here with no selector fights |
| 2. Selector overrides | `adapter/snipeit.css` | `@layer gs-adapter`, declarations `!important` | `!important` inside a cascade layer beats `!important` outside any layer → reliably beats the 157 core `!important` rules without specificity wars |
| 3. Variants | `adapter/variants.css` | `@layer gs-adapter`, keyed by `[data-gs-*]` | Same as above |
| 4. UI kit | `components/*.css` | `@layer gs-components`, `.gs-*` classes | Core never styles `.gs-*`; no conflicts |
| 5. Package CSS | registered by packages | `@layer gs-packages` | Same |
| 6. Theme overrides | `themes/*/overrides.css` | `@layer gs-theme`, **no `!important`**, only `.gs-*` selectors and tokens | Last word on kit components; can't fight core |

Rule for theme authors: *if a theme needs to change something the adapter forces, add a token to the adapter — don't write a selector.*

### 7.2 Variable mapping (excerpt)

| Snipe-IT variable | Mapped to |
|---|---|
| `--main-theme-color` | `--gs-header-bg` |
| `--nav-primary-text-color`, `--nav-hover-text-color` | `--gs-header-fg`, `--gs-header-fg-muted` |
| `--color-bg` / `--color-fg` | `--gs-color-bg` / `--gs-color-text` |
| `--box-bg` | `--gs-color-surface` |
| `--box-header-top-border-color` | `--gs-color-border-strong` |
| `--link-color` / `--link-hover` | `--gs-color-link` / `--gs-color-link-hover` |
| `--table-stripe-bg` / `--table-stripe-bg-alt` | `--gs-color-surface` / `--gs-table-row-alt` |
| `--table-border-row-color` | `--gs-table-border` |
| `--text-{danger,success,warning,info,help}` | `--gs-color-{danger,success,warning,info,text-muted}` |
| `--input-border-color` | `--gs-color-border` |
| `--sidenav-*` | `--gs-sidebar-*` |
| `--main-footer-*` | `--gs-footer-*` |
| `--search-highlight` | `--gs-color-warning-subtle` |

The `default` theme (labelled **"Snipe-IT Classic"**) does **not** map the brand variables (`--main-theme-color`, nav/link colours), so under it Snipe-IT's Branding colours and per-user colour fields work exactly as today. Every other theme maps them. See §7.4.

### 7.4 Snipe-IT pages and Snipe-IT's Branding settings

**Snipe-IT pages use the selected theme.** Every page rendered through `layouts/default`, `layouts/basic` or `layouts/setup` — Dashboard, Assets, Licenses, Accessories, Consumables, Components, People, Reports, Settings, Account, login, setup — gets the user's resolved theme (colours, fonts, layout variants, light/dark), exactly like gov-store pages. Nothing in any Snipe-IT page file changes: the theme arrives through the compile-time layout hook (§5) and the adapter CSS (§7.1–7.3).

**What changes and what doesn't on a Snipe-IT page:**

| Changes with the theme | Stays as Snipe-IT renders it |
|---|---|
| Colours of header, sidebar, boxes, tables, buttons, labels, forms, links, footer | Which menus, columns, fields, buttons and tabs exist |
| Fonts (incl. Bengali), radius, density, table style, card vs flat surfaces | Page layout order and content |
| Light / dark rendering | Snipe-IT behaviour and JavaScript |

**Snipe-IT's Branding settings** (Settings › Branding) and **profile colour fields** fall into two groups:

| Snipe-IT setting | Under any theme |
|---|---|
| **Identity:** `site_name`, logo, favicon, `brand` (logo/text display), `footer_text`, `support_footer`, `version_footer`, `logo_print_assets`, `show_url_in_emails`, `load_remote` | **Always applied, unchanged.** Themes never replace your logo, name or footer |
| **Custom CSS** (`custom_css`) | **Always applied, after the theme** — the administrator's final adjustment layer, as today |
| **Colours:** `header_color`, `nav_link_color`, `link_light_color`, `link_dark_color` (Branding) and the same three fields on each user's profile | **Used only by the "Snipe-IT Classic" theme** (`default`). Every other theme provides its own designed light and dark colours, so these fields have no effect while it is active |

Why the colour fields can't also apply to designed themes: a theme is a tested set of light **and** dark colours with guaranteed contrast. A single `header_color` chosen in Branding would break that guarantee (e.g. white header text on a light green chosen for Institutional Green's dark mode). Choosing "Snipe-IT Classic" restores the Branding-colour behaviour completely.

**Avoiding confusion without editing Snipe-IT pages:** when a theme other than Snipe-IT Classic is active, `gs-theme::foot` shows a small dismissible, translated notice on the Branding and profile routes (`settings.branding.index`, `profile`): *"Colour settings here apply only to the Snipe-IT Classic theme. Current theme: Institutional Green — manage themes in Appearance."* The notice is inserted by the theming script at runtime; the Snipe-IT views are untouched.

### 7.5 Core surface coverage checklist

Header/navbar · sidebar menu (levels 1–2, active, hover, collapsed) · content header & breadcrumbs · `.box` (all variants) · bootstrap-table (toolbar, header, rows, stripes, hover, selected, pagination, column chooser, sticky header) · forms (inputs, input-group, help, errors, required marker) · select2 (single, multi, dropdown, ajax) · datepicker · buttons (`.btn-*`, `.btn-theme`) · labels/badges · alerts & callouts · nav-tabs · modals · info-boxes & small-boxes (dashboard) · dropdown menus · tooltips/popovers · footer · login page (`basic`) · setup (`setup`).

---

## 8. Theme package format

### 8.1 Folder

```
themes/<key>/
├─ theme.json            required
├─ tokens.light.json     required
├─ tokens.dark.json      required
├─ overrides.css         optional — @layer gs-theme, tokens and .gs-* only
├─ preview.light.png     required for picker (1200×750)
├─ preview.dark.png      required for picker
└─ README.md             optional — intent, audience, notes
```

### 8.2 `theme.json`

```json
{
  "key": "institutional-green",
  "version": "1.0.0",
  "status": "published",
  "extends": "default",
  "label":       { "en-US": "Institutional Green", "bn-BD": "প্রাতিষ্ঠানিক সবুজ" },
  "description": { "en-US": "National government ERP look for daily operational work.",
                   "bn-BD": "দৈনন্দিন কাজের জন্য জাতীয় সরকারি ইআরপি চেহারা।" },
  "authors": ["UI/UX Team"],
  "fonts": { "sans": "noto-sans", "bengali": "noto-sans-bengali", "mono": "ibm-plex-mono" },
  "variants": {
    "header": "brand",
    "sidebar": "dark",
    "nav_icons": "shown",
    "density": "compact",
    "table": "grid",
    "surfaces": "card",
    "radius": "md",
    "type": "sans",
    "status_style": "tinted"
  },
  "swatches": ["#14532D", "#15803D", "#B42318", "#F7F9F8", "#1F2937"]
}
```

| Field | Rule |
|---|---|
| `key` | kebab-case, equals folder name, unique |
| `version` | semver, bumped by the developer on every change (shown in the Lab and picker tooltips) |
| `status` | `draft` (Theme Lab only — lets developers ship a theme to production for review before release) · `published` (selectable by users and admins) · `deprecated` (hidden from pickers; anyone on it falls back down the resolution chain, §12.2) |
| `extends` | another theme key or `null` (only `default`). Max depth 3, no cycles |
| `label`, `description` | `en-US` and `bn-BD` required |
| `fonts` | keys from the font registry (`theming/fonts/fonts.json`) |
| `variants` | each value must be a known option (§9) |

All of these are changed only by developers, in git, and take effect with a release.

### 8.3 Inheritance (`extends`)

| Part | Merge rule |
|---|---|
| `tokens.*.json` | deep merge by token path; child wins |
| `variants`, `fonts` | shallow merge; child wins |
| `overrides.css` | concatenated, parent first |
| `label`, `description`, previews | never inherited |

A colour variant of an existing theme can be ~10 lines: `extends` + four seeds in each mode file.

### 8.4 Validation (`gs-theme:validate`, also run by `build`)

| Check | Fails when |
|---|---|
| Schema | missing required field/file, unknown key, bad `extends` |
| Modes | either `tokens.light.json` or `tokens.dark.json` missing or empty after inheritance |
| Contract | any semantic token from §6.4 unresolved in either mode |
| References | `{…}` reference to unknown token, or reference cycle |
| Contrast (WCAG 2.2 AA), **both modes** | `text`/`surface`, `text`/`bg`, `text-muted`/`surface` (4.5:1); `on-primary`/`primary`, `on-accent`/`accent`, `header-fg`/`header-bg`, `sidebar-fg`/`sidebar-bg`, `sidebar-active-fg`/`sidebar-active-bg`, `color-on-{feedback}`/`{feedback}`, `link`/`surface` (4.5:1); `border-strong`/`surface`, `focus-ring`/`surface` (3:1) |
| Distinguishability | status colours that differ only in hue (ΔL < 0.08 in OKLCH) |
| Fonts | font key unknown, or a `bengali` slot missing |
| `overrides.css` | literal colours, `!important`, selectors not starting with `.gs-` or `[data-gs-` |
| Previews | missing `preview.light.png` / `preview.dark.png` for `published` themes |
| Dark mode authored *(warning for `draft`; error for `published`)* | dark file still marked `"$extensions": {"gs.scaffold": true}` from `gs-theme:make`, or `color-bg`/`color-surface` in dark have OKLCH lightness > 0.35 |

Output: human table plus `--json` for CI.

### 8.5 Ownership of light and dark modes

The **theme developer** designs and owns **both** modes of every theme they build — there is no separate dark-mode design track. To make this fast:

- `gs-theme:make` generates `tokens.dark.json` as a *scaffold* derived from the light seeds (lowered lightness, reduced chroma, inverted surfaces), marked `"gs.scaffold": true`.
- The developer refines it in the Theme Lab (matrix view shows light and dark side by side) and removes the scaffold marker.
- `gs-theme:build` (run in CI and at deploy) fails for a `published` theme while the scaffold marker is present or contrast fails in either mode — an unfinished theme cannot be released.

### 8.6 Who does what

| Who | Can do |
|---|---|
| **Theme developers** | Build themes as files (`gs-theme:make`, edit JSON, preview in Theme Lab locally), set `status`, ship them through git, code review and the normal release |
| **Super admin** | Choose (and optionally enforce) the **organisation** default; may also set any company or office default; preview `draft` themes in the Theme Lab in production |
| **Company admin** | Choose (and optionally enforce) the default theme for **their company** |
| **Office admin** | Choose (and optionally enforce) the default theme for **their office** |
| **Every user** | Choose their own theme (unless enforced) and Light / Dark / System mode |

Nobody can create, upload, edit or delete a theme inside the application. Adding, changing, deprecating or removing a theme is a code change.

**Removing a theme** (developer): first set `"status": "deprecated"` for at least one release so users, offices and companies on it fall back gracefully; then delete the folder. Saved preferences/assignments that point at a missing key are ignored by the resolver and cleaned up by `php artisan gs-theme:prune-references`.

---

## 9. Layout variants

Implemented once in `adapter/variants.css`; themes only choose values.

| Variant | Values | Effect |
|---|---|---|
| `header` | `brand` · `neutral` · `dark` · `floating` | Top bar fill: primary colour, surface colour with bottom rule, or near-black identity bar · `floating`: the header tokens on a rounded, shadowed card inset from the page edges (sidebar starts below it) |
| `sidebar` | `dark` · `light` · `brand` | Sidebar ground and text; `light` adds right hairline |
| `nav_icons` | `shown` · `hidden` | Hides first-level sidebar icons (text-only navigation); collapsed-mini mode always keeps icons |
| `nav_style` | `bar` · `pill` | Active/hover sidebar item: full-width row with a 3px left marker · rounded inset pill (sub-items indented), no marker. The tree sidebar itself is unchanged |
| `density` | `compact` (32px rows) · `regular` (38px) · `comfortable` (44px) | Table rows, control heights, box padding. Touch targets stay ≥ 44px on `pointer: coarse` |
| `table` | `grid` · `rules` · `ledger` | Full gridlines + zebra · row rules only · ledger rules, uppercase small headers, double-rule totals |
| `surfaces` | `card` · `flat` · `elevated` | Boxes with border/shadow · no cards, hairline section dividers and whitespace · borderless rounded cards on `shadow.1`, no box accent stripe, page title over a hairline, dashboard small boxes as white metric cards (tone figure, faded tone icon) |
| `controls` | `outlined` · `filled` | Form field fill: surface · sunken. Both keep `border-strong` (≥ 3:1) — a fill alone is too faint to mark a field (WCAG 1.4.11) |
| `radius` | `none` · `sm` (2px) · `md` (6px) · `lg` (10px) | Sets `--gs-radius-*` scale |
| `type` | `sans` · `serif-display` | Headings, KPI figures and page titles use `--gs-font-display` |
| `status_style` | `tinted` · `chip` · `text` | Badge rendering: tinted bordered pill with icon · mono uppercase chip · icon + coloured text, no fill |

### 9.1 Mapping the three design directions

| Variant | Theme 1 · Institutional Green | Theme 2 · Digital Blue | Theme 3 · Executive Neutral |
|---|---|---|---|
| `header` | brand | dark | dark |
| `sidebar` | dark | dark | light |
| `nav_icons` | shown | shown | hidden |
| `density` | compact | compact | comfortable |
| `table` | grid | rules | ledger |
| `surfaces` | card | card | flat |
| `radius` | md | sm | none |
| `type` | sans | sans | serif-display |
| `status_style` | tinted | chip | text |
| Seeds (light) | #14532D · #15803D · #1F2937 · #B42318 | #17365D · #2563EB · #0F766E · #D97706 | #263238 · #2F6B4F · #B68A3A · #20252B |
| Fonts | Noto Sans, Noto Sans Bengali | Inter, IBM Plex Mono, Noto Sans Bengali | Source Serif 4, Noto Sans, Noto Serif Bengali |

Estimated coverage of the artboards with zero core edits: Theme 1 ≈ 100%, Theme 2 ≈ 85% (status tabs/bulk bar on gov-store screens only), Theme 3 ≈ 85–90% (charcoal bar + light text-only sidebar achieved via `header: dark`, `sidebar: light`, `nav_icons: hidden`).

The artboards show light mode only. Dark modes for all three system themes are designed as part of Phase 4 (§8.5), starting from the generated scaffold.

### 9.2 Theme 4 · Lavender (2026-10-08)

Reproduces the *Gull* admin template look (reference snapshots in `docs/plans/theming/`) on Snipe-IT's own tree sidebar, with zero core edits. It needed four new variant values, all generic and reusable by any theme: `header: floating`, `nav_style: pill`, `surfaces: elevated`, `controls: filled`. Every other theme inherits `nav_style: bar` and `controls: outlined` from `default`, so their rendering is unchanged.

| Variant | Lavender |
|---|---|
| `header` · `sidebar` · `nav_style` | floating · light · pill |
| `density` · `table` · `surfaces` · `controls` | regular · rules · elevated · filled |
| `radius` · `type` · `status_style` | lg · sans · tinted |
| Seeds (light) | #663399 (rebeccapurple) · #52495A · #332E38 · #C21F3F |
| Fonts | Nunito (new registry key `nunito`), IBM Plex Mono, Noto Sans Bengali |

Deliberate deviations from the reference, for WCAG 2.2 AA: success/warning are darkened (#2E7D32, #A35A00) so white text passes 4.5:1, and filled inputs keep a 3:1 border. Not reproduced (structural, out of scope per §1): Gull's horizontal and two-panel sidebar layouts, the floating customizer panel, and full-bleed photo sign-in pages.

---

## 10. Build and serve

### 10.1 Commands

| Command | Does |
|---|---|
| `php artisan gs-theme:build` | Validate all themes → resolve inheritance & derivations → emit hashed CSS, copy fonts, bundle registered package CSS/JS → write `manifest.json` |
| `php artisan gs-theme:validate [key] [--json]` | Validation only (§8.4) |
| `php artisan gs-theme:make {key} [--extends=default] [--from=key]` | Scaffold a theme folder (`--from` copies seeds and variants of an existing theme) |
| `php artisan gs-theme:compliance [--update-baseline]` | Gov-store compliance report (§15.3) |
| `php artisan gs-theme:prune-references` | Clears preferences/assignments pointing at themes removed in a release (§8.6) |

### 10.2 Output

```
public/vendor/gs-theme/
├─ manifest.json
├─ kit.{hash}.css                 # adapter vars + adapter + variants + UI kit (shared by all themes)
├─ packages.{hash}.css            # CSS registered by gov-store packages
├─ themes/{key}.{hash}.css        # tokens light + dark for one theme (+ its overrides)
├─ gs-theme.{hash}.js
└─ fonts/*.woff2
```

The page loads `kit` + `packages` + **only the active theme**. The Theme Lab and Appearance preview additionally load the other themes' CSS on demand.

### 10.3 Environments

| Env | Behaviour |
|---|---|
| `production` | `gs-theme::head` reads `manifest.json`; static files served by the web server, long-lived cache headers, hash busting. Missing manifest → log warning, fall back to stock Snipe-IT look |
| `local` / `staging` | `DevAssetController` serves `/gs-theme-dev/{file}.css`, recompiling when any theme/adapter/kit file mtime changes. Designers edit JSON → refresh |

Deploy step: `php artisan gs-theme:build` alongside `php artisan optimize:clear`.

### 10.4 Fonts

Self-hosted woff2 (all OFL-licensed: Noto Sans, Noto Sans Bengali, Noto Serif Bengali, Source Serif 4, Inter, IBM Plex Mono) in `theming/fonts/`, registered in `fonts/fonts.json` with `unicode-range` subsets so Bengali files load only when Bengali text renders. No Google Fonts calls (government intranets often block them).

### 10.5 Browser support baseline

The baseline is **set by upstream Snipe-IT**, not by theming: the core layout already depends on `light-dark()` and relative colour syntax (`hsl(from …)`). Theming itself needs less (custom properties + cascade layers), because derivations are resolved to literal colours at build time.

| Browser | Minimum | Limiting feature (from upstream) |
|---|---|---|
| Chrome / Edge (Chromium) | **123** | `light-dark()` |
| Firefox | **128** | relative colour syntax |
| Safari | **18** | relative colour syntax (complete support) |
| Internet Explorer, legacy Edge | not supported | — |

Consequences and actions:

1. **Windows 7 / 8.1 machines are capped at Chrome/Edge 109** — they cannot render upstream Snipe-IT's current colours correctly, with or without themes. Before Phase 4, the ICT team pulls a user-agent report from web server logs to size this.
2. `gs-theme::head` shows a dismissible, translated **"browser not supported"** notice when `CSS.supports('color', 'light-dark(#000, #fff)')` is false.
3. Visual tests (§16.4) run on Chromium; a smoke subset also runs on Firefox and WebKit.

---

## 11. Light / dark mode

| Concern | Design |
|---|---|
| Modes per user | `light` · `dark` · `system` (default `system`) |
| Server render | Hook writes `data-theme` on `<html>` from the user's saved mode → explicit modes render correctly on first paint |
| `system` mode | Inline nonce'd head script sets `data-theme` from `prefers-color-scheme` before first paint, and listens for OS changes |
| Snipe-IT toggle | Unchanged. Head script writes the resolved mode into `localStorage.theme` before Snipe-IT's toggle script reads it, so they agree. `gs-theme.js` listens for clicks on `[data-theme-toggle]` and POSTs the new mode to `/gov/appearance/mode` |
| Flash of wrong theme | Eliminated for all users (today dark users see a light flash) |
| Charts | `gs:mode-change` event; Chart.js plugin redraws (§13) |
| Print | `@media print` forces light tokens of the active theme |
| Guests (login page) | Org default theme; mode from `localStorage` / system |

---

## 12. Theme resolution and preferences

### 12.1 Scopes

Terminology follows `TenantContext`: **organisation** = the whole system; **company** = ministry / tenant (`companyId`); **office** = location (`locationId`, the user's *working* office).

| Scope | Who sets it (§12.6) | Can enforce? |
|---|---|---|
| Organisation default | superuser | yes |
| Company default | company admin of that company (superuser: any) | yes |
| Office default | office admin of that office (superuser: any) | yes |
| User preference | the user, if `allow_user_choice` is on | — |

### 12.2 Resolution chain

```
1. ?gs_preview=<key>&gs_mode=<mode>      only with theming.lab.view; never saved
2. Enforced assignment, top-down          organisation → company → office
                                          (a higher authority's enforcement cannot be overridden below it)
3. User preference                        if gs-theme.allow_user_choice = true
4. Office default                         TenantContext->locationId (working office)
5. Company default                        TenantContext->companyId
6. Organisation default                   gs_theme_assignments (scope organization)
7. Config default                         GS_THEME_DEFAULT = institutional-green
8. default
```

- Unknown keys (e.g. a theme removed in a release), `draft` themes (except in Lab preview) and `deprecated` themes fall through to the next step.
- Non-enforced defaults resolve **bottom-up** (most specific wins); enforcement resolves **top-down** (most senior wins).
- Mode (light/dark/system) always comes from the user preference, else `system`; scopes assign themes, not modes.
- Resolved **lazily at render time** in `gs-theme::head` (after `InitializeTenantContext` has run), memoised per request. Guests (login page) get steps 6–8.
- Switching working office (`gov_working_membership_id`) switches to that office's default unless the user has their own preference — a deliberate visual cue of the active office.
- Assignments are cached (`gs-theme:assignments:{scope}:{id}`), busted on save.

### 12.3 Data

```
gs_theme_preferences
  id              bigint PK
  user_id         FK users.id, unique, cascade on delete
  theme           varchar(64) null      -- null = follow office/company/organisation default
  mode            enum('light','dark','system') default 'system'
  timestamps

gs_theme_assignments
  id              bigint PK
  scope_type      enum('organization','company','office')
  scope_id        bigint null           -- null only for organization; companies.id / locations.id otherwise
  theme           varchar(64)
  enforced        boolean default false
  updated_by      FK users.id null
  timestamps
  unique(scope_type, scope_id)
```

Every change to an assignment is written to the application log with actor, scope, old → new theme and `enforced` flag.

### 12.4 Config (`config/gs-theme.php`, package-owned, merged)

```php
return [
    'default'             => env('GS_THEME_DEFAULT', 'institutional-green'),
    'allow_user_choice'   => env('GS_THEME_ALLOW_USER_CHOICE', true),
    'lab_enabled'         => env('GS_THEME_LAB', true),          // kill switch only; access is by ability
    'themes_path'         => base_path('packages/gov-store/theming/themes'),   // shipped themes
    // permission strings added to tenant-scope capability profiles (§12.6)
    'permission_profiles' => [
        'company_operations' => ['theming.assign.company'],   // company admins
        'office_operations'  => ['theming.assign.office'],    // office admins
    ],
];
```

### 12.5 Routes (package `routes/web.php`, `web` + `auth`, each with a breadcrumb)

| Method | URI | Name | Ability |
|---|---|---|---|
| GET | `/gov/appearance` | `gs-theme.appearance` | `theming.appearance.self` |
| PUT | `/gov/appearance` | `gs-theme.appearance.update` | `theming.appearance.self` |
| POST | `/gov/appearance/mode` | `gs-theme.appearance.mode` | `theming.appearance.self` |
| GET | `/gov/appearance/assignments` | `gs-theme.assignments` | any `theming.assign.*` |
| PUT | `/gov/appearance/assignments/{scope}/{id?}` | `gs-theme.assignments.update` | matching `theming.assign.{scope}` + scope check |
| GET | `/gov/theme-lab` | `gs-theme.lab` | `theming.lab.view` |
| GET | `/gov/theme-lab/{theme}` | `gs-theme.lab.focus` | `theming.lab.view` |

Validation: `theme` → `nullable|string|in:<published theme keys>`; `mode` → `in:light,dark,system`; `enforced` → `boolean`.

### 12.6 Access control — role-based **and** permission-based

Theming plugs into tenant-scope's existing access model instead of inventing its own.

**Abilities** (`theming/config/abilities.php`, merged into `govstore-abilities` in `ThemingServiceProvider::register()` so tenant-scope defines their Gates and lists them in the Access matrix):

| Ability | Roles (role-based) | Permission string (permission-based) | Scope check |
|---|---|---|---|
| `theming.appearance.self` | `authenticated` | — | own preference only; denied when `allow_user_choice` is off |
| `theming.assign.office` | **`office_admin`** | `theming.assign.office` (profile `office_operations`) | `scope_id` must equal `TenantContext->locationId` (superuser: any) |
| `theming.assign.company` | **`company_admin`** | `theming.assign.company` (profile `company_operations`) | `scope_id` must equal `TenantContext->companyId` (superuser: any) |
| `theming.assign.organization` | `superuser` | — | `national: true` |
| `theming.lab.view` | `superuser` | — | review shipped themes (incl. `draft`) in production; Lab uses fixture data · `national: true`, `enforce: true` |

There is **no ability to create, upload or edit themes** — themes come only from the release (§8.6). Every admin ability is a *choose* ability for a scope. `theming.lab.view` is super-admin only in production and has no permission-string path; developers use the Theme Lab in their local and staging environments.

**Decision rule** (`ThemeAccess`):

```php
public function allows(User $user, string $ability, ?int $scopeId = null): bool
{
    $granted = app(GovAccess::class)->decide($user, $ability)->allowed      // role-based
            || app(TenantContext::class)->hasPermission($ability);          // permission-based

    return $granted && $this->withinScope($user, $ability, $scopeId);
}
```

- **Role-based:** roles resolved by `GovAccess::roles()` — `office_admin` for the working office, `company_admin` for the company, `superuser` everywhere.
- **Permission-based:** the strings in `gs-theme.permission_profiles` are appended to tenant-scope's capability profiles at register time. By default `office_operations` (mapped from the `office_admin` responsibility) gets `theming.assign.office` and `company_operations` (mapped from `company_admin`) gets `theming.assign.company`. Another profile can be given the same choose-only permission in config without code changes; it can never be given manage/lab rights.
- **Why not Snipe-IT group permissions:** adding new keys to Snipe-IT's group permission editor requires editing core `config/permissions.php` — excluded by §2.2.
- **Soft dependency:** if tenant-scope is not installed, `ThemeAccess` falls back to `isSuperUser()` for every `theming.*` ability except `theming.appearance.self`.

### 12.7 Appearance page (user)

- Theme cards (radio group): name, description, light + dark preview images, swatch strip, tags for "Office default" / "Company default" / "Organisation default". Only **published** themes are listed.
- If an enforced assignment applies, the picker is read-only with a translated notice naming the scope ("Your ministry uses Institutional Green"); mode can still be changed.
- Mode segmented control: Light · Dark · System.
- **Live preview before saving:** selecting a card swaps `data-skin` and loads that theme's CSS link; Cancel restores.
- "Reset to default" clears `theme` (follow office/company/organisation).
- Entry point: user menu and gov-store navigation via the existing gov-store menu registry (no core view edit).
- Strings in `theming/lang/{en-US,bn-BD}/appearance.php`.

### 12.8 Assignments page (administrators)

- One card per scope the viewer may manage: Organisation (superuser), Company (company admin → own company; superuser → company picker), Office (office admin → working office; superuser → office picker).
- Each card: theme select (**published** themes only, with previews), "Enforce for everyone in this scope" switch, current effective theme for that scope, last changed by/at.
- Admins (super admin included) only **choose** here; there is no create, upload, edit or design control anywhere in the application.
- Shows inheritance: "Not set — inherits *Digital Blue* from Ministry of Health".
- Saving clears the assignment cache for that scope.

---

## 13. JavaScript: `gs-theme.js`

```js
GS.theme.key            // 'institutional-green'
GS.theme.mode           // 'light' | 'dark'
GS.theme.token('color-primary')         // resolved CSS value from computed style
GS.theme.palette(n)                     // n chart colours from --gs-chart-1..10
GS.theme.on('change', ({key, mode}) => …)   // fires on data-skin / data-theme mutation
```

**Chart.js v2 global plugin** (`Chart.plugins.register({ id: 'gsTheme', … })`), registered in the foot include; then `Chart.helpers.each(Chart.instances, c => c.update())` for charts created earlier.

**Policy — "theme the frame, keep the meaning":**

| Chart element | Behaviour | Why |
|---|---|---|
| Grid lines, ticks, axis titles, legend text, tooltips (all charts) | Always themed: `--gs-chart-grid`, `--gs-chart-label`, surface/text tokens; redraw on mode change | Readability in both modes is the theme's job |
| Slice / bar borders (all charts) | `borderColor` = `--gs-color-surface`, 1px | Keeps adjacent segments distinguishable in dark mode, even with dark admin colours |
| Core series coloured by an **admin-chosen** status-label colour | **Unchanged** | The colour carries meaning admins chose (e.g. red = broken) |
| Core series using Snipe-IT's **fallback palette** (`Helper::defaultChartColors()`) | Re-mapped index-for-index to `--gs-chart-1…10` | These colours carry no meaning; they should match the theme |
| Gov-store charts | Always `GS.theme.palette()` | Built on tokens (R5) |

How fallback colours are detected: `gs-theme::foot` reads the fallback list by **calling** the core helper `Helper::defaultChartColors($i)` (read-only use, no edit) and passes it to JS; the plugin re-maps only dataset colours that exactly match an entry. If upstream changes the palette, detection updates automatically.

Opt-out per chart: `options.plugins.gsTheme = false`. Force full recolour: `options.plugins.gsTheme.recolorData = 'all'`.

---

## 14. Phase 5 in detail — UI kit and Theme Lab

### 14.1 UI kit principles

1. Anonymous Blade components under `gs-theme::components`, used as `<x-gs::name>`.
2. Styles only through `--gs-*` tokens, in `@layer gs-components`, on `.gs-*` classes.
3. Every component reacts to the relevant `data-gs-*` variants **automatically** — callers never pass theme decisions. An explicit prop may override a variant locally (e.g. `table="ledger"` for a printable register).
4. Bootstrap 3 / AdminLTE-compatible markup inside, so components sit naturally next to core screens.
5. Accessible as built: real `<button>`/`<a href>`/`<label>`, visible focus ring (`--gs-color-focus-ring`), `aria-current`, `aria-label` on icon-only controls, status never conveyed by colour alone (icon + text).
6. Bilingual: any `title`/`label` prop accepts an optional `bn` companion rendered in `--gs-font-bengali`.

### 14.2 Component catalogue

| Component | Key props / slots | Variants it follows | States shown in Lab |
|---|---|---|---|
| `<x-gs::page-header>` | `title`, `bn`, `subtitle`, slot `actions`, slot `meta` | `type`, `surfaces` | with/without actions, long title wrap, Bengali |
| `<x-gs::box>` | `title`, `icon`, `tone` (default·primary·muted), `collapsible`, slots `tools`, `footer` | `surfaces`, `radius`, `density` | each tone, collapsed, with footer, loading |
| `<x-gs::table>` | `columns` or default slot, `table` override, `density` override, `sticky`, `caption`, slots `totals`, `empty`; `bootstrap` mode emits core bootstrap-table `data-*` attributes for API-driven lists | `table`, `density`, `radius` | grid/rules/ledger, zebra, hover, selected, totals row, empty, overflow scroll |
| `<x-gs::status-badge>` | `status` (key in `status-map.php`), `label` override, `size` | `status_style`, `radius` | every document + asset status, both sizes |
| `<x-gs::status-tabs>` | `tabs: [{key,label,count,href}]`, `active` | `radius`, `density` | active, zero counts, overflow on narrow screens |
| `<x-gs::bulk-bar>` | `for` (table id), slot `actions`; listens to bootstrap-table `check`/`uncheck` events | `density` | hidden, 1 selected, many selected |
| `<x-gs::stepper>` | `steps: [{key,label,state}]` (`done·current·upcoming·blocked`), `orientation` | `status_style`, `density` | Draft → Ready → Posted, blocked step, vertical |
| `<x-gs::kpi>` | `label`, `value`, `bn`, `delta`, `href`, `icon`, `tone` | `type`, `surfaces` | with delta up/down, linked, large numbers |
| `<x-gs::filter-bar>` | slot fields, `reset-href` | `density` | collapsed on mobile, with active filters |
| `<x-gs::key-value>` | `items: [{label,value}]`, `columns` | `density`, `type` | 1/2/3 columns, long values |
| `<x-gs::timeline>` | `items: [{at,by,action,target}]` | `density` | activity list, empty |
| `<x-gs::form-row>` | `label`, `for`, `required`, `help`, `error`, default slot | `density` | required, error, help, Bengali label |
| `<x-gs::alert>` | `tone` (info·success·warning·danger), `dismissible`, slot | `radius`, `surfaces` | all tones, dismissible |
| `<x-gs::empty-state>` | `icon`, `title`, slot `actions` | `type` | with/without action |
| `<x-gs::document>` | slots `emblem`, `office`, `meta`, default, `signatures`, `footer`; `doc-no`, `date` | `table` (forced `ledger` in print), print tokens | screen view, print preview |

`status-map.php` (single source for badge and stepper semantics):

```php
return [
    'draft'     => ['tone' => 'doc-draft',     'icon' => 'fa-pen'],
    'ready'     => ['tone' => 'doc-ready',     'icon' => 'fa-circle-check'],
    'posted'    => ['tone' => 'doc-posted',    'icon' => 'fa-lock'],
    'cancelled' => ['tone' => 'doc-cancelled', 'icon' => 'fa-ban'],
    'deployed'  => ['tone' => 'status-deployed', 'icon' => 'fa-user'],
    // …
];
```

### 14.2a General-purpose kit (Lab section 6, `components/15-general.css`)

Added with Lavender; every component follows the active theme, mode and variants (`surfaces: elevated` makes cards, KPIs, accordions and list cards borderless with `shadow.1`).

| Component | Key props / slots |
|---|---|
| `<x-gs::button>` | `tone` (primary · secondary · success · danger · warning · info · light · dark), `outline`, `pill`, `size` (sm · md · lg), `icon`, `href`, `loading`, `disabled` |
| `<x-gs::button-group>` | `label`; slot of buttons |
| `<x-gs::badge>` | `tone`, `outline`, `pill`, `floating` (count pinned to the parent's corner), `label` (screen-reader text). Renders `.gs-tag`; workflow states keep using `status-badge` |
| `<x-gs::spinner>` | `type` (ring · dots · pulse), `tone` (or `current`), `size`, `label` (`false` when the control already announces it) |
| `<x-gs::progress>` | `value`, `max`, `tone`, `label`, `show-value`, `caption`, `size` — `role="progressbar"` |
| `<x-gs::accordion>` / `accordion-item` | native `<details>`; `name` makes it exclusive; item `title`, `icon`, `open` |
| `<x-gs::card>` | `title`, `subtitle`, `header`, `image`/`image-alt` or `media` slot, `align`, `href`; slots `actions`, `footer` |
| `<x-gs::tile>` | `title`, `meta`, `icon`, `tone`, `filled`, `href` |
| `<x-gs::list>` / `list-item` | list `divided` · `cards`; item `title`, `subtitle`, `href`, `image` · `avatar` · `icon`, slots `meta`, `actions` |
| `<x-gs::avatar>` | `name` (initials), `image`, `size`, `tone`, `decorative` |
| `<x-gs::tabs>` / `tab-panel` | `id` (required), `tabs`, `active`, `justified`, `label`; ARIA tabs with arrow/Home/End keys in `gs-theme.js` |
| `<x-gs::kpi>` (extended) | + `layout` (stack · icon-left · centered), `spark` (numbers → inline SVG sparkline), `progress`, `caption` |
| `<x-gs::alert>` (extended) | + `appearance` (tinted · outline · card) |

Cascade note: Snipe-IT's base CSS is unlayered, so it beats layered kit rules on bare elements (`a { background-color: transparent }`, `summary { display: list-item }`); link-rendered buttons/tiles/KPIs and the accordion summary restate those properties with `!important`.

### 14.3 Theme Lab — purpose

One page where a UI/UX developer reviews a theme completely — every token, every core surface, every kit component and representative gov-store patterns — in **light and dark**, and compares themes side by side, without clicking through the app. It is also the primary fixture for visual regression tests (§16.4).

### 14.4 Access

The Theme Lab is a **developer tool**. Developers use it locally and on staging while building themes; in production it lets the super admin review shipped themes before choosing defaults (§12.6):

| Setting | Behaviour |
|---|---|
| Ability `theming.lab.view` | Production: `superuser` only (review shipped and `draft` themes before choosing defaults). Local / staging: developers, who are superusers in their own environments. Denied users get tenant-scope's standard access-denied page |
| `GS_THEME_LAB` | Kill switch only (default `true`); when `false` the routes return 404 for everyone |
| Data | Static fixture data only (no reads of real assets, users or offices) — safe to open in production |
| Preview links | `?gs_preview=` honoured only for users holding `theming.lab.view`; never persisted |
| `draft` themes | Visible only in the Lab and via `gs_preview`, so a theme can be shipped to production for review before developers flip it to `published` |

### 14.5 Two views

**Matrix view** `/gov/theme-lab`
- Grid of panels: rows = themes (filterable), columns = Light / Dark.
- Each panel is a wrapper `<div data-skin="…" data-theme="…" data-gs-*="…">`; because tokens are emitted on non-`html`-qualified selectors (§6.5) every panel renders its own theme independently.
- All themes' CSS loaded on this page only.
- Shell variants (header/sidebar) render as a scaled **mini shell** built from the same tokens (AdminLTE's real header/sidebar are `position: fixed` and page-global). Labelled "approximation — open Focus view for the real shell".
- select2/datepicker popups use `dropdownParent` set to the panel so they inherit the panel's theme.

**Focus view** `/gov/theme-lab/{theme}?mode=dark`
- Real application shell rendered with `gs_preview` override (not saved).
- Full component catalogue at real size.
- Toolbar: theme switcher, Light/Dark/System, **variant override dropdowns** (try `table: ledger` on Theme 2 without editing files — shows the JSON line to paste into `theme.json`), density, Bengali/English sample text toggle, "show token names" overlay.

### 14.6 Lab sections (both views, each with a stable `data-lab-section` id)

| # | Section | Contents |
|---|---|---|
| 1 | Foundations · Colour | Every semantic token as a swatch: name, resolved value, **live contrast ratio** against its pair, AA pass/fail chip |
| 2 | Foundations · Type | Scale (display, h1–h4, body, small, mono), English + Bengali paragraphs, tabular figures |
| 3 | Foundations · Shape & density | Radius scale, borders, shadows, spacing scale, row/control heights |
| 4 | Core surfaces | Header, sidebar (normal/active/collapsed), breadcrumbs, `.box` variants, bootstrap-table sample, forms, select2, datepicker, buttons, labels, alerts, callouts, nav-tabs, modal, info-boxes, dropdown, pagination |
| 5 | UI kit | Every component in §14.2 in every listed state |
| 6 | Patterns | List page (page-header + status-tabs + filter-bar + table + bulk-bar), Goods Receipt workspace (stepper + key-value + table with totals + timeline), Dashboard (KPI strip + chart + activity) — mirroring the design artboards |
| 7 | Charts | Bar, pie, line with palette `chart-1…10`; redraw on mode switch |
| 8 | Print | `<x-gs::document>` in print-preview frame (forced light) |
| 9 | Validation report | Output of `gs-theme:validate` for the theme(s) shown: contract, contrast, fonts, overrides lint |

### 14.7 Acceptance criteria for Phase 5

- All 15 kit components implemented, documented in the Lab with their states.
- Each component renders correctly under every value of the variants it follows, in both modes, in all four themes.
- Lab matrix shows 4 themes × 2 modes on one page; focus view supports variant overrides and `gs_preview`.
- Lab contrast panel matches `gs-theme:validate` results.
- Keyboard-only walk-through of the Lab passes (focus visible everywhere, no traps).
- Every Lab section has a `data-lab-section` id used by the visual tests.

---

## 15. Gov-store compliance

### 15.1 Rules

| # | Rule |
|---|---|
| R1 | No colour literals (`#hex`, `rgb[a]()`, `hsl[a]()`, named colours other than `transparent`/`currentColor`/`inherit`) in package views, CSS or JS — use `var(--gs-*)` / `GS.theme.token()` |
| R2 | No `<style>` blocks in views. Package CSS lives in `src/resources/css/*.css`, registered with `AssetRegistry`, emitted in `@layer gs-packages` |
| R3 | No inline `style=` with colour, background, border-colour, font-family or radius |
| R4 | New screens use UI kit components for page header, boxes, tables, badges, tabs, steppers, forms |
| R5 | Charts use `GS.theme.palette()` and listen for `gs:mode-change` |
| R6 | Print views use `<x-gs::document>` |
| R7 | Must look correct in every theme × mode (checked via Theme Lab patterns + visual tests) |

### 15.2 Registering package assets

```php
// in a gov-store package's ServiceProvider::boot()
GsTheme::assets()->css('storeops', __DIR__.'/../resources/css/storeops.css');
GsTheme::assets()->js('storeops',  __DIR__.'/../resources/js/storeops.js');   // optional
```

`gs-theme:build` bundles all registered package CSS into `packages.{hash}.css`, wrapped in `@layer gs-packages`, and runs R1 on it.

### 15.3 Enforcement: ratchet test

- `tests/Feature/GovStore/Theming/GovStoreThemeComplianceTest.php` scans `packages/gov-store/**` (excluding `theming/themes/**` and `theming/fonts/**`).
- Baseline file `theming/compliance-baseline.json` records today's violations per file per rule.
- Test **fails** if a file's count goes up, a new file has any violation, or a count went down but the baseline wasn't lowered (forces the ratchet to tighten). `php artisan gs-theme:compliance --update-baseline` rewrites it (only downward).
- Target: baseline empty at the end of Phase 6.

### 15.4 Migration recipe (per view)

1. Move `<style>` contents into the package CSS file; replace literals with tokens (add a semantic token to the contract if a genuinely new role appears — never a one-off colour).
2. Replace hand-written `.box`/`.table`/badges/tabs with kit components.
3. Remove inline colour styles.
4. Check the screen in Theme Lab focus view (via `gs_preview`) in all themes × modes.
5. Lower the baseline.

Order and status (hex-literal counts are the figure recorded when this plan was written; §15.3's baseline is the live count — run `php artisan gs-theme:compliance` for current numbers):

| Package | R1 at plan time | Status | Migrated |
|---|---|---|---|
| store-operations | 274 | not started | — |
| **tracking** | **225** | **done** | **2026-10-07** — all 13 views, `tracking.css` registered, baseline entries removed |
| classification | 145 | not started | — |
| custom-requests | 77 | not started | — |
| organization | 65 | not started | — |
| office-membership | 30 | not started | — |
| tenant-scope | 3 | not started | — |
| user-onboarding | 1 | not started | — |

Within a package, highest-traffic screens first (Store Documents Hub, Goods Receipt workspace, Stock Register Dashboard, Fulfillment Queue).

Print views to convert to `<x-gs::document>`: `store-operations/…/operations/print.blade.php` (remaining), `tracking/…/initiatives/report.blade.php` (done), `tracking/…/tracking_codes/view_task_component.blade.php` (done).

> **Note (2026-10-07):** migrating `tracking` also surfaced and fixed a pre-existing bug in `compliance-baseline.json`: 19 `store-operations` entries were keyed under a stale `src/Resources/` (capital R) path that no longer matches the real `src/resources/` directory, so the ratchet test was silently not checking them (they read as "new"/already-fixed instead of as still-outstanding violations). The baseline now points at the real paths with their original counts unchanged — this does not mean store-operations was migrated, only that its existing violations are enforced again.

---

## 16. Testing strategy

### 16.1 PHPUnit (`tests/Feature/GovStore/Theming/`)

| Test | Covers |
|---|---|
| `ThemeRepositoryTest` | discovery, `extends` merge rules, depth/cycle errors, status filtering |
| `TokenResolverTest` | references, derivations (OKLCH), cycles |
| `ThemeValidationTest` | every rule in §8.4 with fixture themes (good + each failure) |
| `ThemeResolverTest` | every step of §12.2: preview, top-down enforcement (organisation beats office), user preference, bottom-up defaults (office beats company beats organisation), working-office switch, guests, `allow_user_choice=false`, draft/deprecated/removed-theme fallback |
| `AppearanceControllerTest` | save theme/mode, validation errors, enforced scope makes theme read-only, mode AJAX endpoint, guests redirected |
| `AssignmentControllerTest` | office admin can set own working office only; company admin own company only; superuser any scope; organisation scope superuser-only; `enforced` flag; cache busted; change logged |
| `ThemeAccessTest` | role path (office_admin → own office, company_admin → own company, superuser → any scope + Lab), permission path (`office_operations` / `company_operations` profile strings), no other role can reach the Lab, scope checks, fallback when tenant-scope is absent |
| `ThemeLabAccessTest` | 404 when kill switch off; denied page without ability; 200 via role; 200 via permission; `gs_preview` ignored without ability |
| `ChartPaletteTest` | foot include exports exactly the core fallback palette from `Helper::defaultChartColors()` |
| `GovStoreThemeComplianceTest` | ratchet (§15.3) |
| `BuildCommandTest` | manifest contents, hashing, failure on invalid theme |

### 16.2 Upstream contract test (`SnipeItContractTest`)

- Compiles each target layout through Blade and asserts: `gs-theme-hook:v1` marker present, `<html>` carries `data-skin`, `gs-theme::head` and `gs-theme::foot` included, and the theme head appears **before** the Custom CSS block.
- Renders a Snipe-IT page under Snipe-IT Classic with a custom `header_color` and asserts it is used; under Institutional Green asserts the theme colour is used and the Branding notice appears on `settings.branding.index`.
- Parses the core layout's inline `<style>` and asserts every Snipe-IT variable listed in `adapter/snipeit-vars.css` still exists.
- Asserts the AdminLTE/Bootstrap class names used in `adapter/snipeit.css` still appear in core views (smoke check).

### 16.3 Rendering tests

- Dashboard, an asset list, login, and one gov-store screen render with each theme key (200, correct `data-skin`, correct CSS link).

### 16.4 Visual regression (Playwright)

- New files: `scripts/live-tests/theme-visual/playwright.config.mjs`, `theme-visual.spec.mjs`; `package.json` script `test:themes`.
- Targets: every Theme Lab focus view section (`data-lab-section`) × 4 themes × 2 modes, plus 6 real pages (dashboard, assets list, asset detail, login, Store Documents Hub, Goods Receipt workspace).
- `toHaveScreenshot` with per-section baselines; run on every PR touching `theming/**` and after every upstream merge.

---

## 17. Phases

Estimates are rough person-days for one developer familiar with the codebase. Phase 4 is done by theme developers and includes designing both light and dark modes.

| # | Phase | Key tasks | Deliverables | Done when | Est. |
|---|---|---|---|---|---|
| 0 | **Spike** | Package skeleton + auto-discovery; `LayoutHook` on `default` & `basic`; one layered adapter rule beating a core `!important`; seed → OKLCH derivation; light+dark tokens for one test theme; nonce'd head mode script | Branch with working proof | Dashboard & login re-themed in both modes, no flash, core diff = 0, `view:cache` works | 2–3 |
| 1 | **Token contract & adapter** | DTCG schema; semantic catalogue (§6.4); `snipeit-vars.css`; `snipeit.css` covering §7.5; `default` theme ("Snipe-IT Classic") reproducing stock look incl. Branding colours; Branding/profile notice (§7.4) | Adapter + `default` theme | Every Snipe-IT screen in `default` matches stock Snipe-IT in light & dark (visual diff); every Snipe-IT screen follows a non-default theme | 5–7 |
| 2 | **Layout variants** | All variants in §9 in `variants.css` | Variant CSS | Each value visibly correct on core shell, tables, boxes | 3–4 |
| 3 | **Engine & tooling** | Repository, inheritance, compiler, `ColorMath`, contrast checker, build/validate/make commands (dark scaffold), manifest, dev asset route, unsupported-browser notice, toggle sync, translations | Engine | A new theme ships by adding a folder + `gs-theme:build` | 5–6 |
| 3a | **Preferences, scopes & access** | `gs_theme_preferences` + `gs_theme_assignments` migrations; full resolver (§12.2) on `TenantContext`; `theming.*` abilities merged into `govstore-abilities`; permission strings into `office_operations` / `company_operations` profiles; `ThemeAccess`; Appearance page; Assignments page; audit logging | Users pick theme & mode; organisation/company/office admins set (and optionally enforce) defaults | Resolver, access and controller tests green; abilities visible in the Access matrix | 4–5 |
| 4 | **Three themes** *(theme developers)* | Institutional Green, Digital Blue, Executive Neutral — seeds, variants, fonts, **designed** light and dark tokens (from scaffold), previews | 3 theme folders | All pass `gs-theme:validate` with no scaffold markers, both modes | 5–8 |
| 5 | **UI kit & Theme Lab** | 15 components (§14.2), `status-map.php`, Lab matrix + focus views, sections 1–9 | Kit + Lab | Acceptance criteria §14.7 | 7–10 |
| 6 | **Gov-store migration** | Compliance test + baseline; asset registration in each package; migrate views per §15.4 | Compliant packages | Baseline empty | 10–15 |
| 6.tracking | ↳ `tracking` package (part of Phase 6) | 13 views migrated; `tracking.css` registered via `GsTheme::assets()`; 2 of 3 print views converted to `<x-gs::document>` | `tracking` fully compliant | `tracking/*` removed from baseline; `GovStoreThemeComplianceTest` green | done 2026-10-07 |
| 7 | **Charts & print** | Chart.js plugin with the §13 policy (frame themed, admin colours kept, fallback palette re-mapped); print tokens; convert 3 print views to `<x-gs::document>` | Themed charts & prints | Charts redraw on mode switch; admin status colours unchanged; prints always light & legible | 3–4 |
| 8 | **Safety net** | Contract test, rendering tests, Playwright visual suite (Chromium + Firefox/WebKit smoke), upstream-merge checklist | Tests in CI | All green; checklist adopted | 3–4 |
| | | | | **Total** | **≈ 47–65** |

Dependencies: 0 → 1 → 2 → {3, 5}; 3a needs 3; 4 needs 1–3 (Lab from 5 helps but is not required); 6 and 7 need 5; 8 runs alongside from Phase 1.

Pre-Phase-4 action (ICT team): user-agent report from web server logs to size the share of browsers below the §10.5 baseline.

---

## 18. Theme authoring guide (for UI/UX developers)

1. `php artisan gs-theme:make civic-teal --from=institutional-green`
2. Edit `themes/civic-teal/theme.json`: labels (en-US, bn-BD), description, `variants`, `fonts`, `swatches`.
3. Design **light mode** in `tokens.light.json` — start with the four seeds; override semantic tokens only where derivation isn't right. (Or export from Figma Tokens Studio.)
4. Design **dark mode** in `tokens.dark.json` — you own it. Start from the generated scaffold, tune grounds, surfaces, text and status colours, then delete the `"gs.scaffold": true` marker.
5. Open `/gov/theme-lab/civic-teal` (local) — every save is picked up on refresh. The matrix view shows light and dark side by side. Use the variant dropdowns to experiment; paste the shown JSON into `theme.json`.
6. Fix anything the Lab's validation panel flags (contrast, missing tokens, scaffold marker) in **both** modes.
7. Add `preview.light.png` / `preview.dark.png` (Lab has a "capture preview" button that produces the right size).
8. Keep `"status": "draft"` to ship it to production for review in the Theme Lab (super admin only); change to `"published"` in a later release to make it selectable by users and on the Assignments page.
9. `php artisan gs-theme:validate civic-teal` and `npm run test:themes -- --update-snapshots` for the new theme; open a PR.

No PHP, no routes, no Blade edits required.

---

## 19. Upstream merge checklist

1. `git fetch upstream && git merge upstream/master`
2. `php artisan optimize:clear`
3. `php artisan test --filter=Theming` — contract test first
4. `php artisan gs-theme:build`
5. `npm run test:themes`
6. If the contract test reports a missing variable or class → update **only** `adapter/snipeit-vars.css` / `adapter/snipeit.css`; if a hook anchor is missing → update `LayoutHook` anchors.
7. Re-run 3–5.

---

## 20. Risks and mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Upstream changes `<html>`/`</head>`/`</body>` markup in layouts | Low | Theme not applied (stock look) | Graceful fallback; contract test fails loudly |
| Upstream renames CSS variables or AdminLTE classes | Medium | Parts of theme stop applying | Adapter is the single place to fix; contract + visual tests detect |
| Laravel changes/removes `Blade::precompiler` | Low | Hook stops | Long-standing API; contract test; fallback to a response-injection middleware is a 1-day swap |
| Offices running browsers below the §10.5 baseline (e.g. Windows 7/8.1, capped at Chrome 109) | Medium | Wrong colours — already true for upstream Snipe-IT today | User-agent report before Phase 4; in-app unsupported-browser notice; theming's own CSS needs only cascade layers (older than the baseline) |
| Admins change Branding colours and see no effect under a designed theme | Medium | Confusion | Runtime notice on Branding/profile pages (§7.4); "Snipe-IT Classic" restores Branding colours |
| Custom CSS anchor missing after an upstream change | Low | Theme loads after Custom CSS (Custom CSS loses on equal selectors) | Falls back to `</head>`; contract test asserts theme head appears before the Custom CSS block |
| Dynamic DOM (bootstrap-table, select2 popups) misses theme | Medium | Unstyled fragments | Popups inherit from `<html>` in normal pages; Lab sets `dropdownParent`; covered by visual tests |
| Gov-store migration effort larger than estimated | Medium | Phase 6 slips | Ratchet lets migration proceed incrementally without blocking releases |
| Dark mode left as the generated scaffold | Medium | Poor dark experience | Scaffold marker fails `gs-theme:build` for `published` themes (CI + deploy); Lab matrix makes light/dark review a single page |
| `theming.*` abilities merged after tenant-scope defines its Gates | Low | Abilities missing / always denied | Merge in `register()` (runs before every `boot()`); `ThemeAccessTest` asserts the Gates exist |
| Enforced assignments surprise users | Low | Support requests | Picker shows which scope enforces and why; enforcement changes are logged with actor |
| Theme changes when a user switches working office | Low | Confusion | Intended cue of the active office; documented on the Appearance page; a personal preference overrides it unless enforced |
| Font payload (Bengali) | Low | Slower first load | woff2 + `unicode-range` subsets + preload of the active theme's primary font only |

---

## 21. Decisions

| # | Decision | Resolution | Where in plan |
|---|---|---|---|
| D1 | Organisation default theme | **Yes** — `institutional-green`; superuser can change it on the Assignments page (config value is the fallback) | §12.1–12.4, §12.8 |
| D2 | Users choose their own theme | **Yes** — `allow_user_choice = true`; overridden only by an enforced assignment | §12.2, §12.7 |
| D3 | Company (ministry/tenant) and office defaults | **Yes** — in scope (Phase 3a), with optional enforcement | §12.1–12.3, §12.8, §17 |
| D4 | Who designs dark modes | **Theme developers** design and own both light and dark; scaffold + validation support them | §8.5, §17 Phase 4, §18 |
| D5 | Theme Lab access in production | **Super admin only** (`theming.lab.view`, role-based, no permission path) — to review shipped and `draft` themes; developers use the Lab locally and on staging; `GS_THEME_LAB` is a kill switch | §12.6, §14.4 |
| D8 | Who creates themes | **Developers only, shipped in the code.** No upload, editor or theme creation in the application — not even for the super admin. Every admin only chooses | §1 (G3, G6), §8.6 |
| D9 | Default permissions for choosing (was C2) | **Office admin → their office; company admin → their company** (`theming.assign.office` / `theming.assign.company`, by role and via the `office_operations` / `company_operations` permission profiles); super admin → organisation and any scope | §12.6 |
| D6 | Browser baseline | **Set by upstream Snipe-IT**: Chrome/Edge 123+, Firefox 128+, Safari 18+; unsupported-browser notice; ICT user-agent report before Phase 4 | §10.5 |
| D7 | Recolour core dashboard charts | **"Theme the frame, keep the meaning"**: axes/legends/tooltips/borders always themed; admin-chosen status colours kept; Snipe-IT fallback palette re-mapped to the theme palette; gov-store charts fully themed | §13 |

### Pending

| # | Item | Proposed |
|---|---|---|
| C1 | Browser baseline (D6) — **pending the ICT user-agent report** | Keep upstream's baseline unless a large share of offices is below it. Owner: ICT team; due before Phase 4 |

---

## Appendix A — Inserted head include (sketch)

```blade
{{-- gs-theme::head --}}
@php($gs = app('gs.theme'))
<link rel="stylesheet" href="{{ $gs->asset('kit') }}">
<link rel="stylesheet" href="{{ $gs->asset('packages') }}">
<link rel="stylesheet" href="{{ $gs->themeAsset($gs->key()) }}" data-gs-theme-link>
@foreach ($gs->preloadFonts() as $font)
    <link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
@endforeach
<script nonce="{{ csrf_token() }}">
(function () {
  var html = document.documentElement, mode = @json($gs->mode());
  if (mode === 'system') {
    mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  html.setAttribute('data-theme', mode);
  try { localStorage.setItem('theme', mode); } catch (e) {}
})();
</script>
```

## Appendix B — Glossary

| Term | Meaning |
|---|---|
| Theme | A folder under `theming/themes/` with `theme.json` + light and dark token files |
| Mode | Light or dark rendering of a theme (`data-theme`) |
| Variant | A declared layout option (`data-gs-*`) implemented once in the adapter |
| Adapter | CSS that maps `--gs-*` tokens onto Snipe-IT variables and overrides hard-coded core rules |
| UI kit | `<x-gs::*>` Blade components used by gov-store packages |
| Theme Lab | Page showing every token/component/pattern in every theme × mode; access via `theming.lab.view` |
| Assignment | A theme default set for the organisation, a company (ministry/tenant) or an office; optionally enforced |
| Ratchet | Compliance baseline that may only decrease |
