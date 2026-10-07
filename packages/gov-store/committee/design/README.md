# Committee redesign — design files

Static mockups for the human-centred, operation-first committee UI. **Read [`../UX-REDESIGN.md`](../UX-REDESIGN.md) first**: it holds the workflows, wording rules, the screen-to-route mapping and the backend additions these screens need. This folder holds what the screens look like.

## For code agents: how to use this folder

1. Read `../UX-REDESIGN.md` (§2 principles, §4 screens, §5 workflows, §6 wording, §7 backend additions, §8 Blade notes).
2. For each screen you build, open its **PNG** to see it and its **HTML** for exact structure, copy and styling.
3. Take colours, type, spacing and status styles from **`tokens.json`**.
4. Build in Blade with **AdminLTE 2 / Bootstrap 3** classes (see the component map below). The mockup HTML uses inline styles only so it is self-contained; do not copy inline styles into production views.
5. Move every Bangla string into `src/resources/lang/bn-BD/committee.php` with an English equivalent in `en-US`. The Bangla wording in the mockups is the intended copy; names, numbers, dates and memo numbers are **sample data**.
6. Keep the existing routes, `gov.can` abilities and services. These files change only the presentation layer.

## Files

| Folder | Contents |
|---|---|
| `screens/NN-name.html` | Standalone static HTML. Opens in any browser (fonts load from Google Fonts). Links between screens work. |
| `screens/NN-name.png` | Full-page render at the target width (1280 px; phone screen 390 px), Bangla fonts embedded. |
| `tokens.json` | Design tokens: colours, status styles, type, spacing, radii, sizes, accessibility rules. |
| `source/*.dc.html`, `source/canvas.json` | Original design-canvas source (Design Component format with `<x-dc>` / `<helmet>` wrappers). Kept for re-import into the canvas; use `screens/` for implementation. |

## Screen index

| # | Screen | HTML · PNG | Who | Spec | Existing routes it uses |
|---|---|---|---|---|---|
| 01 | Committee desk (home) | [html](screens/01-desk.html) · [png](screens/01-desk.png) | Office admin, registrar | UX §4 1A, §5 J1 | `committee.dashboard`, `api/coverage` + new `api/desk` (B1) |
| 02 | A committee's page | [html](screens/02-committee.html) · [png](screens/02-committee.png) | `committee.view` | 1B | `committee.show`, `api/{c}/roster` |
| 03 | History in sentences, roster on a date | [html](screens/03-history.html) · [png](screens/03-history.png) | `committee.view` | 1C, J13 | `committee.history` + sentence keys (B2) |
| 04 | New office order — step 1 (the order, what it does) | [html](screens/04-order-start.html) · [png](screens/04-order-start.png) | Registrar | 2A, J2 | `command.create`, `command.order` (B4) |
| 05 | New office order — step 3 (members + rules panel) | [html](screens/05-order-members.html) · [png](screens/05-order-members.png) | Registrar | 2B, J3 | `seats`, `appoint`, `changeDraftHolder`, `api/{c}/composition` (B3) |
| 06 | Add a person (another office, by code) | [html](screens/06-add-person.html) · [png](screens/06-add-person.png) | Registrar | 2C, J4 | `api/people`, `api/people/by-code`, `command.external` |
| 07 | New office order — step 4 (check against paper, confirm) | [html](screens/07-order-confirm.html) · [png](screens/07-order-confirm.png) | Registrar | 2D | `command.activate` |
| 08 | Replace one member | [html](screens/08-replace-member.html) · [png](screens/08-replace-member.png) | Registrar | 3A, J5 | `replace`, `release`, `appoint` (B5) |
| 09 | Someone was transferred | [html](screens/09-transfer.html) · [png](screens/09-transfer.png) | Registrar | 3B, J6 | `api/transfers`, `transfers/apply` (B6) |
| 10 | Dissolve, knowing the impact | [html](screens/10-dissolve.html) · [png](screens/10-dissolve.png) | Office admin, registrar | 3C, J10 | `api/{c}/impact`, `dissolve` |
| 11 | My committees (phone) | [html](screens/11-my-committees-phone.html) · [png](screens/11-my-committees-phone.png) | Every member | 4A, J12 | `committee.mine` |
| 12 | Committee rules as sentences | [html](screens/12-ministry-rules.html) · [png](screens/12-ministry-rules.png) | Company admin | 4B, J14 | `admin/types` commands (B3) |

Step 2 of the new-order flow (committee type, name, term, offices served) is specified in UX §5.2 J3 but not drawn; build it with the same stepper and card patterns as screens 04 and 05.

## Component map (mockup → AdminLTE 2 / Bootstrap 3)

| Mockup pattern | Where | Build with |
|---|---|---|
| Three tabs under the header | all | `nav nav-tabs` (or AdminLTE `nav-tabs-custom`) |
| Start buttons (big cards) | 01 | `btn btn-primary btn-lg btn-block` inside `col-md-4`, or `small-box` |
| Attention card with one action | 01 | `box` with a left status label; `callout` styles are acceptable if status keeps icon + text |
| Coverage table | 01 | `table table-hover` in `table-responsive` |
| Status pill | 01, 02, 05 | `label` with text and icon; colours from `tokens.json` `color.status` |
| People cards | 02, 05 | `box box-solid` in `col-md-4`; avatar = initials circle |
| Stepper (১ আদেশ … ৪ যাচাই) | 04, 05, 07 | ordered list with a top border per step; `aria-current="step"` on the active step |
| Choice cards (what the order does) | 04 | radio inputs inside labelled `panel`s; whole card clickable |
| Rules panel with reasons | 05 | `box` with a `list-unstyled` checklist; each failure links to its fix |
| Add-person drawer with three tabs | 06 | right-aligned `modal` (`modal-lg`) + `nav-pills` |
| Paper-style summary | 07 | plain `div` with serif font and table; print stylesheet can reuse it |
| Reason chips | 08 | `btn-group` with `data-toggle="buttons"` radio labels |
| Before/after preview | 08, 09 | `box` with a two-column `dl-horizontal` |
| Sticky summary bar with one save | 09 | fixed-bottom bar inside the content wrapper |
| Dangerous confirmation | 10 | `modal` with typed confirmation; button disabled with a visible reason |
| Rule sentences with inline inputs | 12 | `form-inline` per sentence; labels kept for screen readers |

## Not shown in the mockups (implement from the spec)

- Error, loading and empty states beyond those drawn. Follow G1: safe failure with a reference ID; denials explain who can help.
- The English locale. Same layout, with strings from `en-US`.
- Step 2 of the new-order flow, *let it end* dismissal (B8), suspend/resume, correction (J11), reconstitution diff (J8): flows are in UX §5.2.
- Interactivity. The HTML is static; buttons and links show intent only.

## Regenerating

`screens/*.html` were produced from `source/*.dc.html` by moving the `<helmet>` contents into `<head>`, unwrapping `<x-dc>`, dropping the canvas runtime script, and rewriting links between artboards to the numbered file names. PNGs were rendered with headless Chromium at each artboard's width with Hind Siliguri and Tiro Bangla embedded locally.
