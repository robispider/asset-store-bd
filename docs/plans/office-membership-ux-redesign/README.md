# Office Membership UX redesign: design artifact snapshot

Source files of the design canvas behind [`../office-membership-ux-redesign-plan.md`](../office-membership-ux-redesign-plan.md).

- **Live canvas (claude.ai, private to the owner until shared):** https://claude.ai/artifact/4i72wRFDSiMQw8TcUCkRiD
- **Snapshot taken:** 2026-10-14 (canvas version 11)

| File | Board |
|---|---|
| `artifact/Main.dc.html` | 1 · UX audit (score, findings, transfer journey, principles) |
| `artifact/Vocabulary.dc.html` | 2 · Statuses, glossary (en/bn), sentence keys, structure |
| `artifact/MyOffices.dc.html` | 3 · My offices (interactive: accept/decline handover, cancel join with undo) |
| `artifact/MyOfficesMobile.dc.html` | 3b · My offices on a phone, in Bangla, with the office chip sheet |
| `artifact/JoinOffice.dc.html` | 4 · Join another office (interactive; `expiredCode` tweak shows the error) |
| `artifact/LeaveOffice.dc.html` | 5 · Leave an office: readiness, confirm, transfer tracker (interactive) |
| `artifact/Handover.dc.html` | 6 · Hand over a role: picker dialog, pending slot, stale-change error |
| `artifact/StaffHub.dc.html` | 7 · Office staff with status tabs (interactive) |
| `artifact/StaffDialogs.dc.html` | 8 · Add staff, office code, sign off, welcome |
| `artifact/Override.dc.html` | 9 · Emergency changes and change history (interactive) |
| `artifact/canvas.json` | Canvas layout index |

## Notes

- **Design references, not app code.** `.dc.html` Design Component files for the claude.ai Design canvas.
- Boards link `/_blob/818ad3bee488823df6a430cb763aba33`, an upload of `public/vendor/gs-theme/kit.4380901a4e.css` plus
  all six `themes/*.css` (built 2026-10-14). The URL only resolves inside the canvas; to preview locally, swap in the
  files from `public/vendor/gs-theme/`.
- Every board has **Theme** (6 themes) and **Mode** (light/dark) tweaks and uses the real `.gs-*` markup.
- Icons are inline SVG placeholders where the app uses Font Awesome.
- People, offices, codes and counts are illustrative fixtures, not production data.
