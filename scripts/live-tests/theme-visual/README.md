# Theme visual regression

Screenshots every Theme Lab focus-view section (`data-lab-section`) × 5 themes × 2 modes, plus
dashboard, assets list, asset detail, Store Documents Hub, Goods Receipt workspace and login, under
each theme via the never-saved `gs_preview` override. Chromium runs everything; Firefox and WebKit run
the `@smoke` subset. Local / non-production hosts only.

## One-time: save a super admin session

The suite never handles credentials. Sign in yourself and save the browser state:

```bash
npx playwright codegen --save-storage=scripts/live-tests/theme-visual/.auth/superuser.json http://snipeit.local/login
```

(`.auth/` and screenshot output are git-ignored; baselines in `__screenshots__/` are committed.)

## Run

```bash
GS_THEME_BASE_URL=http://snipeit.local GS_THEME_STORAGE_STATE=scripts/live-tests/theme-visual/.auth/superuser.json npm run test:themes
```

Record or refresh baselines after an intended visual change (e.g. a new theme):

```bash
npm run test:themes -- --update-snapshots
```

Optional page targets: `GS_THEME_ASSET_PATH` (default `/hardware/1`), `GS_THEME_HUB_PATH`
(default `/gov-store/operations/hub`), `GS_THEME_RECEIPT_PATH` (a Goods Receipt workspace URL),
`GS_THEME_KEYS` (comma-separated theme keys). Run on every PR touching `packages/gov-store/theming/**`
and after every upstream merge (plan §19).
