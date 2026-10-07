# Dynamic Theming — Implementation Plan

> **Package:** `gov-store/theming` (`packages/gov-store/theming`) · **Namespace:** `GovStore\Theming`
> **Status:** Proposed · **Date:** 2026-10-07 · **Target branch:** `feature/gs-theming` (cut from `master`)
> **Design source:** Claude artifact *"National Asset Register — Theme Directions"* (Theme 1 Institutional Green, Theme 2 Digital Blue, Theme 3 Executive Neutral)

---

## 1. Summary

Add user-selectable, file-based visual themes to the National Asset Register **without editing any Snipe-IT core file**. Each theme ships a light **and** a dark mode, can change layout character (sidebar, density, table style, surfaces, typography) through declared *variants*, and every gov-store package renders fully on theme tokens and the shared UI kit.

The theme is attached to Snipe-IT's layouts **when Blade compiles them** (`Blade::precompiler`), so there is zero per-request cost and zero diff in core files.

### Goals

| # | Goal |
|---|---|
| G1 | Users can pick a theme (and Light / Dark / System mode) besides the default. |
| G2 | Zero edits to Snipe-IT core files; upstream merges stay conflict-free for theming. |
| G3 | Themes are **file-based only**: a theme is a folder in git, reviewed like code. |
| G4 | Every theme **must** provide light and dark modes; validated automatically. |
| G5 | All gov-store packages are fully compliant with themes and layout variants. |
| G6 | A UI/UX developer can create a new theme by adding one folder, mostly as JSON. |
| G7 | Breakage after an upstream merge shows up as failing tests, not user reports. |

### Non-goals

