# gov-store Package-wise Gap Analysis

Oct 4, 2026 · @zahid

## Scorecard

The 12 packages have 64 gaps between them: 3 critical, 19 high, 31 medium and 11 low. Store Operations, Classification and tenant-scope carry all three critical gaps. This package-level pass also found defects that a cross-cutting review misses. For example, starter catalogs never run, tracking projections never refresh, and two migrations drop tables owned by other packages. The companion [gov-store Gap Assessment](gov-store-gap-assessment.md) covers cross-cutting themes and the roadmap.

| Package | Critical | High | Medium | Low | Top gap |
| --- | --- | --- | --- | --- | --- |
| store-operations | 1 | 3 | 3 | 1 | Posting and rule publishing have no permission checks |
| classification | 1 | 2 | 2 | 2 | Admin actions have no permission checks |
| tenant-scope | 1 | 2 | 4 | 0 | Operational scope ignores the global flag |
| tracking | 0 | 2 | 4 | 1 | Projection cache never refreshes on new associations |
| custom-requests | 0 | 3 | 2 | 1 | Legacy single-item request path runs beside the basket |
| organization | 0 | 2 | 3 | 1 | No way to suspend, merge or close an office |
| office-membership | 0 | 2 | 3 | 0 | Clearance checks assets only, not accessories, licences or consumables |
| user-onboarding | 0 | 1 | 3 | 0 | System-created users never enter the queue |
| geo-areas | 0 | 1 | 2 | 1 | `dd()` debug dump reachable |
| committee | 0 | 1 | 1 | 0 | Package not built |
| metadata | 0 | 0 | 3 | 1 | Only 3 hard-coded field providers |
| experimentation | 0 | 0 | 1 | 3 | Tests need a hand-built database and are not in CI |

Source: static review of `packages/gov-store` at commit `76934cd8eb`; the application was not run. Each package below gives its current state in two sentences, then its gaps with a severity and a suggested fix.

## tenant-scope

This package builds the tenant context on every web and API request, registers three global scopes and a mutation observer on 11 Snipe-IT models, and owns the central menu. The reference and user scopes respect the global flag and the office hierarchy, but the operational scope (assets, consumables, accessories, components, licences) does not.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | `MinistryLocationScope` lacks the `isGlobal` check that `TenantScope` and `UserScope` have, so the superadmin Global Overview returns no stock rows. | Critical | Return early when `isGlobal`, as the other two scopes do |
| 2 | `MinistryLocationScope` filters on one `locationId` and ignores `allowedLocationIds`. Company admins and ICT officers see users and locations across their jurisdiction, but stock for one office only. | High | Filter with `whereIn` on `allowedLocationIds` |
| 3 | `InitializeTenantContext` imports Organization and Office Membership models, so the base layer depends on packages built on top of it. | High | Define resolver interfaces in tenant-scope and bind them from those packages |
| 4 | Company-admin permissions never reach the effective set: `EffectivePermissionSet` has no `merge()` method, and the `method_exists()` guard skips it silently. | Medium | Add `merge()` and a test |
| 5 | Queued jobs and console commands get no tenant context, so they run unscoped. | Medium | Pass office context into jobs explicitly; document which commands run globally |
| 6 | `Schema::hasColumn` runs on every scoped query, and the middleware runs about 6 uncached queries per request. | Medium | Cache column lists per table and the context per session |
| 7 | The menu is injected by rewriting `</body>` in every HTML response, and permission filtering only hides links. | Medium | Render the menu through a view composer; enforce permissions on routes |

## organization

