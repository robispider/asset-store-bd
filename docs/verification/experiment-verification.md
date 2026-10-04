# Experiment data verification

Verified on the local nonproduction application, 4 October 2026 (Asia/Dhaka).

This is historical evidence from the seeding task. Accounts, datasets and package behavior have changed since these checks. For current actor discovery and live/database testing of any feature, use [Local live testing and database guide](../testing/local-live-testing.md) and recheck the database before using old examples.

## Data and item types

Direct read-only database inspection found two ready government datasets plus the preserved original inventory:

| Native records | Database count | Joined category type |
| --- | ---: | --- |
| Assets | 4,833 | asset |
| Consumables | 1,061 | consumable |
| Accessories | 704 | accessory |
| Components | 352 | component |
| Licenses | 96 | license |

All rows joined the expected category type. The 16 existing named accounts supplied by the user remain present. The two ready datasets are `01a10391-e96b-70cb-8301-de5a89cc207f` and `01a104eb-a364-72fd-b7b6-fd8904f1e804`; neither was removed during this inspection.

## Live UI

- Native admin Global Overview reproduces zero assets, consumables, accessories and components, with 480 license seats. This does not reflect database inventory totals.
- The existing `packages/gov-store/tenant-scope/src/Http/Middleware/InitializeTenantContext.php` superuser branch sets active/global and returns without an office. `Scopes/MinistryLocationScope.php` ignores the global flag and filters office-based tables with `1 = 0` when there is no office. Licenses have no `location_id`; `app/Http/Controllers/DashboardController.php` uses the license seat count. These existing package files were not changed.
- Ordinary login as the replacement dataset's Rajshahi storekeeper succeeds. Its native asset list contains 55 assets, including desktop computers, printers, office chairs, office desks, steel cabinets, scanners, UPS units, routers, projectors, air conditioners, fans and whiteboards.
- The same account's consumable screen contains 12 records. A4 paper has quantity 98 after a seeded issue; other supplies have quantity 100.
- Accessories show eight records with quantity 100, including keyboards and mice.
- Components show four records with quantity 101, including memory, SSD, hard drive and power supply. The posted opening quantity of one plus receipt of 100 explains 101.
- The scoped request catalog contains 52 choices, including ICT, furniture, stocked supplies and organization-visible licenses.
- The seeded employee's draft basket displays a desktop and office chair as `Asset_model`, A4 paper as `Consumable`, and keyboard as `Accessory`; all names resolve correctly. Delivery choices are limited to the employee's office.
- The experiment page now explains how to use a downloaded storekeeper account and why Global Overview may show zero stock.

Screenshots: [global dashboard](global-dashboard-current-scope.jpg), [office assets](office-assets.jpg), [office consumables](office-consumables.jpg), [office accessories](office-accessories.jpg), [office components](office-components.jpg), [mixed request basket](office-request-basket.jpg), [ready dataset checks](experiment-data-ready.jpg).

## Populate, wipe and automated checks

The initial quick dataset and an earlier government dataset were populated and wiped through the live super administrator UI. Incorrect confirmation was rejected. The government wipe handled more than 33,000 owned records and dependent UI activity while preserving unrelated records. The replacement government dataset is ready with all ten data checks passing.

Seven automated tests, 55 assertions pass using the uniquely named separate test database. Coverage includes permissions and environment guards, exact quick-profile counts, hashed account authentication, idempotency, office/company/geography boundaries, catalog inventory, ledger balances, dataset wipe, unrelated references and stale-preview blocking, queued actor permission recheck, isolated installation reset with verified encrypted backup, and operation-lock recovery.

Composer validation, syntax checks on feature PHP files, and whitespace checks pass. No existing package bug was repaired. Full installation reset was tested only in the separate test database. This verifies fixture integration and the new data controls; it does not certify every existing package endpoint.

## Readable account update

At the user's request, all new fictional accounts use `1234567890` and a lowercase English full name followed by a number. On 4 October 2026 both existing ready datasets were updated through the authorized feature account command: 480 accounts per dataset, 960 total. Native IDs and company/office/geography/role assignments remain unchanged. The command updates only manifest-owned users, and records an `update_accounts` audit action.

The protected CSV export now returns the new logins and shared password. Previously downloaded CSVs contain obsolete credentials and should be downloaded again. Live ordinary login succeeds for Rajshahi storekeeper `shamimanasrin617` using `1234567890`; the profile shows the readable login, Bengali name and original office. Employee `shamimanasrin761` retains the same office and existing basket relationships. See [successful readable-account login](readable-seed-account-login.jpg).

Eight tests and 71 assertions pass, including new checks for new-population logins, shared hashed passwords, normal guard authentication, collision handling, idempotent account updates and preservation of unrelated/root credentials. The new isolated test database and temporary marker were removed after testing. The dedicated local worker was started with the updated seeding code.
