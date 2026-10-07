# Theme fonts

Fonts are self-hosted (no Google Fonts calls — government intranets often block them) and registered
by key in `fonts.json`. Themes refer to fonts only by key (`theme.json` → `fonts`).

The woff2 binaries are **not committed yet**. Until they are added, every theme falls back to the
installed fonts listed in each entry's `stack` (Segoe UI / Nirmala UI on Windows, etc.), and
`gs-theme:build` prints a warning. Nothing else changes when the files arrive: drop them here with the
file names from `fonts.json`, run `php artisan gs-theme:build`, and `@font-face` rules (with
`unicode-range` subsets, so Bengali files load only when Bengali text renders) are emitted automatically.

| Key | Family | Expected file(s) | Licence |
|---|---|---|---|
| `noto-sans` | Noto Sans | `NotoSans-Latin.woff2` (variable, Latin subset) | OFL-1.1 |
| `noto-sans-bengali` | Noto Sans Bengali | `NotoSansBengali.woff2` (variable, Bengali subset) | OFL-1.1 |
| `noto-serif-bengali` | Noto Serif Bengali | `NotoSerifBengali.woff2` | OFL-1.1 |
| `source-serif-4` | Source Serif 4 | `SourceSerif4-Latin.woff2` | OFL-1.1 |
| `inter` | Inter | `Inter-Latin.woff2` | OFL-1.1 |
| `ibm-plex-mono` | IBM Plex Mono | `IBMPlexMono-Latin.woff2` (400) | OFL-1.1 |

Obtain the files from the upstream projects (Google Noto, Adobe Source, rsms/inter, IBM Plex), keep the
OFL licence text alongside them (`OFL.txt`), and subset to the ranges in `fonts.json` (e.g. with
`pyftsubset`). Only the active theme's sans font is preloaded.