This package provisions offices with geo verification and duplicate checks, assigns office admins and roles, and manages ICT jurisdictions, the ministry directory and company admins. An office's lifecycle has only two states: provisioned and operational.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | There is no way to suspend, merge, relocate or close an office: `lifecycle_status` only ever holds `provisioned` or `operational`. | High | Add lifecycle states and gate closure with the membership `ClearanceEngine` |
| 2 | The `OfficeProvisioned` event that classification listens for is never defined, so starter catalogs are never created for new offices. | High | Define the event and fire it at the end of `provisionOffice()` |
| 3 | `gov_location_roles` is created by both custom-requests and organization. The organization migration drops and recreates it in `up()`, and both packages ship a `LocationRole` model. | Medium | One owning package and model; additive migrations only |
| 4 | Delegate columns (primary, final and storekeeper delegates) exist, but no service or screen uses them, so approvals stall when an officer is on leave. | Medium | Build delegation with start and end dates and use it in approvals |
| 5 | 20 `bn-BD` keys are missing (282 vs 262), and the office registry and directory lists have no pagination. | Medium | Fill the keys; paginate lists |
| 6 | `InjectOrganizationUi` middleware is never registered, and its `hooks` view folder is empty. | Low | Delete the dead code |

## office-membership

This package handles joining an office by token or invite code, admin approval, switching the working office, peer role handshakes, and release gated by three clearance rules. It also has a superadmin override console with an audit log. The flows are complete, but the clearance rules and the permission checks are thin.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | `RoleAssignmentController` and `RoleHandshakeController` (8 actions) have no controller-level permission checks. This review did not confirm whether the services stop a user acting on someone else's handshake. | High | Add ownership policies and feature tests |
| 2 | `NoActiveAssetsRule` counts only assets checked out to the user. Accessories, licence seats and consumables held by someone leaving are not checked. | High | Add a clearance rule per item type |
| 3 | `NoPendingRequestsRule` imports custom-requests, which creates a two-way dependency between the packages. | Medium | Let custom-requests register its own rule with the `ClearanceEngine` |
| 4 | No notifications for handshake proposals, membership approvals or release requests; the only cue is an injected banner. | Medium | Mail and in-app notifications |
| 5 | Menu titles (“Staff Management”, “Membership Overrides”) are hard-coded in English, and there are no breadcrumbs. | Medium | Move titles to language files; add breadcrumbs |

## user-onboarding

This package watches user creation. Users created by an office admin are onboarded straight away; everyone else goes into a WAITING queue until an admin assigns them to an office. It is the smallest package (312 PHP lines, one screen).

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | The observer exits when no one is logged in, so users created by SCIM, LDAP sync, CSV import or console commands never enter the queue. | High | Queue them with `owner_type = SYSTEM`, a value the schema already has |
| 2 | The schema defines a `CANCELLED` status, but no code sets it, and there is no reject or reassign action. | Medium | Add reject and reassign actions, with a reason |
| 3 | Neither the user nor the admin is notified when an assignment completes. | Medium | Send a notification on completion |
| 4 | The one screen is untranslated and the package has no language file. | Medium | Add `en-US` and `bn-BD` language files |

## geo-areas

This package ships 9,110 areas: 8 divisions, 64 districts, 494 upazilas, 4,555 unions, 12 city corporations, 95 city thanas and 325 paurashavas, plus their wards. It exposes one search endpoint used by select2 dropdowns. The data coverage is good; the gaps are hygiene and maintenance.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | A non-AJAX request with an empty search term hits a `dd()` diagnostic dump in `GeoAreaController`. | High | Remove it and always return JSON |
| 2 | The data has no source or version date and no update path, and the `geo_type` value for divisions is misspelt (`divison`). | Medium | Record source and version; add a refresh command; fix the value with a migration |
| 3 | The search response joins English and Bangla names into one string regardless of locale, and the package has no language file. | Medium | Return both names and let the UI pick by locale |
| 4 | Search uses `LIKE '%term%'`, which cannot use an index. This is fine at 9,110 rows but will not scale to mouza-level data. | Low | Prefix search or a full-text index if the dataset grows |

## custom-requests

