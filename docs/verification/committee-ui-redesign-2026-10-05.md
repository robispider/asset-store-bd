# Committee UI redesign verification

Recorded 5 October 2026, Asia/Dhaka. This is local evidence for this revision, not a production readiness or accessibility certification.

## Implemented

Implemented the workflows and visual language in [the design index](../../packages/gov-store/committee/design/README.md) and [the UX specification](../../packages/gov-store/committee/UX-REDESIGN.md):

- An office committee desk with attention cards, job coverage, search and recent changes.
- A registry, committee pages with person cards, sentence-based history and a shared paper summary for confirmation and printing.
- A four-step order-first flow with private, office-bound unfinished order/file storage, draft autosave and conflict handling.
- A three-source person drawer, composition checks with reasons, member replacement previews, transfer/vacancy handling and reconstitution differences.
- Extension, suspension/resumption, correction and typed dissolution confirmation flows.
- Responsive personal committee pages, authorized appointment-order downloads and personal expiry reminder dismissal.
- Ministry rule sentences and proposed-policy impact counts, retaining the existing national review flow and immutable committee policy snapshots.
- Bangla and English text, localized Gregorian dates/numbers, local Hind Siliguri and Tiro Bangla fonts, scoped styles and generated assets.

Related server work preserves declared authorization, office/company boundaries, private order delivery and atomic command behavior. Preview changes roll back without recording mutations. Replacement/transfer date cutovers and corrected tenure selection now target the current holder while preserving original evidence.

## Verified

Automated checks used installed PHP 8.4.15 with Xdebug disabled and isolated SQLite in memory:

| Suite | Result |
| --- | --- |
| `tests/Feature/GovStore/CommitteeTest.php` | 41 tests, 1,649 assertions passed |
| `tests/Feature/GovStore/G1AuthorizationTest.php` | 29 tests, 946 assertions passed |

Coverage includes bilingual rendering and compiled Blade syntax, private/context-bound intake revisions and failure retention, atomic order/file rollback, previews without committed ledger/audit/event effects, own appointment-order access, reminder term binding, transfers and vacancies, same-day member transitions, corrected tenure protection, reconstitution, frozen-policy impact and protected route declarations. After the final desk wording adjustment, the Blade/bilingual rendering checks were repeated: 2 tests, 1,036 assertions passed.

JavaScript syntax, relevant PHP syntax and whitespace checks passed. Assets were rebuilt using the repository committee asset builder.

Live verification used the existing authorized session at `http://snipeit.local/`. The resolved database was `snipeit`; the working office was 4 and company 412. The actor's existing storekeeper context and access mode were retained.

- Checked the new-order screen, synthetic PDF upload, automatic retention after navigating away, restoration and explicit discard.
- Checked an existing dissolved committee's person cards, actual end date, order details and readable history. Switching from an empty history filter back to all restored the entries.
- Checked the personal committee empty state at 390 × 844, including navigation and horizontal containment. Reset the viewport afterward.
- No browser console errors were captured during these checks.
- Left the redesigned new-order page open for review.

[Desktop committee page](committee-redesign-desktop.png) · [Phone personal committee page](committee-redesign-phone.png)

## Local database changes and cleanup

Only the two targeted migrations were applied after verifying the effective database name:

- `2026_10_05_000116_create_committee_reminder_dismissals.php`
- `2026_10_05_000117_create_committee_order_intakes.php`

No reset, broad seeding, user permission change, credential change, access-mode change or committee business mutation was performed. The existing eight committee records, including prior soft-deleted drafts, were preserved. Earlier fixtures and their evidence were not created by this task.

The synthetic unfinished order was discarded through the UI. Final inspection found zero unfinished intake rows and zero private copies matching the synthetic PDF. Task inspection/generation helpers and the synthetic source PDF were removed. Audit records and these verification screenshots were retained.

## Open limits

- All nine local committee templates were inactive. The UI now explains who can enable the appropriate ministry rule. Full live creation, activation and populated member-drawer/action workflows could not be exercised with this configuration; isolated tests exercised these flows. No template was silently enabled.
- The current HR membership schema does not record the actual departure date needed for automatic replacement-date prefill (B5). The date remains editable; temporary membership expiry and update timestamps are not treated as departure dates.
- Gregorian dates are localized. The optional Bangla date written on a signed order is retained and displayed; automatic Bangla-calendar conversion is not enabled.
- Local results do not establish CI success, WCAG compliance, production rollout readiness or completion of the real-world shadow observation period.
