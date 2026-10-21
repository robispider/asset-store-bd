# Tracking monitor redesign: design artifact snapshot

Design reference for [`../tracking-monitor-redesign-plan.md`](../tracking-monitor-redesign-plan.md). To hand the build to
an agent, give it [`BUILD-PROMPT.md`](BUILD-PROMPT.md).

- **Live artifact (claude.ai, private to the owner until shared):** https://claude.ai/artifact/3Qj47v3E28B8gZqroHDeLU
- **Snapshot taken:** 2026-10-18 (artifact version 3)

| File | What it is |
|---|---|
| `artifact/ictd-lab-monitor.html` | The whole interactive prototype in one file. Open it directly in a browser (needs internet for Google Fonts). |
| `BUILD-PROMPT.md` | Self-contained prompt for the agent that builds the design |
| `IMPLEMENTATION-PLAN.md` | Concrete build sequence, code findings and verification gates, using the owner's resolved decisions |

## Implementation status — 2026-10-19

The owner approved the build and phases 0–5 are implemented on `codex/tracking-monitor-redesign`.
See the [verification report](../../verification/tracking-monitor-redesign-2026-10-19.md) for automated/live checks,
remaining acceptance items and historical data limitations. The prototype remains a design snapshot.

## What is in the prototype

| Where | Screen | Plan section |
|---|---|---|
| Tab **Monitor** | Programme monitor for ICTD Lab: KPIs, needs attention, delivery through the year, codes table, tile map, items and suppliers, activity | §4.1 |
| Click any code | Code dashboard: status panel with next step and forecast, receipts over time, lifecycle, office grid and mini map, prices paid, receipts, documents, contacts | §4.2 |
| Click any office, or tab **Offices** | Office view at all-programme / programme / code level with *Why you can see this* and *Previewing as* (5 viewers); offices directory | §4.3, §4.4, §6 |
| Tab **Route map & audit** | Every tracking route, how it is reached, HTTP result as an officer, defects | §2 |
| Tab **Design notes** | Users, questions answered, metric definitions, scope rules, kit mapping, backend work | §3, §5–§8 |

Deep links work as URL fragments: `#monitor`, `#offices`, `#audit`, `#notes`, `#code-ictd-201`, `#office-9`,
`#office-9~p4` (programme 4 level), `#office-9~c~ictd-20` (code level).

## Notes

- **Owner decisions recorded 2026-10-18:** design plan §11 and `IMPLEMENTATION-PLAN.md` override conflicting prototype
  behaviour. In particular, office access alone must not reveal programmes or their deliveries. The saved prototype
  still demonstrates the older, broader office-only visibility and is retained as historical design evidence.
- **A design reference, not app code.** The prototype is plain HTML with inline CSS and JavaScript. It is not served by
  Laravel and is not Blade. Port its layout to `<x-gs::*>` components and its logic to PHP services.
- It embeds the six built theme files from `public/vendor/gs-theme/themes/*.css` (built 2026-10-17) and uses only `--gs-*`
  tokens, so the theme and light/dark switches show the real palettes. It does not include `kit.css`; component shapes
  are drawn with page CSS that the kit components should replace.
- **Example data** (the default) keeps ICTD Lab's real codes, targets, offices and team, and invents receipts, suppliers,
  values, eight extra Naogaon upazila offices, codes for *Office Network*, and an example programme owned by another
  ministry (*Union Digital Centre support*) to demonstrate cross-programme scope. **Live data** reflects the local
  database on 18 Oct 2026 (one receipt, GRN 4324324).
- The viewers on office pages are personas for the scope rules, not real accounts.
- The *Route map & audit* tab is dated evidence from 18 Oct 2026. Recheck before relying on it.