This package runs basket-based requests with purpose and justification, policy-driven approval (auto, primary only, or primary then final), fulfillment with substitutions, and a fulfillment register. An older single-item request path is still live beside it.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | There are two request systems. The legacy `ItemRequest` path (`/submit`, `/admin/{id}/approve` and `/reject`, table `custom_item_requests`) runs beside the basket flow, and `/gov-requests/admin` is defined twice. | High | Retire the legacy routes, model and table |
| 2 | `GovRequestController::store` builds a class name from user input (`App\Models\` + `item_type`) with no allow-list. | High | Resolve types only through `RequestableFactory` with a fixed list |
| 3 | Approval is fixed at two levels, with no value thresholds, delegation, escalation timer or notifications. | High | Add threshold rules, delegate lookup and notifications |
| 4 | There is no component adapter, so components cannot be requested even though they are stocked. | Medium | Add a `ComponentAdapter` |
| 5 | Requesters cannot withdraw a submitted request; only approvers can end it. | Medium | Add a withdraw action while the request is pending |
| 6 | `BasketController` and `GovRequestController` (9 actions) have no explicit permission checks. They act on the user's own basket, but ownership is untested. | Low | Feature tests for cross-user access |

## store-operations

This is the largest package (5,415 PHP lines). It provides a document workspace for goods receipts and issues (draft, ready, posted), an immutable ledger with a kardex register, a Product Rules Studio with a simulator and impact preview, and print and attachment support. The ledger core is solid; the document set and controls around it are incomplete.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | 37 actions, including posting documents to the ledger and publishing product rules, have no permission checks; the menu only hides the links. See [G1 analysis](g1-unprotected-actions-analysis.md). | Critical | `can:` middleware per route group: storekeeper for documents, superuser for rules |
| 2 | Only `receipt` and `issue` documents exist. `StockAdjustment` and the transfer capability have no document type, and posted documents cannot be cancelled or reversed (the `CANCELLED` state is never used). | High | Add adjustment and transfer types, plus a reversal document |
| 3 | The Ready state has no effect: there is no committee verification, approval or sign-off before posting. | High | Gate Ready → Posted on committee or approver sign-off |
| 4 | The workspace captures neither the supplier nor the committee reference (only the unrouted legacy controller did). Receipt of serialized assets is unproven end-to-end; the demo data excludes laptop receipt. | High | Add header fields; test the asset-creation path |
| 5 | The legacy `GoodsReceiptController`, `GoodsIssueController` and their `receipts/create` and `issues/create` views are unrouted dead code. | Medium | Delete them |
| 6 | `gov_profiles` and `gov_profile_capabilities` are created by three migrations, and each later one drops the earlier tables in `up()`. | Medium | Squash into one additive migration |
| 7 | About 19 view lines and the menu titles are hard-coded in English, and the line-item grid is not usable on store-room tablets. | Medium | Move text to language files; give the grid a responsive layout |
| 8 | Route name `storeops.admin.rules.assign` is used by two different routes. | Low | Rename one |

## classification

This package holds a reference catalog of about 158,000 nodes and 143,000 definitions (UNSPSC, CGA, HS). Its features are search, an explorer, collections, bulk adoption into Snipe-IT categories, office copy, a governance registry and an organization catalog. All six phases in `plan.md` have screens, but the automation behind them is partly disconnected.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | 30 admin actions (import validation and execution, adoption, bulk adoption, collection building, office copy) have no permission checks. See [G1 analysis](g1-unprotected-actions-analysis.md). | Critical | Route middleware: superuser for import and collections, office admin for adoption |
| 2 | Starter templates never run: the listener waits for `OfficeProvisioned`, which does not exist, and reads a `type` attribute that Snipe-IT locations do not have. | High | Fire the event from organization; store the office type on `LocationProfile` |
| 3 | Bulk adoption runs inside the HTTP request; `plan.md` itself warns that a 150-category collection will time out. | High | Dispatch a queued job and show progress |
| 4 | The synonym dataset is empty (header row only), so synonym search returns nothing. | Medium | Compile and ship synonyms, Bangla terms first |
| 5 | About 30 view lines and 8 menu titles are hard-coded in English, and one Livewire component sits among Blade-only screens. | Medium | Move text to language files; choose one interaction model |
| 6 | The external catalog source is a disabled placeholder button. | Low | Build the feature or remove the button |
| 7 | The migration filename `2026__08_04_create_gov_catalog_collections_table.php` is malformed. | Low | Rename to the standard timestamp format |

## tracking

This package covers funding types, initiatives with a workspace, operation units, and tracking codes with a three-level allocation matrix. It also handles geography and participant scopes, the GRN validation handshake, retrospectives and an initiative report. Authorization is the most thorough of any package (39 checks), but two event paths are broken.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | The projection cache never refreshes on new associations. `TrackingAssociationObserver` dispatches with `tracking_reference_id`, a column associations do not have (theirs is `tracking_code_id`), so the job receives `null`. | High | Resolve the initiative id via the tracking code; add a test |
| 2 | Assets received through a GRN are never linked to programmes. `AssociateAssetsToProgramme` listens for `AssetsReceivedViaGRN`, which nothing dispatches; only the consumable inventory path is wired. | High | Fire the event from the asset-creation step of posting |
| 3 | Document upload is not wired: `TrackingDocumentController` imports a missing `TrackingReference` model and has no routes. | Medium | Rebuild it on `TrackingCode` and route it |
| 4 | `refactor plan2.md` marks Excel template download, upload and round-trip as done, but there is no spreadsheet library in the tracking code or the root `composer.json`. | Medium | Implement with PhpSpreadsheet, or correct the plan |
| 5 | The GRN evaluation “API” runs on the web session, and `TrackingEvaluationController` (4 actions) has no permission checks. | Medium | Scope checks to the caller's office; move to token auth with the REST API |
| 6 | This is the heaviest UI: 4,452 Blade lines, 11 inline JavaScript partials, about 46 hard-coded English lines and 4 raw `{!! !!}` outputs. | Medium | Bundle the JavaScript, move text to language files, use Blade components |
| 7 | The namespace casing is mismatched (`GovStore\tracking` registered, `GovStore\Tracking` declared), and the licence says “proprietary”. | Low | Fix the registration; align the licence |

## metadata

This package maps a logical field schema onto Snipe-IT custom fieldsets through a provider registry, converges asset models in a queued job, and reports drift on a health page. It works, but it is code-driven rather than configurable.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | There are only 3 hard-coded providers (baseline, laptop, police department), so every new domain needs a code change. | Medium | Make providers configurable in the database, or document a provider recipe |
| 2 | There is no screen to view or edit field mappings, only the health page and a converge button. | Medium | Read-only mapping browser first, then an editor |
| 3 | The health page is English-only, and the package has no language file. | Medium | Add `en-US` and `bn-BD` language files |
| 4 | `composer.json` has no provider auto-discovery and declares a “proprietary” licence. | Low | Add `extra.laravel.providers`; align the licence |

## experimentation

This package gives superadmins populate, resume, wipe and installation reset for fictional Bangladesh government data, with encrypted backups and a CLI. It holds the only tests in gov-store (8 tests) and is the best-documented package; its gaps are minor.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | The tests need a separate, hand-prepared database and are not in CI. | Medium | Add a CI job that prepares the test database |
| 2 | The package is missing from the root `composer.json` `require` and has no provider auto-discovery. | Low | Require it as a dev dependency with auto-discovery |
| 3 | Backups can be taken, but there is no restore screen; restore is a manual step into a separate database. | Low | Add a restore command, at least |
| 4 | 7 `bn-BD` keys are missing (21 vs 14). | Low | Fill the keys |

## committee

This package exists only as `plan.md` (358 lines). The plan describes configurable committee types, scoping to offices, initiatives or stores, members with designations and appointment dates, a set of query services, and nine workspace screens. No code, migrations or screens exist yet.

| # | Gap and evidence | Severity | Fix |
| --- | --- | --- | --- |
| 1 | The package is not built, so receiving, inspection and stock-verification committees cannot be recorded. | High | Build it from the plan: models and services first, then the screens |
| 2 | The plan lists store-operations and tracking as consumers but defines no hook for store-operations' Ready → Posted step. | Medium | Add a “committee sign-off required” contract that store-operations calls before posting |
