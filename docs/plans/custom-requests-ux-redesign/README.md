# Custom Requests UX redesign: design artifact snapshot

Source files of the design canvas behind [`../custom-requests-ux-redesign-plan.md`](../custom-requests-ux-redesign-plan.md).

- **Live canvas (claude.ai, private to the owner until shared):** https://claude.ai/artifact/1W6beCwxWeaRJQHiKqxd6e
- **Snapshot taken:** 2026-10-15

| File | Board |
|---|---|
| `artifact/Main.dc.html` | 1 · UX audit (score, measurements, personas, findings, journey, principles) |
| `artifact/Vocabulary.dc.html` | 2 · Request statuses, glossary (en/bn), sentence keys, structure |
| `artifact/Catalog.dc.html` | 3 · Request items (interactive: live search, type filter, add with undo) |
| `artifact/DraftRequest.dc.html` | 4 · Draft request: split preview, error in place, review, sent (interactive) |
| `artifact/MyRequests.dc.html` | 5 · My requests with tabs and receipt confirmation (interactive) |
| `artifact/MyRequestsMobile.dc.html` | 5b · My requests on a phone, in Bangla |
| `artifact/RequestDetail.dc.html` | 6 · New request page: progress, lines, next step, return dialog (interactive) |
| `artifact/Approvals.dc.html` | 7 · Approvals inbox with tabs (interactive) |
| `artifact/Decide.dc.html` | 8 · Decide: stock, stage, required reasons; `staleError` tweak (interactive) |
| `artifact/ToIssue.dc.html` | 9 · To issue queue with tabs (interactive) |
| `artifact/IssueItems.dc.html` | 10 · Issue items: prefilled, scan list, alternative, close the rest; `stockError` tweak (interactive) |
| `artifact/Register.dc.html` | 11 · Issue register and a record |
| `artifact/Rules.dc.html` | 12 · Request rules with live preview (interactive) |
| `artifact/canvas.json` | Canvas layout index |

## Notes

- **Design references, not app code.** These are `.dc.html` Design Component files for the claude.ai Design canvas.
- Boards link `/_blob/aef52f8d7c3fef253b593dd05928ec1c`, an upload of `public/vendor/gs-theme/kit.4380901a4e.css`
  plus all six `themes/*.css` (built 2026-10-14). The URL only resolves inside the canvas. To preview locally, swap
  in the files from `public/vendor/gs-theme/`.
- Every board has **Theme** (6 themes) and **Mode** (light/dark) tweaks and uses the real `.gs-*` markup.
- Icons are inline SVG placeholders where the app uses Font Awesome.
- People, offices, request numbers and counts are illustrative fixtures, not production data.
