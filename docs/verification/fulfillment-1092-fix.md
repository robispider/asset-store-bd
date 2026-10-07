# Request 1092 fulfillment correction

4 October 2026. Authorized local installation only. No credentials, cookies or tokens are recorded here.

## Cause and correction

Request **1092 / SR-2026-000288** was approved and unstarted, with one asset-model line (model 3) and one consumable line (item 3). Halima had an active membership and storekeeper responsibility in office 7. The available serials were assets 12 and 13.

Native `Asset::checkOut` rejected asset 12 because its model's required **GRN** custom field was empty. The request service returned an unexplained conflict, and the transaction rolled back both the asset and consumable issue. This was a metadata validation failure, not an approval or stock shortage.

Both eligible serials had exact intake registrations linking them to receipt item `019f86a5-8a7b-7141-8f44-3d69d57c22e1`, belonging to posted receipt **GR-2026-000012**, document `019f868b-6db5-724f-8e8a-9b31b7e11054`. The repair checked receipt status, matching office/company, model, tag and serial; required native asset-update permission; and locked request/asset/source rows. Only their empty mapped GRN field was set to that verified receipt number. Native validation and update observers were retained. No required-field rule, role or access setting was weakened. Native update history and a translated `asset_metadata_repaired` request event retain the repair evidence.

Two code changes accompany the repair:

- Failed native checkout now produces a translated validation response identifying the selected asset and invalid non-encrypted custom-field labels. It exposes no field values, physical column names, SQL or traces. Unknown/native failures use a generic asset-review instruction. Transaction rollback remains unchanged.
- Store-operations' native movement audit writer uses `action_logs.created_by`, the installed native actor column, instead of the nonexistent `user_id`. Regression fixtures now match that schema and verify actor attribution for bulk issue history.

The fulfillment workspace's remaining literal substitution label was also replaced with its existing translation key.

## Verification

- Halima signed in through the normal local HTTP login in a separate in-memory cookie session. The actual fulfillment page returned 200 and offered assets 12 and 13. The original browser login was preserved.
- Before repair, a rolled-back diagnostic reproduced native checkout rejection and its missing-GRN validation error. All attempted assignments, quantities and documents were rolled back.
- After repair, the complete issue service passed with one chair and one consumable. The diagnostic used the actual native checkout listener and stock issuer; outbound mail/notifications were suppressed within that diagnostic. Its enclosing transaction was rolled back, and persisted asset assignments, issued quantities and consumable stock were verified unchanged. This proves the local path can execute; it does not claim a physical handover or committed issue.
- `php vendor/bin/phpunit tests/Feature/GovStore`: **55 tests, 830 assertions passed**, using isolated SQLite memory. The new case exercises actual native required-field validation, safe errors, no partial issue/ledger/reservation changes on failure, and successful issuance after supplying valid GRN metadata.
- Compiled package Blade syntax, bilingual translation parity and PHP formatting were checked.

## Retained state and browser limit

Request 1092 remains **approved / unstarted**, with its original approval, quantities and reservations intact. Assets 12 and 13 retain the corrected GRN and remain unassigned. No issue, receipt, test grant or access-mode change was committed. Temporary inspection and diagnostic helpers were removed.

Only the shared in-app browser was available; starting a separate Chrome session returned unavailable. Automatic approval review refused signing out the existing browser account without explicit authorization. The HTTP sessions provided independent Halima verification while preserving that login. Halima can refresh the fulfillment workspace, select either eligible chair, enter consumable quantity 1 and submit after the actual handover.
