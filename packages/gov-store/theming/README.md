# gov-store/theming

File-based, user-selectable themes for the National Asset Register — light **and** dark for every
theme, layout variants, a Blade UI kit and a Theme Lab — attached to Snipe-IT **without editing any
Snipe-IT core file**. Design: [`docs/plans/dynamic-theming-plan.md`](../../../docs/plans/dynamic-theming-plan.md).

## How it attaches

`LayoutHook` is a `Blade::precompiler`: when Blade compiles `layouts/default`, `layouts/basic` or
`layouts/setup` it

1. adds `data-skin`, `data-theme`, `data-gs-mode` and `data-gs-*` variant attributes to `<html>`;
2. includes `gs-theme::head` just before the admin Custom CSS block (else before `</head>`);
3. includes `gs-theme::foot` before `</body>`.

Compiled once, cached in `storage/framework/views`. After enabling or upgrading run
`php artisan view:clear` (part of `optimize:clear`). A missing anchor skips that insertion and the page
renders stock Snipe-IT; `SnipeItContractTest` fails loudly.

Core touch ledger: `composer.json` (one `require` line) and `package.json` (`test:themes`). One gov-store
file outside this package changed: tenant-scope's `access.php` lang files gained the five `theming_*`
ability labels (the Access matrix convention enforced by `G1AuthorizationTest`).

## Operations

```bash
php artisan migrate                      # gs_theme_preferences, gs_theme_assignments
php artisan gs-theme:build               # deploy step (with optimize:clear) → public/vendor/gs-theme
php artisan gs-theme:validate [key] [--json]
php artisan gs-theme:make civic-teal --from=institutional-green
php artisan gs-theme:previews [key] [--force]   # schematic 1200×750 picker previews from tokens
php artisan gs-theme:compliance [--update-baseline]
php artisan gs-theme:prune-references    # after removing a theme in a release
php artisan test tests/Feature/GovStore/Theming
npm run test:themes                      # Playwright visual suite (see scripts/live-tests/theme-visual)
```

In `local`, `staging` and `testing`, assets are compiled on request by `/gs-theme-dev/{file}` (edit JSON →
refresh). Elsewhere `gs-theme::head` reads `public/vendor/gs-theme/manifest.json`; without it the app
logs a warning and falls back to stock styling. Override with `GS_THEME_DEV_ASSETS=true|false`.

| Env | Default | Meaning |
|---|---|---|
| `GS_THEME_DEFAULT` | `institutional-green` | Configured organisation fallback |
| `GS_THEME_ALLOW_USER_CHOICE` | `true` | Users may pick their own theme |
| `GS_THEME_LAB` | `true` | Kill switch for the Theme Lab (access is by ability) |

## Pages and access

| Page | Route | Who |
|---|---|---|
| Appearance | `/gov/appearance` | every user (theme unless enforced; Light / Dark / System always) |
| Theme defaults | `/gov/appearance/assignments` | office admin → working office, company admin → own company, super admin → organisation and any scope |
| Theme Lab | `/gov/theme-lab`, `/gov/theme-lab/{theme}` | super admin (`theming.lab.view`, no permission path) |

Abilities `theming.*` are merged into tenant-scope's `govstore-abilities`; `theming.assign.office` /
`theming.assign.company` are also granted through the `office_operations` / `company_operations`
capability profiles. Resolution: Lab preview → enforced (organisation → company → office) → user →
office → company → organisation → `GS_THEME_DEFAULT` → `default`.

## Authoring a theme (developers only — themes ship in git)

1. `php artisan gs-theme:make civic-teal --from=institutional-green`
2. Edit `themes/civic-teal/theme.json`: labels and descriptions (`en-US` + `bn-BD`), `variants`, `fonts`, `swatches`.
3. Design light mode in `tokens.light.json` — usually just the four `seed` colours; override semantic
   tokens (catalogue in `src/Build/Contract.php`) only where the derivation isn't right.
4. Design dark mode in `tokens.dark.json`. Dark mode starts from the merged light tree (dimensions, fonts,
   references) with every dark file in the chain applied on top. Remove `"gs.scaffold": true` when done.
5. Review in `/gov/theme-lab/civic-teal` (matrix shows light and dark side by side; the toolbar's variant
   overrides print the JSON to paste into `theme.json`).
6. `php artisan gs-theme:previews civic-teal` and `php artisan gs-theme:validate civic-teal` (contrast is
   checked in **both** modes; a published theme with a scaffold dark mode fails the build).
7. Keep `"status": "draft"` to review in production's Lab; switch to `"published"` in a later release.

No PHP, routes or Blade. Derivations (`"$extensions": {"gs.derive": {"l": -0.06}}`, `mix`, `alpha`,
`lightness`, `c_scale`) are resolved to literal colours at build time in OKLCH.

### Cascade

| Layer | File | Notes |
|---|---|---|
| unlayered | `adapter/snipeit-vars.css`, `adapter/variants.css` part A | token → Snipe-IT variable mapping; variant → token values |
| `gs-adapter` | `adapter/snipeit.css`, `adapter/variants.css` part B | every declaration `!important` (layered `!important` beats core's unlayered `!important`) |
| `gs-components` | `components/*.css` | UI kit, `.gs-*` only |
| `gs-packages` | registered package CSS | `GsTheme::assets()->css('storeops', $path)` |
| `gs-theme` | `themes/*/overrides.css` | tokens and `.gs-*` only, no `!important`, no literals |

"Snipe-IT Classic" (`default`) maps none of Snipe-IT's brand variables and is excluded from
`snipeit.css` / variant effects, so Branding colours keep working exactly as stock. Known limit: an admin
Custom CSS rule marked `!important` cannot beat the adapter's layered `!important` rules (non-important
Custom CSS still applies after the theme).

## Gov-store compliance

`GovStoreThemeComplianceTest` ratchets `compliance-baseline.json` (colour literals, `<style>` blocks,
inline colour styles per file). Migrate a view (§15.4 of the plan), then
`php artisan gs-theme:compliance --update-baseline` — the baseline only ever goes down.

## UI kit

`<x-gs::page-header>`, `box`, `table`, `status-badge`, `status-tabs`, `bulk-bar`, `stepper`, `kpi`,
`filter-bar`, `key-value`, `timeline`, `form-row`, `alert`, `empty-state`, `document`. Status semantics
come from `status-map.php`. Charts: `GS.theme.palette(n)`, `GS.theme.token('color-primary')`, and the
global Chart.js v2 plugin `gsTheme` (frame always themed; admin status colours kept; Snipe-IT's fallback
palette re-mapped; `options.plugins.gsTheme = false` opts out, `{recolorData: 'all'}` recolours fully).
