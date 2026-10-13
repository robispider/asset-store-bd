# Organization UX redesign: design artifact snapshot

Source files of the design canvas behind [`../organization-ux-redesign-plan.md`](../organization-ux-redesign-plan.md).

- **Live canvas (claude.ai, private to the owner until shared):** https://claude.ai/artifact/UVEpbBykRxBvB9XzQD6C5A
- **Snapshot taken:** 2026-10-14 (canvas version 16)

| File | Board |
|---|---|
| `artifact/Main.dc.html` | 1 · UX audit of the current package (score, findings, journey, principles) |
| `artifact/Foundations.dc.html` | 2 · Civic Ledger theme and workflow kit catalogue |
| `artifact/Registry.dc.html` | 3 · Offices registry (interactive status tabs and search) |
| `artifact/RegistryMobile.dc.html` | 3b · Registry on a phone, in Bangla |
| `artifact/AddOffice.dc.html` | 4 · Guided "Add office" flow (interactive, 5 steps) |
| `artifact/OfficeHub.dc.html` | 5 · Office hub |
| `artifact/Dialogs.dc.html` | 6 · Pause and relocate dialogs |
| `artifact/Access.dc.html` | 7 · Administrators and access |
| `artifact/StaffStates.dc.html` | 8 · What staff see before go-live (Bangla waiting page, English no-office page) |
| `artifact/canvas.json` | Canvas layout index |

## Notes

- These are **design references, not app code.** They are `.dc.html` Design Component files for the claude.ai
  Design canvas. They are not served by Laravel and are not Blade.
- Boards 2–8 link `/_blob/40008be14305f8813d984e23623f924e`, an upload of the built
  `public/vendor/gs-theme/kit.*.css` plus all six `themes/*.css` (from `php artisan gs-theme:build` on 2026-10-14).
  That URL only resolves inside the canvas. To preview locally, swap it for the files in `public/vendor/gs-theme/`.
- Every board 2–8 has **Theme** (6 themes) and **Mode** (light/dark) tweaks. The markup uses the real `.gs-*` classes
  that `<x-gs::*>` renders, so it shows how the shipped kit looks in each theme.
- Icons are inline SVG placeholders where the app uses Font Awesome (`fas fa-*` from `status-map.php`).
- Sample people, offices and counts are illustrative fixtures, not production data.