- No in-browser theme editor / Theme Studio, no database-stored themes.
- No new page *structure* on Snipe-IT core screens (e.g. status tabs on core *Assets › List All*). Structural patterns live in gov-store screens only.
- No change to Snipe-IT's own branding settings (`header_color`, link colours, custom CSS); they keep working for the `default` theme.

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
          │ ThemeRepository ─ discovers themes/*/theme.json, resolves `extends`                      │
          │ ThemeResolver   ─ user → office/tenant → org default → `default`                         │
          │ ThemeCompiler   ─ tokens.*.json → CSS (build time), OKLCH derivations, contrast checks   │
          │ AssetRegistry   ─ packages register their CSS/JS; bundled into the build                 │
          │ UI kit          ─ <x-gs::*> Blade components                                             │
          │ gs-theme.js     ─ token reader, mode sync, Chart.js plugin                               │
          │ Appearance page ─ /gov/appearance        Theme Lab ─ /gov/theme-lab                      │
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
├─ database/migrations/
│  ├─ 2026_10_xx_create_gs_theme_preferences_table.php
│  └─ 2026_10_xx_create_gs_theme_assignments_table.php      # office/tenant defaults (Phase 3b)
├─ routes/web.php
├─ lang/en-US/appearance.php
├─ lang/bn-BD/appearance.php
├─ src/
│  ├─ ThemingServiceProvider.php
│  ├─ Compiler/LayoutHook.php
│  ├─ Themes/{Theme.php, ThemeRepository.php, ThemeResolver.php, Manifest.php}
│  ├─ Build/{ThemeCompiler.php, TokenResolver.php, ColorMath.php, ContrastChecker.php}
│  ├─ Assets/AssetRegistry.php
│  ├─ Http/Controllers/{AppearanceController.php, ThemeLabController.php, DevAssetController.php}
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
   ├─ default/                         # stock Snipe-IT look; root of inheritance
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

        // 2. head assets, last </head>
        $source = Str::replaceLast('</head>', "@include('gs-theme::head')\n</head>", $source);

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
| before `</head>` | `gs-theme::head` → `<link>` to built kit CSS, theme CSS, package CSS; `<link rel="preload">` fonts; inline nonce'd mode script (for `system` mode and `localStorage` sync) | Theme styles load **after** core inline styles and win by source order |
| before `</body>` | `gs-theme::foot` → `gs-theme.js` (nonce'd), Chart.js plugin, toggle sync | Runtime helpers |

### 5.3 Operational notes

- **Deploy:** `php artisan view:clear` (already covered by `optimize:clear`) after enabling or upgrading the hook. `php artisan view:cache` runs precompilers too.
- **Graceful fallback:** if an anchor is missing, that insertion is skipped; the page renders with stock Snipe-IT styles.
- **Detection:** the contract test (§16.2) compiles each target layout and asserts the `VERSION` marker and all three insertions are present.
- **Admin Custom CSS** (Settings › Branding) is emitted inside `<head>` before our include. Theme tokens therefore take precedence over Custom CSS for variables the theme defines. Documented; the `default` theme defines none of Snipe-IT's brand variables, so Custom CSS behaves as today under `default`.
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

The `default` theme does **not** map brand variables (`--main-theme-color`, link colours), so Snipe-IT's `header_color` and per-user link colours keep working exactly as today under `default`.

### 7.3 Core surface coverage checklist

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
  "status": "stable",
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
| `status` | `stable` (visible to everyone) · `experimental` (Theme Lab and admins only) · `deprecated` (hidden from picker; users on it fall back to org default) |
| `extends` | another theme key or `null` (only `default`). Max depth 3, no cycles |
| `label`, `description` | `en-US` required, `bn-BD` required for `stable` |
| `fonts` | keys from the font registry (`theming/fonts/fonts.json`) |
| `variants` | each value must be a known option (§9) |

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
| Previews | missing `preview.light.png` / `preview.dark.png` for `stable` themes |

Output: human table plus `--json` for CI.

---

## 9. Layout variants

Implemented once in `adapter/variants.css`; themes only choose values.

| Variant | Values | Effect |
|---|---|---|
| `header` | `brand` · `neutral` · `dark` | Top bar fill: primary colour, surface colour with bottom rule, or near-black identity bar |
| `sidebar` | `dark` · `light` · `brand` | Sidebar ground and text; `light` adds right hairline |
| `nav_icons` | `shown` · `hidden` | Hides first-level sidebar icons (text-only navigation); collapsed-mini mode always keeps icons |
| `density` | `compact` (32px rows) · `regular` (38px) · `comfortable` (44px) | Table rows, control heights, box padding. Touch targets stay ≥ 44px on `pointer: coarse` |
| `table` | `grid` · `rules` · `ledger` | Full gridlines + zebra · row rules only · ledger rules, uppercase small headers, double-rule totals |
| `surfaces` | `card` · `flat` | Boxes with border/shadow · no cards, hairline section dividers and whitespace |
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

Dark modes for all three are **new design work** (the artboards show light only) — see §19 open decisions.

---

## 10. Build and serve

### 10.1 Commands

| Command | Does |
|---|---|
| `php artisan gs-theme:build` | Validate all themes → resolve inheritance & derivations → emit hashed CSS, copy fonts, bundle registered package CSS/JS → write `manifest.json` |
| `php artisan gs-theme:validate [key] [--json]` | Validation only (§8.4) |
| `php artisan gs-theme:make {key} [--extends=default] [--from=key]` | Scaffold a theme folder (`--from` copies seeds and variants of an existing theme) |
| `php artisan gs-theme:compliance [--update-baseline]` | Gov-store compliance report (§15.3) |

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

### 12.1 Resolution chain

```
?gs_preview=<key>&gs_mode=<mode>   (only users with gs-theme.lab ability; never saved)
        ↓ else
user preference                    (only if gs-theme.allow_user_choice = true and theme is stable)
        ↓ else
office / tenant assignment         (Phase 3b — via office-membership / tenant-scope context)
        ↓ else
organisation default               (config gs-theme.default, env GS_THEME_DEFAULT)
        ↓ else
default
```

Unknown or `deprecated` keys fall through to the next level. Resolved once per request in `ThemeResolver` (singleton), memoised.

### 12.2 Data

```
gs_theme_preferences
  id              bigint PK
  user_id         FK users.id, unique, cascade on delete
  theme           varchar(64) null     -- null = follow office/org default
  mode            enum('light','dark','system') default 'system'
  timestamps

gs_theme_assignments                    -- Phase 3b
  id              bigint PK
  scope_type      varchar(32)          -- 'office' | 'tenant'
  scope_id        bigint
  theme           varchar(64)
  timestamps
  unique(scope_type, scope_id)
```

### 12.3 Config (`config/gs-theme.php`, package-owned, merged)

```php
return [
    'default'           => env('GS_THEME_DEFAULT', 'institutional-green'),
    'allow_user_choice' => env('GS_THEME_ALLOW_USER_CHOICE', true),
    'lab_enabled'       => env('GS_THEME_LAB', ! app()->isProduction()),
    'themes_path'       => base_path('packages/gov-store/theming/themes'),
    'extra_theme_paths' => [],      // future: other folders of themes
];
```

### 12.4 Routes (package `routes/web.php`, `web` + `auth` middleware, each with a breadcrumb)

| Method | URI | Name | Purpose |
|---|---|---|---|
| GET | `/gov/appearance` | `gs-theme.appearance` | Theme & mode picker |
| PUT | `/gov/appearance` | `gs-theme.appearance.update` | Save theme + mode |
| POST | `/gov/appearance/mode` | `gs-theme.appearance.mode` | AJAX mode sync from Snipe-IT toggle |
| GET | `/gov/theme-lab` | `gs-theme.lab` | Theme Lab matrix view |
| GET | `/gov/theme-lab/{theme}` | `gs-theme.lab.focus` | Theme Lab focus view |

Validation: `theme` → `nullable|string|in:<stable theme keys>`; `mode` → `in:light,dark,system`.

### 12.5 Appearance page

- Theme cards (radio group): name, description, light + dark preview images, swatch strip, "Default" tag on the org default. Experimental themes shown only to lab users.
- Mode segmented control: Light · Dark · System.
- **Live preview before saving:** selecting a card swaps `data-skin` and loads that theme's CSS link; Cancel restores.
- Entry point: a link in the user menu and gov-store navigation, added through the existing gov-store menu registry (no core view edit).
- Strings in `theming/lang/{en-US,bn-BD}/appearance.php` (package-owned so no core lang edits).

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

| Applies to | What it changes |
|---|---|
| All charts (core + gov-store) | Grid lines, tick labels, legend text, tooltip colours → `--gs-chart-grid`, `--gs-chart-label`, surface tokens; redraw on mode change |
| Gov-store charts, or any chart with `options.plugins.gsTheme.recolorData = true` | Dataset colours from `GS.theme.palette()` |
| Core dashboard data colours | **Unchanged** — they carry meaning (status-label colours chosen by admins) |

Opt-out per chart: `options.plugins.gsTheme = false`.

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

### 14.3 Theme Lab — purpose

One page where a UI/UX developer reviews a theme completely — every token, every core surface, every kit component and representative gov-store patterns — in **light and dark**, and compares themes side by side, without clicking through the app. It is also the primary fixture for visual regression tests (§16.4).

### 14.4 Access

| Setting | Behaviour |
|---|---|
| Ability `gs-theme.lab` | `Gate::define('gs-theme.lab', fn ($u) => $u->isSuperUser())`; can be widened to a gov-store role later |
| `GS_THEME_LAB` | Default `true` in local/staging, `false` in production (route returns 404 when disabled) |
| Data | Uses static fixture data only (no DB reads of real assets), so it is safe in any environment |

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

Order: store-operations (274) → tracking (225) → classification (145) → custom-requests (77) → organization (65) → office-membership (30) → tenant-scope (3) → user-onboarding (1). Within a package, highest-traffic screens first (Store Documents Hub, Goods Receipt workspace, Stock Register Dashboard, Fulfillment Queue).

Print views to convert to `<x-gs::document>`: `store-operations/…/operations/print.blade.php`, `tracking/…/initiatives/report.blade.php`, `tracking/…/tracking_codes/view_task_component.blade.php`.

---

## 16. Testing strategy

### 16.1 PHPUnit (`tests/Feature/GovStore/Theming/`)

| Test | Covers |
|---|---|
| `ThemeRepositoryTest` | discovery, `extends` merge rules, depth/cycle errors, status filtering |
| `TokenResolverTest` | references, derivations (OKLCH), cycles |
| `ThemeValidationTest` | every rule in §8.4 with fixture themes (good + each failure) |
| `ThemeResolverTest` | full resolution chain, `allow_user_choice=false`, deprecated/unknown fallback, `gs_preview` only for lab users |
| `AppearanceControllerTest` | save theme/mode, validation errors, mode AJAX endpoint, guests redirected |
| `ThemeLabAccessTest` | 404 when disabled, 403 for non-superusers, 200 for superusers |
| `GovStoreThemeComplianceTest` | ratchet (§15.3) |
| `BuildCommandTest` | manifest contents, hashing, failure on invalid theme |

### 16.2 Upstream contract test (`SnipeItContractTest`)

- Compiles each target layout through Blade and asserts: `gs-theme-hook:v1` marker present, `<html>` carries `data-skin`, `gs-theme::head` and `gs-theme::foot` included.
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

Estimates are rough person-days for one developer familiar with the codebase; theme dark-mode design is separate design effort.

| # | Phase | Key tasks | Deliverables | Done when | Est. |
|---|---|---|---|---|---|
| 0 | **Spike** | Package skeleton + auto-discovery; `LayoutHook` on `default` & `basic`; one layered adapter rule beating a core `!important`; seed → OKLCH derivation; light+dark tokens for one test theme; nonce'd head mode script | Branch with working proof | Dashboard & login re-themed in both modes, no flash, core diff = 0, `view:cache` works | 2–3 |
| 1 | **Token contract & adapter** | DTCG schema; semantic catalogue (§6.4); `snipeit-vars.css`; `snipeit.css` covering §7.3; `default` theme reproducing stock look | Adapter + `default` theme | Every core screen in `default` matches stock Snipe-IT in light & dark (visual diff) | 5–7 |
| 2 | **Layout variants** | All variants in §9 in `variants.css` | Variant CSS | Each value visibly correct on core shell, tables, boxes | 3–4 |
| 3 | **Engine & tooling** | Repository, inheritance, compiler, `ColorMath`, contrast checker, build/validate/make commands, manifest, dev asset route, resolver, preferences migration, Appearance page, toggle sync, translations | Engine + Appearance page | A new theme ships by adding a folder + `gs-theme:build`; users can pick theme & mode | 5–6 |
| 3b | **Office/tenant defaults** (optional) | `gs_theme_assignments`, admin screen, resolver step | Scoped defaults | An office default applies to its members | 2 |
| 4 | **Three themes** | Institutional Green, Digital Blue, Executive Neutral — seeds, variants, fonts, light **and** dark tokens, previews | 3 theme folders | All pass `gs-theme:validate` in both modes | 4–6 |
| 5 | **UI kit & Theme Lab** | 15 components (§14.2), `status-map.php`, Lab matrix + focus views, sections 1–9 | Kit + Lab | Acceptance criteria §14.7 | 7–10 |
| 6 | **Gov-store migration** | Compliance test + baseline; asset registration in each package; migrate views per §15.4 | Compliant packages | Baseline empty | 10–15 |
| 7 | **Charts & print** | Chart.js plugin; print tokens; convert 3 print views to `<x-gs::document>` | Themed charts & prints | Charts redraw on mode switch; prints always light & legible | 3–4 |
| 8 | **Safety net** | Contract test, rendering tests, Playwright visual suite, upstream-merge checklist in this doc | Tests in CI | All green; checklist adopted | 3–4 |
| | | | | **Total** | **≈ 44–61** |

Dependencies: 0 → 1 → 2 → {3, 5}; 4 needs 1–3; 6 and 7 need 5; 8 runs alongside from Phase 1.

---

## 18. Theme authoring guide (for UI/UX developers)

1. `php artisan gs-theme:make civic-teal --from=institutional-green`
2. Edit `themes/civic-teal/theme.json`: labels (en-US, bn-BD), description, `variants`, `fonts`, `swatches`.
3. Edit `tokens.light.json` and `tokens.dark.json` — start with the four seeds; override semantic tokens only where derivation isn't right. (Or export both files from Figma Tokens Studio.)
4. Open `/gov/theme-lab/civic-teal` (local) — every save is picked up on refresh. Use the variant dropdowns to experiment; paste the shown JSON into `theme.json`.
5. Fix anything the Lab's validation panel flags (contrast, missing tokens) in **both** modes.
6. Add `preview.light.png` / `preview.dark.png` (Lab has a "capture preview" button that produces the right size).
7. Set `"status": "experimental"` to test with admins, then `"stable"` to release.
8. `php artisan gs-theme:validate civic-teal` and `npm run test:themes -- --update-snapshots` for the new theme; open a PR.

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
| Cascade-layer `!important` behaviour in older browsers | Low | Some overrides lose | Upstream already requires `light-dark()` / relative colour syntax (newer than cascade layers); verify in spike against office browser baseline |
| Theme tokens override admin Custom CSS | Medium | Admin CSS appears ignored under non-default themes | Documented; `default` theme leaves brand variables to Snipe-IT |
| Dynamic DOM (bootstrap-table, select2 popups) misses theme | Medium | Unstyled fragments | Popups inherit from `<html>` in normal pages; Lab sets `dropdownParent`; covered by visual tests |
| Gov-store migration effort larger than estimated | Medium | Phase 6 slips | Ratchet lets migration proceed incrementally without blocking releases |
| Dark-mode designs not ready for Phase 4 | Medium | Themes blocked by G4 | Derivation gives a working first dark mode from seeds; designers refine |
| Font payload (Bengali) | Low | Slower first load | woff2 + `unicode-range` subsets + preload of the active theme's primary font only |

---

## 21. Open decisions

| # | Decision | Proposed default |
|---|---|---|
| D1 | Organisation default theme | `institutional-green` (per design recommendation) |
| D2 | Allow users to choose their own theme | Yes |
| D3 | Office/tenant-level defaults (Phase 3b) | Defer until after Phase 4 |
| D4 | Who designs dark modes for the three themes | UI/UX team, starting from derived defaults |
| D5 | Theme Lab access in production | Disabled; superusers only when enabled |
| D6 | Browser baseline for government offices | Same as upstream Snipe-IT (evergreen Chromium/Firefox/Edge) — confirm in spike |
| D7 | Recolour core dashboard data series | No — keep status-label colours; only axes/legends follow the theme |

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
| Theme Lab | Admin page showing every token/component/pattern in every theme × mode |
| Ratchet | Compliance baseline that may only decrease |
