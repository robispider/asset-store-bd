# BUSINESS LOGIC — Asset Store BD

> **Read this if:** you are new here, you are not a programmer, or you are a programmer who needs to know *why* code exists before touching it.
>
> Every section answers four questions:
> 1. **What is this for?** (the real-world problem)
> 2. **What does it do?** (the behaviour, step by step)
> 3. **What does it touch?** (tables, other modules)
> 4. **Function by function** (each method, in plain words)
>
> Companion documents: [ERD.md](ERD.md) = database diagrams. [workflow.md](workflow.md) = shorter architecture tour. **This file is the deep one.**

---

## TABLE OF CONTENTS

**[PART 0 — Understand the shape of the system](#part-0--understand-the-shape-of-the-system)**

**[PART 1 — THE MAIN PROJECT (Snipe-IT)](#part-1--the-main-project-snipe-it)**
- [1.1 What Snipe-IT is, in one page](#11-what-snipe-it-is-in-one-page)
- [1.2 How a click becomes a database change](#12-how-a-click-becomes-a-database-change)
- [1.3 The inventory modules](#13-the-inventory-modules)
  - [Assets](#module-assets) · [Asset Models](#module-asset-models) · [Categories](#module-categories) · [Manufacturers](#module-manufacturers) · [Suppliers](#module-suppliers) · [Depreciations](#module-depreciations) · [Status Labels](#module-status-labels) · [Custom Fields](#module-custom-fields--fieldsets)
  - [Accessories](#module-accessories) · [Consumables](#module-consumables) · [Components](#module-components) · [Licenses](#module-licenses--license-seats) · [Predefined Kits](#module-predefined-kits)
- [1.4 The people & place modules](#14-the-people--place-modules)
  - [Users](#module-users) · [Groups](#module-groups--permissions) · [Companies](#module-companies--fmcs) · [Locations](#module-locations) · [Departments](#module-departments)
- [1.5 The process modules](#15-the-process-modules)
  - [Checkout / Checkin](#module-checkout--checkin-the-heart) · [Acceptances](#module-checkout-acceptances-eula--signature) · [Checkout Requests](#module-checkout-requests) · [Maintenances](#module-maintenances) · [Activity Log](#module-activity-log-actionlog) · [Notifications](#module-notifications) · [Reports](#module-reports--report-templates) · [Importer](#module-csv-importer) · [Settings](#module-settings) · [Labels & QR](#module-labels--qr-codes)
- [1.6 The cross-cutting machinery](#16-the-cross-cutting-machinery)

**[PART 2 — THE CUSTOM PACKAGES (`packages/gov-store/*`)](#part-2--the-custom-packages-packagesgov-store)**
- [2.0 Why packages exist and how they attach](#20-why-packages-exist-and-how-they-attach)
- [2.1 `geo-areas` — the map of Bangladesh](#21-geo-areas--the-map-of-bangladesh)
- [2.2 `organization` — turning a building into a Government Office](#22-organization--turning-a-building-into-a-government-office)
- [2.3 `office-membership` — staff belong to offices, and can move](#23-office-membership--staff-belong-to-offices-and-can-move)
- [2.4 `user-onboarding` — new employees who have no office yet](#24-user-onboarding--new-employees-who-have-no-office-yet)
- [2.5 `tenant-scope` — the invisible wall between offices](#25-tenant-scope--the-invisible-wall-between-offices)
- [2.6 `classification` — the national product catalogue (UNSPSC)](#26-classification--the-national-product-catalogue-unspsc)
- [2.7 `custom-requests` — the internal shop (request → approve → issue)](#27-custom-requests--the-internal-shop-request--approve--issue)
- [2.8 `store-operations` — the government store register (receipts, issues, kardex, rules)](#28-store-operations--the-government-store-register)

**[PART 3 — END-TO-END STORIES](#part-3--end-to-end-stories)**

**[PART 4 — REFERENCE](#part-4--reference)**
- [4.1 Every table, what it holds, who owns it](#41-every-table-what-it-holds-who-owns-it)
- [4.2 Roles and what each one may do](#42-roles-and-what-each-one-may-do)
- [4.3 "I want to change X — where do I go?"](#43-i-want-to-change-x--where-do-i-go)
- [4.4 Glossary](#44-glossary)

---
---

# PART 0 — Understand the shape of the system

## The one thing to understand first

This repository is **two software projects living in one folder**.

| | **The main project** | **The custom packages** |
|---|---|---|
| **Name** | Snipe-IT | Gov-Store |
| **Where** | everything *except* `packages/` | `packages/gov-store/` |
| **Who wrote it** | an open-source community | this team |
| **What it does** | tracks equipment and who holds it | turns that into a multi-ministry government store system |
| **Do we edit it?** | **No.** Treat it like a purchased product. | **Yes.** All new work happens here. |

The rule everyone follows: **never edit the main project — extend it from inside `packages/`.** The custom code "reaches into" Snipe-IT using standard Laravel features (service providers, middleware, global query filters, runtime relationships). Nothing in `app/`, `resources/views/`, or `routes/` is modified. This is called the **Zero-Touch** approach, and it means Snipe-IT can be upgraded without losing our work.

## The two projects in plain words

**Snipe-IT answers:** *"We own 400 laptops. Who has laptop #217? When did they get it? What did it cost? When does the warranty end?"*

**Gov-Store answers:** *"The Ministry of Health has 3,000 offices in 64 districts. Office A must never see Office B's stock. An employee in Office A wants a laptop — who approves it, who hands it over, and how is that recorded on an official government stock register? And when that employee transfers to another district, how do we make sure they return everything first?"*

## The eight custom packages, and what each is for

| Package | Plain-English job | Depends on |
|---|---|---|
| **geo-areas** | The map. Division → District → Upazila → Union, with Bangla and English names. | nothing |
| **organization** | Turns a plain "Location" into a real Government Office: pinned to a place, owned by a Ministry, with an admin and a lifecycle. Also defines ICT officer territories. | geo-areas |
| **office-membership** | Which staff belong to which office, who holds which duty (storekeeper / approver), and the rules for leaving and joining an office. | organization |
| **user-onboarding** | Catches newly-created user accounts that have no office yet, and puts them in a queue to be assigned. | office-membership, organization |
| **tenant-scope** | The invisible wall. Decides, on every single page load, exactly which slice of the database the logged-in person may see and change. | all of the above |
| **classification** | The national product dictionary (UNSPSC standard) and the rules about which office may use which product category. | tenant-scope |
| **custom-requests** | The internal shop: browse a catalogue, fill a basket, submit, get approved, get the item handed to you. | store-operations, office-membership |
| **store-operations** | The official store register: goods receipts, goods issues, an immutable stock ledger (kardex), and a configurable rules engine that decides what data must be captured for each product. | tenant-scope |

**Isolation rule:** each package owns its own tables, its own routes, its own screens. They talk to each other only through named services and interfaces, never by reaching into each other's tables directly.

---
---

# PART 1 — THE MAIN PROJECT (Snipe-IT)

## 1.1 What Snipe-IT is, in one page

Snipe-IT is an **IT Asset Management** system. It keeps a register of things the organisation owns, and a record of who currently holds each thing.

### The six kinds of "thing"

| Thing | Real-world example | Counted how? | Returned? |
|---|---|---|---|
| **Asset** | A specific laptop, serial `ABC123` | Individually. Each one is a separate row. | Yes |
| **Accessory** | Keyboards, mice | By quantity ("we have 40") | Yes |
| **Consumable** | Toner, paper, pens | By quantity | **No** — used up |
| **Component** | A RAM stick installed inside a laptop | By quantity, attached to an Asset | Yes |
| **License** | Microsoft Office, 50 seats | By seats; each **seat** is the unit handed out | Yes |
| **Kit** | "New Employee Pack" = 1 laptop + 1 bag + 1 licence | A bundle of the above | — |

### The two verbs

Everything in Snipe-IT revolves around two actions:

- **Checkout** — give a thing to someone (or somewhere). The thing now has a holder.
- **Checkin** — take it back. The thing returns to stock.

### The supporting reference data

These do not get checked out. They describe and organise the things above.

**Category** (what kind of thing) · **Manufacturer** (who made it) · **Asset Model** (the exact product, e.g. "Dell Latitude 5420") · **Supplier** (who sold it) · **Depreciation** (how fast it loses value) · **Status Label** (Ready to Deploy / Pending / Broken / Archived) · **Custom Field** (extra data you invented, e.g. "MAC Address") · **Company** · **Location** · **Department** · **User**.

## 1.2 How a click becomes a database change

Every single page view and every API call travels the same path. Learn this once and the entire codebase makes sense.

```
Browser click
   │
   ▼
Route            routes/web.php  (screens)   ·   routes/api.php  (data for tables & dropdowns)
   │
   ▼
Middleware       "Are you logged in?" … and (see Part 2) the Gov-Store filters that decide
   │             which office you are working in and what you may see
   ▼
Controller       app/Http/Controllers/…      ·   app/Http/Controllers/Api/…
   │             Thin. Collects input, calls the real logic, chooses a response.
   ▼
Form Request     app/Http/Requests/…         Validates the input before anything happens.
   │             Custom validation rules live in app/Rules/.
   ▼
Action / Service app/Actions/…, app/Services/…   One job each. This is where decisions live.
   │
   ▼
Model            app/Models/…                Reads and writes the database (Eloquent ORM).
   │             Observers (app/Observers/) fire automatically on create/update/delete.
   ▼
Policy           app/Policies/…              "Is this user allowed to do this?"
   │
   ▼
Response         a Blade screen               OR   JSON shaped by a Transformer
                 resources/views/…                 app/Http/Transformers/…
```

**Two rules you will see everywhere:**

1. **Screens return Blade views. The API returns Transformer output.** An API controller must never hand back a raw database row — it always passes it through a Transformer first, so the JSON shape is stable and no secret column leaks out.
   ```php
   return (new AssetsTransformer)->transformAssets($assets, $assets->count());
   ```

2. **Every table on screen and every searchable dropdown is powered by the API tree.** The screen is just a shell; the rows arrive by AJAX from `app/Http/Controllers/Api/`.

**A global convenience:** the variable `$snipeSettings` is automatically available inside *every* Blade view (injected by a service provider). It holds the system settings, so no controller has to pass them.

## 1.3 The inventory modules

### Module: Assets

**Files:** `app/Models/Asset.php`, `app/Http/Controllers/Assets/`, `app/Http/Controllers/Api/AssetsController.php`, `app/Observers/AssetObserver.php`, `app/Policies/AssetPolicy.php`
**Table:** `assets`

**What it is for.** An Asset is one specific, individually identifiable physical object. If you can put a sticker with a unique number on it and track *that exact one*, it is an Asset. Laptops, projectors, vehicles, furniture.

**What it does.**
- Every asset carries a unique **asset tag** (the sticker number) and usually a **serial number**.
- It points at an **Asset Model** (which in turn gives it a Category and a Manufacturer).
- It has a **status** (Ready to Deploy, Pending, Archived, Broken…).
- It has *two* locations: `rtd_location_id` = "the shelf it lives on when nobody has it" (RTD = Return To Default), and `location_id` = "where it physically is right now".
- It can be **checked out to three different kinds of holder** — a User, another Asset (e.g. a docking station attached to a laptop), or a Location (e.g. a projector that belongs to Room 5). This is why the database stores both `assigned_to` (the ID) and `assigned_type` (which kind of thing that ID refers to). Programmers call this a *polymorphic* relationship.
- Counters on the row track how many times it has been checked out, checked in, and requested.
- `byod` marks "bring your own device" — the employee owns it, not the organisation.
- Audit dates (`last_audit_date`, `next_audit_date`) support physical stock-taking.

**Key functions on the Asset model:**

| Function | What it does, in plain words |
|---|---|
| `availableForCheckout()` | Answers "can this be given out right now?" — it must not already be assigned, must not be deleted or archived, and its status must be a *deployable* one. |
| `checkOut($target, $admin, $checkout_at, $expected_checkin, $note, $name, $location, $signInPlace)` | The single most important function in the main project. Step by step: (1) refuse if there is no target, and refuse to check an asset out to itself; (2) record the expected return date if one was given; (3) stamp `last_checkout`; (4) attach the holder via `assignedTo()->associate($target)`; (5) work out the new physical location — explicitly given, or inherited from the holder, or the holder itself if the holder *is* a Location; (6) save; (7) fire the `CheckoutableCheckedOut` event, which is what triggers e-mails, the activity log entry, and the acceptance/EULA record; (8) bump the checkout counter. Returns `true`/`false`. |
| `checkIn()` (via the shared checkin controllers) | The reverse: clears the holder, returns the asset to its `rtd_location`, records the date, fires `CheckoutableCheckedIn`, logs and notifies. |
| `checkInvalidNextAuditDate()` | Guards against an audit date that makes no sense (e.g. in the past). |
| `assignedTo()` | The polymorphic link to whoever holds it. |

**Relations.** Belongs to an AssetModel, a Status Label, a Supplier, a Company, and two Locations. Has many Maintenances, Action Log entries, and attached Components. May have a Checkout Acceptance awaiting signature.

**How Gov-Store changes this.** Assets are filtered by `MinistryLocationScope` (you only see assets at your office), guarded on write by `TenantMutationObserver`, and can be *created automatically* by the `store-operations` goods-receipt pipeline. See [2.5](#25-tenant-scope--the-invisible-wall-between-offices) and [2.8](#28-store-operations--the-government-store-register).

---

### Module: Asset Models

**Files:** `app/Models/AssetModel.php`, `app/Http/Controllers/AssetModelsController.php`, `app/Observers/AssetModelObserver.php`
**Table:** `models`

**What it is for.** A *template*. "Dell Latitude 5420" is one Asset Model; you may own 200 physical assets that are all that same model.

**What it does.** Holds the model number, the picture, the default depreciation schedule, whether a serial number is required, and — importantly — which **Custom Fieldset** applies. That fieldset decides which extra fields appear on the asset form (a laptop model might require "MAC address"; a chair model would not).

**Why it matters to Gov-Store.** The internal shop's catalogue lists **Asset Models, not individual assets** — an employee requests "a Dell Latitude 5420", and the storekeeper later picks *which physical unit* to hand over. See [2.7](#27-custom-requests--the-internal-shop-request--approve--issue).

---

### Module: Categories

**Files:** `app/Models/Category.php`, `app/Http/Controllers/CategoriesController.php`, `app/Actions/Categories/DestroyCategoryAction.php`
**Table:** `categories`

**What it is for.** The top-level grouping: "Laptops", "Printers", "Toner", "Office Software".

**What it does — and this is the part people miss.** A Category is not just a label. It carries **behaviour**:
- `category_type` — is this category for assets, accessories, consumables, components, or licences? A category belongs to exactly one world.
- `require_acceptance` — if true, the person receiving the item must formally accept it (see [Acceptances](#module-checkout-acceptances-eula--signature)).
- `eula_text` / `use_default_eula` — the terms they must agree to.
- `checkin_email` — should we e-mail the person when the item is returned?

`DestroyCategoryAction` exists because deleting a category is not a simple delete: it must first check nothing still uses it.

**Gov-Store hooks into Categories heavily.** Which categories an office may use is controlled by the `classification` package; the rules for what must be captured when receiving a category's items are controlled by `store-operations`; and the approval policy for requesting items in a category is controlled by `custom-requests`.

---

### Module: Manufacturers

**File:** `app/Models/Manufacturer.php` · **Table:** `manufacturers`
Who made the thing (Dell, HP, Canon). Holds support phone/URL/e-mail. Asset Models belong to a Manufacturer. `DestroyManufacturerAction` handles safe deletion.

---

### Module: Suppliers

**File:** `app/Models/Supplier.php` · **Table:** `suppliers`
Who *sold* it to us — the vendor. Attached to Assets, Licenses, Accessories, Consumables, Components and Maintenances. Carries address and contact details. In Gov-Store, suppliers are one of the reference types that can be locked to a specific office (decentralised procurement).

---

### Module: Depreciations

**File:** `app/Models/Depreciation.php`, `app/Models/Depreciable.php` · **Table:** `depreciations`
A named schedule ("3-year straight line" = 36 months). Attached to Asset Models and Licenses. The `Depreciable` trait does the arithmetic: given purchase cost, purchase date and the schedule, what is this worth today? Feeds the depreciation report.

---

### Module: Status Labels

**File:** `app/Models/Statuslabel.php` · **Table:** `status_labels`
Named states with **meaning attached** via three flags:
- `deployable` — items in this status may be checked out (e.g. "Ready to Deploy").
- `pending` — arrived but not yet ready (e.g. "Awaiting Imaging").
- `archived` — retired; hidden from normal lists (e.g. "Disposed").
- Neither of the three = **undeployable** (e.g. "Broken").

`Helper::deployableStatusLabelList()` is what fills the status dropdown on checkout forms — it returns only the statuses that permit a checkout.

---

### Module: Custom Fields & Fieldsets

**Files:** `app/Models/CustomField.php`, `app/Models/CustomFieldset.php` · **Tables:** `custom_fields`, `custom_fieldsets`, `custom_field_custom_fieldset`, `models_custom_fields`

**What it is for.** Letting an organisation add their own data fields without a programmer.

**How it works.** You define a **Custom Field** ("MAC Address"). Behind the scenes Snipe-IT physically adds a real column to the `assets` table named `_snipeit_mac_address_1` (stored in the field's `db_column`). You then group fields into a **Fieldset**, and attach the fieldset to an **Asset Model**. Now every asset of that model shows those extra fields.

Fields can be marked `field_encrypted` (stored encrypted, e.g. for passwords), `is_unique`, `show_in_email`, and `display_in_user_view`.

---

### Module: Accessories

**Files:** `app/Models/Accessory.php`, `app/Http/Controllers/Accessories/` · **Tables:** `accessories`, `accessories_checkout`

Bulk items that are handed out and expected back, but are not individually serialised. You hold "40 keyboards", not "keyboard #17".

- `qty` is the total owned.
- Each handout writes a row in `accessories_checkout` (who got it, when, note). The number of rows = how many are out.
- `numRemaining()` = `qty` minus the number of open checkout rows. This is what "available" means.
- `min_amt` is the low-stock warning threshold.
- Checkout is polymorphic — an accessory can go to a User, an Asset, or a Location.

---

### Module: Consumables

**Files:** `app/Models/Consumable.php`, `app/Http/Controllers/Consumables/` · **Tables:** `consumables`, `consumables_users`

Same shape as Accessories, with one crucial difference: **consumables are never returned.** Once issued, the quantity is gone (toner, paper, batteries). Each issue writes a row in `consumables_users`. `numRemaining()` works the same way, but there is no checkin.

---

### Module: Components

**Files:** `app/Models/Component.php`, `app/Http/Controllers/Components/` · **Tables:** `components`, `components_assets`

Parts that get installed *into* an asset — RAM, SSDs, spare batteries. Unlike accessories, a component is checked out **to an Asset, not to a person**. The pivot `components_assets` records `assigned_qty` so you can put 2 of 8 RAM sticks into one machine.

---

### Module: Licenses & License Seats

**Files:** `app/Models/License.php`, `app/Models/LicenseSeat.php`, `app/Http/Controllers/Licenses/` · **Tables:** `licenses`, `license_seats`

**What it is for.** Software entitlements.

**How it works — the key idea.** You do not check out a *License*; you check out a **Seat**. Buying a 50-seat licence creates 50 rows in `license_seats`. Each seat can then be assigned to a User *or* to an Asset (some software is licensed per-machine, not per-person).

Useful fields: `expiration_date`, `termination_date`, `maintained` (are we paying support?), `reassignable` (may a seat be moved to someone else, or is it locked to its first holder?), `serial` (the product key — permission-gated behind `licenses.keys`).

---

### Module: Predefined Kits

**Files:** `app/Models/PredefinedKit.php`, `app/Http/Controllers/Kits/`, `app/Services/PredefinedKitCheckoutService.php` · **Tables:** `kits`, `kits_models`, `kits_licenses`, `kits_accessories`, `kits_consumables`

**What it is for.** "Every new employee gets: 1 laptop, 1 docking station, 1 headset, 1 Office licence." Instead of four separate checkouts, you define the bundle once and check out the whole kit in a single action.

`PredefinedKitCheckoutService` walks the kit contents, finds available units of each item, and performs each individual checkout, collecting any failures (e.g. "no laptops in stock") into one report.

## 1.4 The people & place modules

### Module: Users

**Files:** `app/Models/User.php`, `app/Http/Controllers/Users/`, `app/Observers/UserObserver.php` · **Table:** `users`

**What it is for.** Two jobs at once, and this dual nature causes most of the confusion in the codebase:
1. A **login** — someone who signs in and does work.
2. An **asset holder** — someone equipment is assigned to, who may never log in at all.

**What it holds.** Name, username, e-mail, employee number, job title, manager (`manager_id`, pointing at another user), department, location, company, `activated` flag, and a **permissions** JSON blob.

**Ways a user can be created:** manually, by CSV import, by LDAP/Active Directory sync (`LDAPImportController`), by SCIM provisioning (an automated feed from an HR system), or by SAML single-sign-on on first login.

**Security extras:** `two_factor_secret` (Google Authenticator), `remote` / `vip` flags, `start_date` / `end_date`.

**Gov-Store attaches a lot here.** Runtime relationship `$user->memberships` (which offices they belong to), an observer that creates an office membership when a user is created, another observer that queues them for onboarding if they have no office, and a request-time permission rewrite driven by the duty they hold at their current office. See [2.3](#23-office-membership--staff-belong-to-offices-and-can-move) and [2.5](#25-tenant-scope--the-invisible-wall-between-offices).

---

### Module: Groups & Permissions

**Files:** `app/Models/Group.php`, `app/Http/Controllers/GroupsController.php`, `app/Actions/Permissions/` · **Tables:** `permission_groups`, `users_groups`

**How permissions work.** Permissions are fine-grained strings like `assets.view`, `assets.checkout`, `licenses.keys`, `users.edit`. They live in two places and are combined:
1. On the **user row** (`users.permissions` JSON).
2. On any **groups** the user belongs to.

`admin` and `superuser` are special: `admin` short-circuits most policy checks (see `SnipePermissionsPolicy::before()`), and `superuser` bypasses everything.

Two Action classes protect this area:
- `NormalizePermissionsPayloadAction` — cleans up the submitted permission form into a consistent shape.
- `PreserveUnauthorizedPrivilegedPermissionsAction` — stops a lesser admin from accidentally stripping (or granting) permissions they themselves do not hold. Without this, editing a superuser's profile as a normal admin could silently demote them.

---

### Module: Companies & FMCS

**Files:** `app/Models/Company.php`, `app/Models/Traits/CompanyableTrait.php`, `app/Models/CompanyableScope.php` · **Tables:** `companies`, `company_user`

**What it is for.** Snipe-IT's own built-in multi-tenancy, called **FMCS — Full Multiple Company Support**. When switched on in Settings, records belong to a Company and users only see their own company's records.

**How it works.**
- Any model that uses the `CompanyableTrait` automatically gets `CompanyableScope` applied, which silently adds "…and company_id = mine" to every query.
- Companies can have a `parent_id`, so a Ministry can have child Departments, and `Company::reachableCompanyIds()` expands one level in either direction.
- `company_user` is a pivot letting one person legitimately belong to several companies.

**`CompanyableTrait::canCheckoutTo($target)` — the FMCS gate on handing something over.** It answers "may this item be given to that person/place?" and returns true when *any* of these hold:
- FMCS is switched off entirely;
- the item has no company at all (a "floater");
- the target is a User whose company list includes the item's company (or its parent);
- the target is a Location and location-scoping is switched off;
- the target has no company and the "null company is a floater" setting is on;
- the target's company matches exactly;
- the target is a Location whose company is a parent or direct child of the item's company.

Otherwise: refused.

**Important:** Gov-Store's `tenant-scope` is a *second, stricter* wall layered on top of FMCS. They are not the same thing and both can be active.

---

### Module: Locations

**Files:** `app/Models/Location.php`, `app/Http/Controllers/LocationsController.php`, `app/Observers/LocationObserver.php` · **Table:** `locations`

A physical place. Can nest (`parent_id`), belongs to a Company, has a manager, and an address. In plain Snipe-IT a Location is little more than an address book entry.

**In Gov-Store a Location becomes the single most important object in the system**: it *is* the Office. Everything — stock, staff, approvals, documents — is bounded by a Location. The `organization` package bolts a profile and a role matrix onto it. See [2.2](#22-organization--turning-a-building-into-a-government-office).

---

### Module: Departments

**File:** `app/Models/Department.php` · **Table:** `departments`
An organisational unit inside a company, optionally tied to a location and a manager. Users belong to a department. Used mostly for reporting and filtering.

## 1.5 The process modules

### Module: Checkout / Checkin (the heart)

**Files:** `app/Http/Controllers/*/`*`CheckoutController.php`, `*CheckinController.php`, `app/Events/`, `app/Listeners/CheckoutableListener.php`

This is the flow the entire main project exists to support.

```
1. User opens the checkout form for an item
2. Permission check      @can('checkout', Asset::class)     → app/Policies/
3. FMCS check            $item->canCheckoutTo($target)      → CompanyableTrait
   (Gov-Store adds: office-boundary check + storekeeper-duty check)
4. $item->checkOut(...)
      · set the holder (user / asset / location)
      · set the physical location
      · set status and dates
      · save
5. Event fired:  CheckoutableCheckedOut
      ├─ CheckoutableListener writes an Activity Log entry
      ├─ CheckoutableListener sends the notification (e-mail / Slack / Teams / Google Chat)
      └─ if the Category requires acceptance → CreateCheckoutAcceptanceAction
            creates a pending acceptance the holder must sign
6. Redirect, decided by Helper::getRedirectOption($request, $id, $table)
```

**Checkin** is the mirror image: clear the holder, send the item back to its `rtd_location`, fire `CheckoutableCheckedIn`, log, and e-mail if the Category says `checkin_email`.

**Bulk operations** exist for most entities (`BulkAssetsController`, `BulkUsersController`, …) and simply loop the same logic over many rows inside a transaction.

**The redirect trick.** After a checkout you often want to land back on the *person* you just gave things to. The form sends three hidden fields — `redirect_option=target`, `checkout_to_type=user`, `assigned_user={{ $user->id }}` — and `Helper::getRedirectOption()` reads them.

---

### Module: Checkout Acceptances (EULA / signature)

**Files:** `app/Models/CheckoutAcceptance.php`, `app/Models/Checkoutable.php`, `app/Actions/Acceptances/CreateCheckoutAcceptanceAction.php`, `app/Http/Controllers/Account/AcceptanceController.php` · **Table:** `checkout_acceptances`

**What it is for.** Legal proof that a person accepted responsibility for an item.

**What it does.** When a Category has `require_acceptance = true`, checking an item out creates a **pending acceptance** rather than a finished handover. The holder sees it on their account page, reads the EULA text (which is *copied into* `stored_eula`, so later edits to the category text cannot rewrite history), and either accepts — optionally drawing a signature, saved to `signature_filename` — or declines. `accepted_at` / `declined_at` record the outcome, and events `CheckoutAccepted` / `CheckoutDeclined` fire.

The `Checkoutable` value object (`app/Models/Checkoutable.php`) is a small helper that takes any pending acceptance and flattens it into one consistent shape — company, category, model, name, tag, assignee — regardless of whether the underlying item is an Asset, Accessory, Consumable, Component or LicenseSeat. It exists so the "unaccepted assets" report and its CSV export can render a mixed list without a pile of `if` statements.

---

### Module: Checkout Requests

**Files:** `app/Models/CheckoutRequest.php`, `app/Models/Traits/Requestable.php`, `app/Actions/CheckoutRequests/` · **Tables:** `checkout_requests`, `requests`, `requested_assets`

Snipe-IT's own lightweight "I would like this" feature. Items flagged `requestable = 1` appear in a self-service list; a user requests one; an admin approves or denies. `CreateCheckoutRequestAction` and `CancelCheckoutRequestAction` handle the two transitions.

> **Note:** Gov-Store does **not** use this. It replaces it entirely with the much richer basket/approval/fulfilment workflow in [`custom-requests`](#27-custom-requests--the-internal-shop-request--approve--issue). The native feature still exists but is bypassed.

---

### Module: Maintenances

**Files:** `app/Models/Maintenance.php`, `app/Http/Controllers/MaintenancesController.php` · **Tables:** `maintenances`, `maintenance_types`

Repair and service history for an asset. Records the type (repair, upgrade, calibration…), the supplier who did the work, start and completion dates, cost, and whether it was covered by warranty (`is_warranty`). While an asset is out for repair it can be recorded as checked out to the repair shop (`checked_out_to_type` / `checked_out_to_id`).

---

### Module: Activity Log (Actionlog)

**File:** `app/Models/Actionlog.php`, `app/Models/Traits/Loggable.php` · **Table:** `action_logs`

**What it is for.** The permanent "who did what to what, and when" record. Almost every meaningful action writes here.

**Shape of a row.** `action_type` (checkout, checkin, update, delete, accepted…), the **item** it happened to (`item_type` + `item_id` — polymorphic, so it can point at any entity), the **target** it happened *to/for* (`target_type` + `target_id` — usually the user who received something), who did it (`created_by`), a free-text note, and forensic detail: `remote_ip`, `user_agent`, `action_source`, `action_date`.

The `Loggable` trait gives models convenience methods like `logCheckout()`, `logCheckin()`, `logAudit()`. Gov-Store's store-operations listener `WriteNativeAuditLogs` deliberately writes here too, so that stock movements show up in Snipe-IT's own history screens rather than living in a hidden parallel log.

---

### Module: Notifications

**Folder:** `app/Notifications/` · **Channels:** e-mail, Slack, Microsoft Teams, Google Chat

Triggered by events, not called directly. Covers: item checked out, item checked in, acceptance required, acceptance reminder, expiring licence, expiring warranty, low inventory, audit due, password reset, welcome mail. Who receives what is configured in Settings and per-Category (`checkin_email`, `alert_on_response`).

---

### Module: Reports & Report Templates

**Files:** `app/Http/Controllers/ReportsController.php`, `app/Models/ReportTemplate.php` · **Table:** `report_templates`

Built-in reports: full asset export, depreciation, activity, licence, unaccepted assets, audit due, custom report builder. The custom builder lets a user tick which columns and filters they want and **save that selection as a Report Template** for re-use.

One quirk worth knowing: the unaccepted-assets route is named with slashes rather than dots — use `route('reports/unaccepted_assets')`, not `route('reports.unaccepted_assets')`.

---

### Module: CSV Importer

**Folder:** `app/Importer/`, `app/Http/Controllers/Api/ImportController.php` · **Table:** `imports`

Bulk-loads assets, users, accessories, consumables, components and licences from CSV. The flow is: upload → the system reads the header row → the user maps each CSV column to a system field → the importer runs row by row, creating missing reference data (manufacturers, categories, models, suppliers) on the fly, and reports errors per row.

> **Caution for Gov-Store:** the importer creates records in ways that can bypass the tenant boundary observers. Treat imports as an administrative, superuser-only operation.

---

### Module: Settings

**File:** `app/Models/Setting.php`, `app/Http/Controllers/SettingsController.php`, `app/Observers/SettingObserver.php` · **Table:** `settings`

One single row holding every system-wide preference: branding, e-mail configuration, LDAP/SAML details, security policy (password rules, 2FA enforcement), label layout, alert thresholds, localisation, and the **FMCS master switch** (`full_multiple_companies_support`). Read it anywhere with `Setting::getSettings()`, or in Blade simply as `$snipeSettings`.

---

### Module: Labels & QR codes

**Files:** `app/Http/Controllers/LabelsController.php`, `app/Models/Labels/`, `app/Http/Controllers/QrCodeController.php`

Generates printable asset tags on a range of standard label-sheet layouts, with barcodes (Code128) or QR codes that link back to the asset page. Used for physical stickers and for scan-based audits.

## 1.6 The cross-cutting machinery

**Policies (`app/Policies/`).** All "may this user do X?" logic. Every entity has one. `SnipePermissionsPolicy` is the abstract base — its `before()` method grants everything to `admin` users (subject to FMCS) and then defers to the specific method. `CheckoutablePermissionsPolicy` extends it for anything checkoutable, adding `checkout()`, `checkin()` and `manage()`. Because those accept `$item = null`, you can ask `@can('checkout', \App\Models\Asset::class)` in a view without holding a specific asset.

**Model traits (`app/Models/Traits/`).**
- `CompanyableTrait` — automatic company scoping + the `canCheckoutTo()` gate.
- `Loggable` — one-line helpers for writing activity log entries.
- `Acceptable` — the EULA/acceptance behaviour.
- `Requestable` — the native "can be requested" behaviour.
- `Searchable` — a shared, consistent search implementation across entities.
- `HasUploads` — file attachments.
- `Depreciable` — depreciation arithmetic.

**Observers (`app/Observers/`).** Automatic reactions to model lifecycle events. `AssetObserver` writes activity-log entries and keeps checkout counters accurate; `SettingObserver` clears cached settings; `UserObserver` handles cleanup when a user changes.

**Actions (`app/Actions/`).** Small classes that each do exactly one job, so controllers stay thin. Destroy actions (`DestroyCategoryAction`, `DestroyCompanyAction`, …) exist because deleting reference data safely requires checks the controller should not carry.

**Helpers (`app/Helpers/Helper.php`).** Grab-bag of shared utilities. Three you will meet constantly:
- `Helper::deployableStatusLabelList()` — the status dropdown for checkout forms.
- `Helper::defaultChartColors()` — the ten-colour palette for dashboards.
- `Helper::getRedirectOption($request, $id, $table)` — where to send the user after a checkout.

**Charts.** Chart.js **v2.9.4**, bundled at `public/js/dist/Chart.min.js`. It is the v2 API — horizontal bars use type `horizontalBar`, not the v3 style.

**Select2 AJAX dropdowns.** Add `class="js-data-ajax"` and `data-endpoint="hardware|licenses|consumables|…"` to a `<select>`; `snipeit.js` wires it up automatically and forwards `data-company-id` as `companyId` and `data-asset-status-type` as `statusType` to the API.

**Translations.** Every user-visible string is a translation key in `resources/lang/en-US/` (`general.php` and siblings). Never hard-code English in a view.

**Routes & breadcrumbs.** `routes/web.php` (screens) and `routes/api.php` (data). Breadcrumbs are declared inline on the route with `->breadcrumbs(fn (Trail $trail) => …)` from the `tabuna/breadcrumbs` package. Every screen route should have one.

---
---

# PART 2 — THE CUSTOM PACKAGES (`packages/gov-store/*`)

## 2.0 Why packages exist and how they attach

### The problem plain Snipe-IT cannot solve

Snipe-IT assumes **one organisation, one shared pool of data, and trusted administrators**. This deployment is a **government**: many Ministries, thousands of offices spread across a geographic hierarchy, staff who transfer between offices, and legal requirements for how stock is received and issued.

Five things had to be built:

1. **Hard data isolation.** Office A must not see Office B's stock — not on a screen, not in a dropdown, not through the API, not by guessing a URL.
2. **A storefront.** Employees should *request* items like an online shop, instead of an admin assigning everything by hand.
3. **Multi-step approvals.** Some items need one signature, some need two, some need none.
4. **A real staff lifecycle.** People move office. They must be *cleared* (nothing held, no duties left dangling) before leaving, and *claimed* by the receiving office.
5. **Official store paperwork.** Government stores run on Goods Receipts, Goods Issues, and a stock register (kardex) with running balances — not on informal checkouts.

### The six ways a package plugs in without editing core files

**1 — Composer path repositories.** `composer.json` declares the packages directory as a local repository and requires each package:
```jsonc
"repositories": [ { "type": "path", "url": "./packages/gov-store/*" } ],
"require": { "gov-store/custom-requests": "dev-master", … },
"autoload": { "psr-4": { "GovStore\\CustomRequests\\": "packages/gov-store/custom-requests/src/", … } }
```

**2 — Service providers**, listed in `config/app.php`:
```
GovStore\CustomRequests\Providers\CustomRequestServiceProvider
GovStore\Organization\Providers\OrganizationServiceProvider
GovStore\GeoAreas\Providers\GeoAreasServiceProvider
GovStore\TenantScope\Providers\TenantScopeServiceProvider
GovStore\OfficeMembership\Providers\OfficeMembershipServiceProvider
GovStore\StoreOperations\Providers\StoreOperationsServiceProvider
GovStore\Classification\Providers\ClassificationServiceProvider
GovStore\UserOnboarding\Providers\UserOnboardingServiceProvider
```
Each provider's `boot()` loads that package's migrations, routes, views (under a namespace like `govstore::`, `govorg::`, `govmem::`, `govscope::`, `storeops::`, `gov-classification::`) and translations.

**3 — Middleware pushed onto core groups.** Providers call `$router->pushMiddlewareToGroup('web', …)` — and `'api'` for the tenant context — so the custom filters run on every request without anyone editing Laravel's kernel.

**4 — Runtime relationships on core models.** Instead of editing `app/Models/Location.php`, the provider attaches the relationship in memory at boot:
```php
Location::resolveRelationUsing('profile', fn($l) => $l->hasOne(LocationProfile::class, 'location_id', 'id'));
User::resolveRelationUsing('memberships', fn($u) => $u->hasMany(OfficeMembership::class, 'user_id', 'id'));
```

**5 — Global scopes and observers on core models.** `Asset::addGlobalScope(new MinistryLocationScope())` silently filters *every* query. `Model::observe(TenantMutationObserver::class)` guards every create, update and delete.

**6 — A central menu registry.** Rather than each package injecting its own snippet into the sidebar, `tenant-scope` provides a shared `MenuRegistry` singleton. Every package registers its menu items into it with an id, parent, title, icon, route, required permission and sort order; the registry then builds one permission-filtered tree and it is rendered into the existing AdminLTE sidebar.

### How to recognise Gov-Store things

- Tables start with `gov_` (plus the `custom_service_request*` and `draft_basket*` sets).
- Route names start with `gov.` or `storeops.`.
- URLs live under `/gov-store/…`, `/gov-requests/…` or `/admin/catalog/…`.
- PHP namespaces start with `GovStore\`.

---

## 2.1 `geo-areas` — the map of Bangladesh

**Namespace:** `GovStore\GeoAreas` · **Table:** `gov_geo_areas` · **Views:** none (API only) · **Depends on:** nothing

### What it is for

Every office must sit at a **real place**. This package is the reference map: the full administrative hierarchy of Bangladesh — Division → District → Upazila → Union — with names in both Bangla and English. Nothing here is business logic; it is the ground truth that everything geographic stands on.

### The table

| Column | Meaning |
|---|---|
| `GeoAreaId` (primary key) | Stable unique ID for the area |
| `hid` | **The hierarchy path.** A string like `/10/1004/100415/`. A child's `hid` always *starts with* its parent's `hid`. |
| `geo_type` | division / district / upazilla / union / city / ward … |
| `geo_code`, `parent_geo_code` | The official numeric codes |
| `bn_name`, `en_name` | Bangla and English names |
| `GeoLevel` | Depth in the tree |

Data is seeded automatically from `src/database/data/geo_areas.csv` during migration.

### The `hid` trick — the single most reused idea in the whole system

To answer *"give me every area inside Dhaka Division"* you do **not** walk a tree recursively. You run one indexed query:

```sql
WHERE hid LIKE '/10/%'
```

One query, any depth, instantly. This one idea powers ICT-officer territory scoping, office provisioning boundary checks, and geographic filtering everywhere else. It is worth internalising.

### `GeoAreaService` — function by function

| Function | What it does |
|---|---|
| `getById(int $id): ?GeoArea` | Fetch one area by its primary key. Returns `null` if not found. |
| `getAllDistricts(): Collection` | Every district-level area, alphabetically. Other packages call this to fill filter dropdowns without knowing anything about the table. |
| `resolveParentNames(string $hid): array` | Given a hierarchy path, walks each code in it and pulls out the English names of the Upazila/City level and the District level. Returns `['city' => …, 'state' => …]`. Used when creating an Office so the core Snipe-IT Location gets sensible city/state values filled in automatically. |
| `search(string $term, array $types = [], ?string $restrictToHid = null, int $limit = 15)` | The search that powers every geography dropdown. Three filters combine: (1) text match on **both** `en_name` and `bn_name`, so a user can type in either language; (2) restrict to certain `geo_type`s; (3) **restrict to a subtree** using the `hid LIKE 'prefix%'` trick — this is how an ICT officer's dropdown only ever offers places inside their own territory. |
| `isWithinBoundary(int $officerGeoId, int $targetOfficeGeoId): bool` | The security question: *"is the place they picked inside the territory they govern?"* Loads both areas and returns `str_starts_with($target->hid, $officer->hid)`. If either area is missing, returns `false` (fail closed). Called by `OfficeProvisioningService` before letting anyone create an office. |

### Controller & route

`GeoAreaController@search` at `GET /gov-store/api/geo/search`, route name `gov.geo.search`. Returns JSON shaped for Select2 dropdowns. Every other package's geography picker calls this one endpoint.

---

## 2.2 `organization` — turning a building into a Government Office

**Namespace:** `GovStore\Organization` · **Views:** `govorg::` · **Depends on:** geo-areas (and reads office-membership's responsibilities table)

### What it is for

In plain Snipe-IT a **Location** is barely more than an address. In this system a Location must become a fully-described **Government Office**:

- pinned to a real place on the map,
- owned by a Ministry or Department (a Snipe-IT Company),
- staffed with an **Office Admin** and a set of duties,
- walked through a **lifecycle** so the system knows when the office is actually ready to trade.

This package also defines **ICT Officer jurisdictions** (which geographic territory an IT officer governs) and **Company Admins** (who oversees an entire Ministry), and it imports the official government ministry directory.

### The tables

| Table | What it holds |
|---|---|
| `gov_location_profiles` | A one-to-one extension of a core `locations` row. Mandatory `geo_area_id`, optional `office_admin_id`, a `lifecycle_status`, geo-verification stamps, and (added later) a shareable `invitation_code` with created/expiry timestamps. |
| `gov_ict_jurisdictions` | Maps one user to one geographic area. That user governs everything inside that subtree. One row per user (unique). |
| `gov_location_roles` | The original approver/storekeeper matrix with delegate columns and delegate-until dates. **Now largely superseded** by `gov_office_responsibilities` in the office-membership package — see the note below. |
| `gov_organization_activity_logs` | An append-only audit trail of office events: `office_created`, `admin_assigned`, `roles_configured`, `status_changed`, `membership_granted`. Stores a JSON `details` blob. |
| `gov_ministries_directory` | The official government organogram imported from CSV: bilingual names, org type, parent, hierarchy path, domain, and the linked Snipe-IT Company. |
| `gov_company_admins` | Which users administer which Ministry (Company). |

> **Historical note that saves confusion:** `gov_location_roles` was the first design — one row per office with `primary_approver_id`, `storekeeper_id` and so on. It has been replaced by `gov_office_responsibilities` (a flexible many-rows-per-office pivot in the office-membership package). `OfficeProvisioningService` no longer creates a `gov_location_roles` row. The table and model still exist and are still referenced by a few legacy paths (notably the superadmin "strip roles" override), so do not assume it is dead — but **write new code against `OfficeResponsibility`.**

### The office lifecycle

```
provisioned  →  configured  →  operational
     │              │               │
 created, has   an Office      all readiness
 a geo pin      Admin was      checks pass;
                assigned       the office can trade
```

The transition to `operational` is not manual — it is computed. See `OfficeReadinessService` below.

### `OfficeProvisioningService` — function by function

**`provisionOffice(array $data, int $executorId): Location`**

The main creation flow. In order:

1. **Security boundary check.** If the person doing this is *not* a superuser or admin, load their ICT jurisdiction and call `GeoAreaService::isWithinBoundary()`. If the chosen place is outside their territory, throw *"Access Denied: The chosen territory lies outside of your assigned geographical jurisdiction."* This is the hard stop that keeps an officer in Rangpur from creating offices in Chattogram.
2. **Duplicate pre-check.** If a Company was given, look for an existing Location of that Company whose profile already points at the same geo area. If one exists, flash a **warning** (not an error) — sometimes a Ministry legitimately has two offices in one Upazila, so the human decides.
3. Everything below runs inside a **database transaction** — if any step fails, nothing is written.
4. **Identity check.** If `existing_location_id` was supplied, this is an *onboarding* of a legacy Location: reload it and optionally rename it. Otherwise create a brand-new `Location`.
5. Fill structural attributes: parent, company, city, state, `country = 'Bangladesh'`, `currency = 'BDT'` (Snipe-IT requires a currency).
6. Save. If Snipe-IT's own validation rejects it, extract the real message and throw it, rather than failing silently.
7. Create the `LocationProfile` with the **mandatory** `geo_area_id`, optional office admin, and `lifecycle_status = 'provisioned'`.
8. Write an `office_created` entry into the activity log.
9. Return the Location.

**`assignOfficeAdmin(int $locationId, ?int $adminId, int $executorId): void`**

Inside a transaction: load the profile, compare old and new admin (do nothing if unchanged), set `office_admin_id`, and move the lifecycle to `configured` if an admin was set or back to `provisioned` if it was cleared. Log an `admin_assigned` event recording both old and new IDs.

### `OfficeReadinessService` — function by function

**`evaluateAndTransition(int $locationId): array`**

The gate that decides whether an office may operate. It builds a four-item checklist:

| Check | Passes when |
|---|---|
| `has_office_admin` | the profile has an `office_admin_id` |
| `has_primary_approver` | some `gov_office_responsibilities` row at this office has `role_slug = 'primary_approver'` |
| `has_storekeeper` | some row has `role_slug = 'storekeeper'` |
| `has_users` | at least one user has this `location_id` (queried with `withoutGlobalScopes()` so the tenant wall does not hide the count from the person checking) |

If **all four** pass, the lifecycle becomes `operational`; otherwise `configured`. If the status actually changed, a `status_changed` event is written to the activity log. Returns `['is_operational' => bool, 'checklist' => [...], 'users_count' => int]` so the setup screen can show a live tick-list.

### `OfficeConfigurationService` — function by function

**`saveRoles(int $locationId, array $roles, int $executorId): void`**

Backs the "who does what in this office" form. Inside a transaction:

1. **Delete every existing responsibility** for this office. (Replace-all, not merge — the form is the complete truth.)
2. Map the three form fields to role slugs: `primary_approver_id → primary_approver`, `final_approver_id → final_approver`, `storekeeper_id → storekeeper`.
3. Create one `OfficeResponsibility` row per non-empty field.
4. Write a `roles_configured` audit entry containing all three IDs.
5. **Immediately re-run `OfficeReadinessService::evaluateAndTransition()`** — so an office flips to `operational` the very moment the last required duty is filled, with no separate "activate" button.

### `MinistryDirectoryImporter` — function by function

**`import(string $csvPath): array`**

Loads the official government organogram and synchronises it with Snipe-IT Companies. Three passes:

- **Pass 1 — read.** Parse the CSV into memory, keyed by ID, capturing Bangla name, English name, org type, parent names (both languages) and domain. Build a name→ID lookup map.
- **Pass 2 — resolve parents and guard against loops.** Match each row's parent by name, then compute its `hid` hierarchy path via `buildHidPath()`, which walks up the parent chain recursively while keeping a `visited` list so a malformed CSV with a circular parent reference cannot hang the import.
- **Pass 3 — synchronise Companies**, inside a transaction. For each row it hunts for the matching Snipe-IT Company in **priority order**: (1) already linked via the directory row; (2) matched by unique government domain; (3) matched by the target bilingual name; (4) matched by the raw English name (this catches legacy records). If found, it renames the Company to the standard bilingual format if needed; if not found, it creates the Company. Then it writes or updates the `MinistryDirectory` row linking directory ID ↔ Company ID.

Returns statistics (created / updated / matched / total) plus a list of warnings. Non-destructive: it never deletes a Company.

**`buildHidPath(int $id, array $rawRows, array $visited = []): string`** — recursive path builder described above; returns `/` if a cycle is detected.

### Observers

- **`IctJurisdictionObserver`** — when a jurisdiction row is created, find the Snipe-IT permission group named **"ICT Operations"** and attach the user to it; when deleted, detach. This keeps native Snipe-IT permissions in step with the Gov-Store role automatically.
- **`CompanyAdminObserver`** — when a Company Admin is created, set the user's native `company_id` to that company (so core Snipe-IT recognises them) and attach them to the **"Company Administration"** group; on delete, detach the group.

### Middleware

- `InjectOrganizationUi` — sidebar/UI injection.
- `EnsureOfficeIsOperational` — can block or redirect a flow when the office has not reached `operational` yet.
- `EnsureUserIsIctOfficer` — gate for ICT-only routes.

### Controllers & routes

Prefix `gov-store/admin/organization`, route names `gov.org.*`:

| Route | Controller method | Purpose |
|---|---|---|
| `GET /` | `ProvisioningController@index` | The Office Registry dashboard — searchable list of all offices in your bounds |
| `GET /create` · `POST /store` | `ProvisioningController@create` / `@provision` | Create a new office |
| `GET /geo-search` · `GET /check-duplicate` | `ProvisioningController` | AJAX helpers for the create form |
| `GET/POST/DELETE /jurisdictions*` | `ProvisioningController@jurisdictions*` | Manage ICT officer territories (superadmin) |
| `GET /onboard` · `POST /onboard/store` | `OnboardLocationController` | Adopt a pre-existing legacy Location into Gov-Store |
| `GET /{id}/hub` | `OfficeHubController@show` | The per-office control panel |
| `POST /{id}/update` · `/save-roles` · `/verify-geo` | `OfficeHubController` | Edit the office, set duties, confirm the geo pin |
| `GET /directory` · `POST /directory/import` | `MinistryDirectoryController` | Import the government organogram (superadmin) |

Prefix `gov-store/office` — the *local* office admin's own area:

| Route | Controller | Purpose |
|---|---|---|
| `GET /` · `POST /save` | `ConfigurationController@index` / `@save` | The local activation checklist: assign duties, watch the office turn operational |
| `GET/POST /company-admins*` | `CompanyAdminController` | Manage Ministry administrators (superadmin only) |

`OfficeRegistryViewModel` is a small presentation helper that packages a Location plus its role row into one object for the registry table, keeping the Blade template free of query logic.

---

## 2.3 `office-membership` — staff belong to offices, and can move

**Namespace:** `GovStore\OfficeMembership` · **Views:** `govmem::` · **Depends on:** organization

### What it is for

This package exists because of one insight, taken directly from the module's own design notes:

> A person's **Identity** (their user account) is permanent. Their **Membership** (which office they work in) is temporary. Their **Responsibilities** (storekeeper, approver) are temporary *and separate*. And their **Working Context** (which office they are acting as *right now*) is a session thing that can change several times a day.

Plain Snipe-IT smashes all four into one field: `users.location_id`. That breaks the moment someone works at two offices, or transfers, or hands over the storekeeper duty for a week.

### The tables

| Table | What it holds |
|---|---|
| `gov_office_memberships` | The link "this user belongs to this office". `is_home_office` (their permanent HR base), `status` (`active` / `pending` / `suspended` / `release_requested` / `released` / `inactive`), optional `valid_until` for temporary postings, plus approval metadata (`approved_by_user_id`, `approved_at`, `approval_note`). **Unique on (user_id, location_id)** — you cannot have two membership rows for the same pair, which is why the code uses `updateOrCreate` everywhere. |
| `gov_office_responsibilities` | The live duty matrix: `(location_id, user_id, role_slug)`. `role_slug` is `storekeeper`, `primary_approver` or `final_approver`. Unique on all three columns. **This is the authoritative answer to "who is the storekeeper here right now?"** |
| `gov_role_handshakes` | Pending peer-to-peer duty handovers: `role_type`, `outgoing_user_id`, `incoming_user_id`, `status` (pending/accepted/rejected/cancelled). |
| `gov_role_assignments` | The parallel, admin-driven assignment flow (see the note below). |
| `gov_override_audit_logs` | Permanent record of superadmin emergency overrides: target user, override type, mandatory reason, executor, old/new location. |
| `gov_employee_verification_tokens` | Short-lived 6-character codes an employee generates to prove their identity to a receiving office admin. 24-hour expiry, single use. |

> **Two similar services, and why.** `RoleHandshakeService` is the **peer-to-peer** flow — *I* hold the duty, *I* offer it to a colleague, *they* accept. `RoleAssignmentService` is the **administrative** flow, writing into the older `gov_location_roles` / `LocationProfile` columns. Both exist. New work should use the handshake path and `OfficeResponsibility`.

### `ClearanceEngine` — the leaving gate

**What it is for.** Before someone may leave an office, the system must be certain they are not walking away with equipment or leaving a duty vacant.

**Design.** A rule *stack*. Rules are registered at boot in the service provider; adding a new rule later means writing one class and registering it — nothing else in the system changes.

| Function | What it does |
|---|---|
| `registerRule(IClearanceRule $rule): void` | Adds a rule to the stack. |
| `runChecks(User $user, int $locationId): array` | Runs every registered rule and returns an array of `ClearanceResult` objects keyed by the rule's display name. |
| `isCleared(array $results): bool` | `true` only if **every** result passed. One failure blocks the release. |

`ClearanceResult` is a tiny value object: `isPassed` (bool) plus a human-readable `reason` string shown directly to the employee.

**The three registered rules:**

| Rule | The check | Message when it fails |
|---|---|---|
| `NoActiveAssetsRule` | Count assets where `assigned_to = this user` **and** `location_id = this office`. Must be zero. | "you hold N active assets" |
| `NoActiveRolesRule` | Count `gov_office_responsibilities` rows for this user at this office. Must be zero. | "you hold N office responsibilities" |
| `NoPendingRequestsRule` | Count service requests raised by this user for delivery to this office whose approval status is not `rejected`, `cancelled` or `closed`. Must be zero. (Guards with `class_exists()` so the rule silently passes if the custom-requests package is absent.) | "you have N requests still in progress" |

### `RoleHandshakeService` — handing over a duty

| Function | What it does, step by step |
|---|---|
| `proposeHandshake($locationId, $roleSlug, $fromUserId, $toUserId)` | (1) Refuse self-delegation. (2) Verify the proposer **actually holds** that duty at that office (`OfficeResponsibility` lookup) — you cannot give away what you do not have. (3) Refuse if the same person already has a pending handover of the same duty. (4) Create the handshake with status `pending`. Note the *anti-corruption layer*: the domain calls it `role_slug` but the older database column is `role_type`, so the model translates between them via accessor/mutator. |
| `acceptHandshake($handshakeId, $userId)` | The atomic transfer. Inside **one transaction**: (1) load the pending handshake addressed to this user, or fail; (2) **delete** the outgoing user's `OfficeResponsibility` row; (3) **create** the incoming user's; (4) **cancel every other pending handshake** for the same duty at the same office (only one person can hold it); (5) mark this handshake `accepted`; (6) write a `roles_configured` entry to the organization activity log describing exactly who handed what to whom. Because it is one transaction, there is never a moment where the office has two storekeepers or none. |
| `rejectHandshake($handshakeId, $userId)` | Recipient declines — status becomes `rejected`. |
| `cancelHandshake($handshakeId, $userId)` | Proposer withdraws — status becomes `cancelled`. |

### `RoleAssignmentService` — the administrative variant

| Function | What it does |
|---|---|
| `proposeTransfer($locationId, $roleType, $fromUserId, $toUserId)` | Refuses self-assignment and duplicate pending proposals; creates a `pending` `RoleAssignment`. |
| `acceptTransfer($assignmentId, $userId)` | Inside a transaction: if the role is `office_admin`, update `LocationProfile.office_admin_id`; otherwise `firstOrCreate` the `LocationRole` row and set the `{roleType}_id` column. Mark the assignment `completed`, then log a `roles_configured` audit event. |
| `rejectTransfer` / `cancelTransfer` | Mark rejected, or hard-delete the draft to keep the table clean. |

### `OfficeMembershipService` — the plumbing

| Function | What it does |
|---|---|
| `getActiveMembers(int $locationId): Collection` | Every user with an *active* membership at this office, sorted by first name. Used to populate "who works here" lists and role dropdowns. |
| `getUserMemberships(int $userId): Collection` | Every active office this user belongs to, home office first. Powers the context switcher. |
| `grantMembership(int $userId, int $locationId, bool $isHome = false, $validUntil = null)` | **The core authorisation call.** If this is being set as the home office, first clear the `is_home_office` flag on *all* the user's other memberships — a person has exactly one HR base. Then `updateOrCreate` the membership as active. |
| `revokeMembership(int $userId, int $locationId)` | Sets status `inactive` and clears the home flag. Does not delete, so the history survives. |

### `LegacyUserSynchronizationService` — keeping core Snipe-IT honest

Plain Snipe-IT lets an admin change `users.location_id` on a form. That would silently teleport a person between offices, bypassing every clearance rule. This service intercepts that.

| Function | What it does |
|---|---|
| `handleNewUser(User $user)` | When a user is created with a location, grant them an active **home** membership there, stamped as approved by the current user (or system user 1 for CLI). |
| `handleUpdatedUser(User $user)` | When `location_id` changes: (1) find their old home membership; (2) **run the full ClearanceEngine against the old office**; (3) if they are *not* cleared, **revert `location_id` to the old value**, save quietly (so observers do not loop), log a warning and flash an error — the transfer is blocked; (4) if cleared, mark the old membership `released` and grant an active home membership at the new office. |

### `UserSyncObserver`

Attached to the core `User` model. `created()` → `handleNewUser()`. `updated()` → if `location_id` actually changed, `handleUpdatedUser()`. This is the hook that makes the paragraph above happen automatically, without touching `UsersController`.

### `MembershipActivityLogObserver`

Attached to `OfficeMembership`. Writes to `gov_organization_activity_logs` on create (`membership_granted`), on status change (`status_changed`, recording old → new), and on delete (`membership_revoked`). Falls back to user ID 1 when running from the command line.

### `SetWorkingContext` middleware — which office am I acting as?

Runs on **every web request**. This is what makes multi-office work possible.

1. Skip if not logged in.
2. If the session's remembered user is not the current user, **wipe the working context** — this prevents one person's office context leaking to the next person on a shared machine.
3. If no working membership is set in the session yet:
   - Find the user's memberships that are `active`, ordered so `is_home_office = true` comes first, then oldest first as a deterministic tie-break.
   - Put that membership's ID into the session key **`gov_working_membership_id`**.
   - **Self-heal:** if the core `users.location_id` disagrees with the membership's location, correct it with `saveQuietly()` (quiet = do not re-fire observers and cause a loop).
   - If the user has *no* memberships at all, leave the session key unset so `InitializeTenantContext` can fall back to their native location.

That one session key — `gov_working_membership_id` — is the pivot the entire isolation engine turns on.

### `SyncInitialMemberships` console command

`php artisan gov-store:sync-memberships` — back-fills the membership table for an already-populated Snipe-IT install. Loops every user with a location and `updateOrCreate`s an active home membership. Run once when bolting Gov-Store onto an existing system.

### Controllers, routes and the five real workflows

**Employee self-service** — prefix `gov-store/my-memberships`, names `gov.membership.*`:

| Route | Method | What happens |
|---|---|---|
| `GET /` | `MembershipController@index` | Builds the "My Office Memberships" screen. For each active membership it runs the ClearanceEngine and stores the results in a matrix; loads eligible colleagues at that office (deliberately bypassing `UserScope`, because you need to see colleagues to delegate to them); works out which duties *you* hold; loads incoming and outgoing pending handshakes; loads your active verification token. |
| `POST /token/generate` | `@generateVerificationToken` | Deletes any previous unused tokens (one active token at a time), generates a unique 6-character uppercase code, stores it with a 24-hour expiry. You read this code out to the office admin who is adding you. |
| `POST /{id}/request-release` | `@requestRelease` | Server-side guard: re-runs the ClearanceEngine (never trust the button being enabled). If cleared, sets membership status to `release_requested`. |
| `POST /switch` | `@switchContext` | Changes the active working office. Three paths: an admin passing `location_id = 0` clears the context back to global; an admin passing a real `location_id` gets a mock context (`ADMIN_MOCK_<id>`); a normal user passes a `membership_id`, which is verified to be theirs and active before the session key is updated. |
| `POST /join` | `@joinByCode` | Self-service join using an office **invitation code**. Looks up the `LocationProfile` by code, rejects if missing or expired, rejects if you are already active or already pending, then `updateOrCreate`s a membership with status `pending` awaiting the office admin's approval. |
| `POST /handshake/propose` · `/{id}/accept` · `/reject` · `/cancel` | `RoleHandshakeController` | Thin wrappers over `RoleHandshakeService`. |

**Office admin staff hub** — prefix `gov-store/office/staff`:

| Route | Method | What happens |
|---|---|---|
| `GET /` | `MembershipAdminController@index` | Resolves which office you administer (from the tenant context, falling back to a profile lookup; aborts 403 if you administer none and are not a superuser). Loads active staff, pending join requests, and the pool of "floating" users — people whose home membership is `release_requested` or `released`, i.e. available to be claimed. |
| `POST /add-employee` | `@addEmployeeByToken` | Grants **secondary** access. Validates username + 6-character code, checks the token is valid and unused, and **refuses if the person is mid-transfer** (`release_requested`/`released`) — a transferring employee must be *claimed*, not casually added. In one transaction it burns the token and `updateOrCreate`s an active membership with `is_home_office = false`, then logs it. |
| `POST /claim` | `@claimEmployee` | **The permanent transfer.** In one transaction: (1) mark the person's old home membership `released` and clear its home flag; (2) `updateOrCreate` a new **home** membership at this office, active, with a "Claimed Transfer" note; (3) sync the native `users.location_id` with `saveQuietly()` so the sync observer does not fight the change. |
| `POST /generate-invite-code` | `@generateInviteCode` | Creates a unique 8-character office code on the `LocationProfile`, valid 30 days. Hand it to a batch of new staff so they can self-join. |
| `POST /approve/{id}` · `/reject/{id}` | `@approveMembership` / `@rejectMembership` | Approve a pending self-join — **always as a secondary membership** (`is_home_office = false`), which is a deliberate safety guard so a self-join can never quietly relocate someone's HR base. Reject simply deletes the pending row. |

**Superadmin overrides** — prefix `gov-store/admin/memberships`:

| Route | Method | What happens |
|---|---|---|
| `GET /override/console` | `@overrideConsole` | Superadmin only. Shows the full override audit history, the list of users awaiting release, and all users. |
| `POST /override/force` | `@forceOverride` | Requires `override_type` and a **reason of at least 10 characters**. `force_release` sets every membership of that user to `released` and clears home flags. `strip_roles` nulls their approver/storekeeper columns on `LocationRole` and their `office_admin_id` on `LocationProfile`. Either way an `OverrideAuditLog` row is written permanently. For abandoned posts and emergencies. |

### The five workflows in narrative form

1. **Self-release & clearance.** Employee opens *My Office Memberships*. The engine runs; each failing rule shows a plain-English reason and keeps the "Request Release" button disabled. All green → submit → status becomes `release_requested`.
2. **Peer-to-peer handshake.** A rule fails because *you are the storekeeper*. You delegate to a colleague at the same office. They see a pending-handshake box and accept. The transfer happens atomically; your blocker disappears immediately.
3. **Claim employee.** Once released, you appear in the floating pool. The receiving office admin opens their Staff hub and claims you: old membership released, new home membership created, core `users.location_id` synced.
4. **Emergency override.** Someone left without clearance. A superadmin force-releases or force-strips them, typing a mandatory justification that is stored forever.
5. **Multi-office context switching.** A director who oversees three offices switches working context. The session key changes; `InitializeTenantContext` reads it on the next request and instantly re-scopes their catalogue, stock, staff list and requests — with no change at all to their user record.

---

## 2.4 `user-onboarding` — new employees who have no office yet

**Namespace:** `GovStore\UserOnboarding` · **Table:** `gov_user_onboardings` · **Views:** `govonboard::` · **Depends on:** office-membership, organization, tenant-scope

### What it is for

Different people create user accounts for different reasons:

- An **Office Admin** creating a user means "this person works *here*, starting now."
- An **ICT Officer** or **Company Admin** creating a user often means "this person has joined the Ministry; which office they land in is not decided yet."

Plain Snipe-IT cannot tell the difference, so the second case produces an orphan account with no location — invisible to the office-based tenant wall, and easy to forget. This package catches those orphans and puts them in a visible queue.

### The table

`gov_user_onboardings`: one row per user (unique). `status` (`WAITING` / `COMPLETED` / `CANCELLED`), `creator_user_id`, `owner_type` (`OFFICE_ADMIN` / `ICT_OFFICER` / `COMPANY_ADMIN` / `SYSTEM`), `owner_id`, an optional `geo_area_id` that spatially tags the orphan so the right ICT officer sees them, and `assigned_membership_id` once resolved.

### `SnipeUserOnboardingObserver` — function by function

Attached to the core `User` model.

**`creating(User $model)`** — runs *before* the row is saved. Resolves the creator's active role via `AssignmentResolver`. If that role is `office_admin` **and** no location was filled in, it auto-injects the admin's own office and company. This is a convenience: an office admin adding staff should not have to pick their own office from a dropdown every time.

**`created(User $model)`** — runs *after* the save, and picks one of two paths:

- **Office Admin path.** Immediately grant a home-office membership at the admin's office and write an onboarding row with `status = 'COMPLETED'`, `owner_type = 'OFFICE_ADMIN'`. Done — the person is fully onboarded in one step.
- **ICT Officer / Company Admin path.** Write an onboarding row with `status = 'WAITING'`. If the creator is an ICT officer, also record their jurisdiction's `geo_area_id` so the orphan is geographically tagged. No membership is granted yet.

### `UserOnboardingService` — function by function

**`assignToOffice(int $onboardingId, int $locationId): void`**

Completes an onboarding. Refuses if the row is not `WAITING`. Inside a transaction:

1. Call `OfficeMembershipService::grantMembership($userId, $locationId, isHome: true)`.
2. Look the new membership row back up to get its ID.
3. Sync the core Snipe-IT projection: set the user's `location_id` and inherit `company_id` from the location. (`User::withoutGlobalScopes()` is used here on purpose — the admin doing the assigning may not otherwise be able to "see" this location-less user.)
4. Mark the onboarding `COMPLETED` and store `assigned_membership_id`.

### Controller & routes

Prefix `gov-store/admin/onboard`, wrapped in `InitializeTenantContext`.

- **`checkAccess()`** — superusers and admins pass; otherwise the user must hold an ICT jurisdiction or be a Company Admin. Anyone else gets 403.
- **`index()`** — lists `WAITING` onboardings whose users still have `location_id IS NULL`, with the creator and geo area eager-loaded, paginated 25 at a time, plus the list of locations available for assignment.
- **`assign()`** — validates `onboarding_id` and a real `location_id`, then calls the service; success and error messages come back as flash messages.

---

## 2.5 `tenant-scope` — the invisible wall between offices

**Namespace:** `GovStore\TenantScope` · **Views:** `govscope::` · **Depends on:** office-membership, organization, geo-areas

### What it is for

This is the most important and most cross-cutting package in the system. It answers one question on every single request:

> **Who is this person, which office are they acting as, what slice of the database may they see, and what may they change?**

And then it enforces the answer **automatically**, so no controller, no report, no dropdown and no API endpoint can accidentally leak another office's data by forgetting a `where` clause.

It also **rewrites the user's permissions in memory** on every request, based on the duty they hold at their current office — so the same person can be a storekeeper with full inventory rights at Office A and an ordinary employee at Office B, without their user record ever changing.

### The `TenantContext` object

A singleton created fresh for each request. Think of it as an ID badge the request carries around.

```php
TenantContext {
  bool  $isActive;              // is scoping engaged at all?
  bool  $isGlobal;              // true = superadmin, no restrictions
  ?array $allowedLocationIds;   // pre-computed offices this user may see
  ?array $allowedCompanyIds;    // pre-computed ministries; null = "may see all"
  ?int  $membershipId;          // which membership is active
  ?int  $companyId;             // active ministry
  ?int  $locationId;            // active office — the most-used value in the system
  bool  $isHomeOffice;
  ?EffectivePermissionSet $effectivePermissions;
  array $configs;               // per-reference-type scope strategy
  getConfig(string $referenceType): ?object
}
```

Note the important distinction on `allowedCompanyIds`: **`null` means "unrestricted"**, an **empty array means "may see nothing"**. Getting these two confused is the classic bug in this area.

### `InitializeTenantContext` middleware — the brain

Pushed onto **both** the `web` and the `api` middleware groups, so the REST API is scoped exactly like the screens. Step by step:

1. **Not logged in?** Do nothing and continue. Guests carry no context.
2. **Superadmin, or an admin with no company?** Set `isActive = true`, `isGlobal = true`, and return immediately. Every scope checks `isGlobal` and bows out, so these users see everything.
3. Otherwise `isActive = true`, `isGlobal = false`, and:

   **3a. Resolve the working office.** Read `gov_working_membership_id` from the session (put there by `SetWorkingContext`). If a membership is found, take its `membershipId`, `locationId`, and the location's `companyId`. If not, fall back to the user's own `location_id` / `company_id`.

   **3b. Pre-compute the allowed offices — this is where role shapes the wall:**

   | Who they are | `allowedLocationIds` | `allowedCompanyIds` |
   |---|---|---|
   | **Company (Ministry) admin** — has `admin` access **and** a `company_id` | every Location belonging to their company (queried `withoutGlobalScopes()`, otherwise the scope would filter its own input) | `null` (may see all companies) |
   | **ICT officer** — has a `gov_ict_jurisdictions` row | every Location whose profile's geo area sits under the officer's jurisdiction `hid` — the `LIKE 'prefix%'` subtree query. If their jurisdiction has no geo area, the list is **empty** (see nothing). | `null` |
   | **Everyone else** (employee / storekeeper / approver) | just `[their active working location]` | `[their active company]`, or `[]` if they have none |

   **3c. Resolve duty → permissions.**
   - `AssignmentResolver::resolveActiveRole($userId, $locationId)` returns a role slug.
   - `CapabilityProfileResolver::resolveSchema($roleSlug)` turns that slug into an `EffectivePermissionSet`.
   - `SnipePermissionAdapter::adaptAndInject($user, $set)` writes those permissions onto the in-memory User object **for this request only**. `$user->save()` is never called — the database is untouched.

### `AssignmentResolver::resolveActiveRole(int $userId, ?int $locationId): string`

Returns exactly one role slug, checked in strict priority order and cached for 60 seconds:

1. Is there a `CompanyAdmin` row for this user? → `company_admin`
2. Is there an `IctJurisdiction` row? → `ict_officer`
3. Is this user the `office_admin_id` on this location's profile? → `office_admin`
4. Is there an `OfficeResponsibility` row for this user at this location? → its `role_slug` (`storekeeper`, `primary_approver`, `final_approver`)
5. Otherwise → `employee`

### `CapabilityProfileResolver::resolveSchema(?string $roleSlug): EffectivePermissionSet`

Reads `config('govstore-permissions')` (merged from the package's `src/config/permissions.php`). Starts with the baseline `employee` permission list, then — if the role slug maps to a profile — merges that profile's permissions on top. Returns an `EffectivePermissionSet` carrying the permission array, the role slug and the profile slug.

**The mapping (`responsibilities` → `profiles`):**

| Role slug | Capability profile | What it can do, roughly |
|---|---|---|
| `storekeeper` | `inventory_operator` | Full create/edit/checkout/checkin across assets, consumables, accessories, components, licences; create/edit reference catalogues; view reports. The only profile that may physically move stock. |
| `primary_approver`, `final_approver` | `workflow_operator` | Approve and reject requests; **read-only** across all inventory. Deliberately cannot issue anything — separation of duties. |
| `office_admin` | `office_operations` | Configure the office and assign duties; full user/location/department management; read-only inventory. |
| `ict_officer` | `ict_operations` | Provision and onboard offices, manage jurisdictions; create/edit users and locations; read-only everything else. |
| `company_admin` | `company_operations` | Ministry-wide oversight: provision offices, manage users and locations across the Ministry, advanced reports; read-only over inventory across the whole Ministry. |
| *(default)* | `employee` | Request from the catalogue, view requestable assets, self-service account functions. |

### `EffectivePermissionSet` — function by function

A tiny immutable value object. `has(string $permission): bool` (does this set include that permission?), `getPermissions(): array`, `getRole(): ?string`, `getProfile(): ?string`.

### `SnipePermissionAdapter::adaptAndInject(User $user, EffectivePermissionSet $set): void`

The translation layer between Gov-Store's role model and Snipe-IT's native permission checks. It:

1. **Clears the user's groups in memory** (`setRelation('groups', collect([]))`) so a database-level group grant cannot smuggle in extra rights beyond the active role.
2. Converts the permission list into Snipe-IT's `['permission.key' => 1]` shape.
3. Overwrites the `permissions` attribute, matching whatever type it already was (array or JSON string).
4. **Busts the caches.** Snipe-IT caches compiled permissions in `cached_permissions`, and the underlying Sentinel auth library caches them again inside a permissions instance. Both are cleared and re-seeded, otherwise `hasAccess()` would keep answering from the stale pre-injection copy.

Net effect: `@can(...)`, `hasAccess(...)` and every Snipe-IT policy answer according to the duty the person holds *at the office they are currently acting as*.

### The global scopes — automatic read filtering

Registered on core models at boot. Every one of them exits early if `!isActive` or `isGlobal`, so superadmins are never restricted.

**`MinistryLocationScope`** — applied to `Asset`, `Consumable`, `Accessory`, `Component`, `License` (the operational inventory).
- If the table has a `company_id` column and the context has a company → filter to it.
- If the table has a `location_id` column: filter to the context location — **and if there is no context location, apply `WHERE 1 = 0`**, meaning "see absolutely nothing". Fail closed, not open.

**`UserScope`** — applied to `User`. Restricts to `location_id IN allowedLocationIds`; an empty allowed list becomes `WHERE 1 = 0`. **Plus one critical exception:** the query always also allows `id = auth()->id()`. Without that self-bypass, a user whose location is not in their own allowed list could not load their own record, and the application would fall into a login redirect loop.

**`TenantScope(string $referenceType)`** — applied to `Location` (`'locations'`), `Company` (`'companies'`), and the reference catalogues `Category` (`'categories'`), `AssetModel` (`'models'`), `Supplier` (`'suppliers'`), `Manufacturer` (`'manufacturers'`). Three different behaviours in one class:

- **`locations`** — restrict `id IN allowedLocationIds`; empty list → `WHERE 1 = 0`.
- **`companies`** — if `allowedCompanyIds` is `null`, do nothing (unrestricted). If it is an array, restrict `id IN` it; empty → `WHERE 1 = 0`.
- **Reference catalogues** — the adoption model. A row is visible if **either**:
  - **(A)** it has an *active* mapping in `gov_tenant_scope_mappings` for the current company **or** the current location — i.e. this office/ministry has explicitly adopted it; **or**
  - **(B)** it has **no mapping at all** — i.e. it is a shared national standard that belongs to everyone.

  The consequence is the important part: **the moment someone maps a category to an office, that category disappears for every other office.** Unmapped items stay universally visible.

### Write and delete enforcement — the observer path

Read filtering alone is not enough; a crafted POST could still update someone else's record. So `TenantMutationObserver` is registered on every scoped model and forwards `creating`, `updating` and `deleting` to `TenantBoundaryService::verify($model, $action)`.

### `TenantBoundaryService` — function by function

**`verify(Model $model, string $action): void`**

1. Bail out if the context is not bound, not active, or global.
2. **Resolve the right boundary policy** for this model (see `resolvePolicy` below).
3. If a policy applies:
   - **On create** — *inject* ownership. The policy declares which tenant columns it owns; the service writes the context's `company_id` and `location_id` onto the new record. New rows are therefore **born owned by the correct office**; a user cannot create a record belonging to somewhere else.
   - **On update/delete** — call `$policy->canMutate($model, $context)`. If it returns false, log a security warning with user, action, model and ID, then throw a `TenantBoundaryException` with reason code `OWNERSHIP`.
   - **On create/update** — run `validateRelationshipIntegrity()`.
4. If the model is an `Asset`, additionally run `verifyAssetMutation()`.
5. Finally run `BusinessRuleValidator::validate()`.

**`verifyAssetMutation(Model $asset, string $action, TenantContext $context): void`**

- First, the asset's `location_id` must equal the context location. If not → `TenantBoundaryException` with code `OUT_OF_BOUNDS`, HTTP 403.
- Then, if this is an update and any of `assigned_to`, `status_id` or `location_id` is changing — that is, **a checkout, checkin, status change or transfer is happening** — look up the actor's `OfficeResponsibility` at this office and ask `ResponsibilityRegistry::can($roleSlug, 'checkout_assets')`. If they may not, log the violation and throw code `ROLE_VIOLATION`, HTTP 403. In practice this means **only the storekeeper can move stock**, regardless of what native Snipe-IT permissions say.

**`validateRelationshipIntegrity(Model $model, $policy): void`**

For each foreign key the policy declares in its `relationMap`, and only when a value is present, it runs **two** lookups:

1. `withoutGlobalScopes()->find($id)` — does this row exist *at all*? If not → `NOT_FOUND`, HTTP **404**.
2. `find($id)` (with scopes) — is it visible *to me*? If not → `RELATIONSHIP`, HTTP **403**.

The two-step exists so error messages are honest: "that category does not exist" and "that category exists but is not yours" are different problems, and collapsing them into one message makes debugging miserable. The practical effect: you cannot attach a category, model, supplier or manufacturer from outside your boundary to your asset.

**`resolvePolicy(Model $model)`**
- `Asset`, `Consumable`, `Accessory`, `Component`, `License` → `AssetBoundaryPolicy`
- `Category`, `AssetModel`, `Supplier`, `Manufacturer`, `Location` → `CategoryBoundaryPolicy`
- anything else → `null` (no boundary policy; other checks still run)

Note that `User` is deliberately **excluded** from this transactional policy list — user records are governed by `UserScope` and the membership rules instead.

**`logViolation(Model $model, string $action)`** — writes a `SECURITY VIOLATION: Cross-Tenant mutation blocked` warning to the log with the user ID, action, model class and record ID.

### The boundary policies

**`AssetBoundaryPolicy`** — for operational inventory.
- `tenantColumns = ['company_id', 'location_id']` — declared statically rather than probed from the schema, to avoid an expensive column lookup on every single write.
- `relationMap` = `category_id → Category`, `model_id → AssetModel`, `supplier_id → Supplier`, `manufacturer_id → Manufacturer`.
- `canMutate()`: refuse if the record has a company that is not the context company; refuse if it has a location that is not the context location; otherwise allow.

**`CategoryBoundaryPolicy`** and **`ReferenceBoundaryPolicy`** — for shared catalogues. Both ask `ReferenceOwnershipService` for the ownership state:
- `GLOBAL` (no mapping exists) → **refuse**. Global standards belong to the state; local offices may *use* them but never edit or delete them. Only a true superadmin (who bypasses all this) can change them.
- `COMPANY` → allow only if the owner is the context company.
- `LOCATION` → allow only if the owner is the context location.

**`TransactionalBoundaryPolicy`** — a schema-probing variant of `AssetBoundaryPolicy` (uses `Schema::hasColumn` instead of a static list). Available for models whose columns are not known in advance.

### `ReferenceOwnershipService` — function by function

| Function | What it does |
|---|---|
| `getMapping(Model $model): ?TenantScopeMapping` | Looks up `gov_tenant_scope_mappings` using the model's lowercase class basename as `reference_type` plus its primary key. |
| `getOwnershipState(Model $model): string` | No mapping → `'GLOBAL'`. Otherwise the uppercased `scope_type` — `'COMPANY'` or `'LOCATION'`. |
| `getOwnerId(Model $model): ?int` | The `scope_id` of the mapping, or `null`. |

### `BusinessRuleValidator` — function by function

| Function | What it does |
|---|---|
| `validate(Model $model, string $action)` | Currently routes `delete` to the deletion guard. The hook exists so more rules can be added without touching the boundary service. |
| `enforceDeletionIntegrity(Model $model)` | For `Category`, `AssetModel`, `Supplier`, `Manufacturer` and `Location`: if the model has an `assets()` relationship and any asset still uses it, throw `TenantBoundaryException` with code `BUSINESS_RULE`. Stops someone from deleting "Laptops" while 400 laptops point at it. |

### `ResponsibilityRegistry::can(?string $roleSlug, string $capability): bool`

A small static lookup table of what each duty is allowed to do — deliberately separate from the permission config, because these are *operational* rights rather than UI permissions:

| Role | Capabilities |
|---|---|
| `storekeeper` | `checkout_assets`, `checkin_assets`, `adjust_stock`, `audit_inventory` |
| `primary_approver` | `approve_requests`, `reject_requests` |
| `final_approver` | `approve_requests`, `reject_requests` |
| `office_admin` | `configure_office`, `assign_responsibilities` |

Unknown or null role → `false`. Fail closed.

### `BoundaryResolver::resolveStrategy(User $user, ?string $referenceType = null): string`

A helper that names the strategy in play — `'global'`, `'company'`, `'location'` or `'jurisdiction'`. Superuser outside an office context → `global`; a reference type configured as global → `global`; anyone holding an ICT jurisdiction → `jurisdiction`; otherwise `location` or `company` depending on whether they are in their home office.

### `HandleBoundaryExceptions` middleware

Catches `TenantBoundaryException` anywhere in the request and converts it into a clean HTTP response (403 or 404) with the exception's message, instead of a raw stack trace.

### The configuration tables

**`gov_tenant_scopes`** — one row per reference type recording its `scope_strategy` (`global` / `company` / `office`) and a `show_only_used` flag. Seeded defaults: categories, models and manufacturers = `global`; suppliers = `office` (decentralised procurement); fieldsets = `company`; locations = `company`.

**`gov_tenant_scope_mappings`** — the polymorphic "who owns this reference row" table: `(scope_type = company|location, scope_id)` ↔ `(reference_type, reference_id)`, plus an `is_active` flag added later so a mapping can be **archived without being deleted** (soft-archive, used by the classification package's "My Catalog" screen).

### `MenuRegistry` — the shared sidebar

A singleton every package registers into, so the sidebar is assembled in one place instead of eight competing injections.

| Function | What it does |
|---|---|
| `register(array $definition)` | Adds a menu item (`id`, `parent`, `title`, `icon`, `route`, `permission`, `order`, `active_patterns`). **Throws on a duplicate ID**, so two packages cannot silently clobber each other's menu entry. |
| `tree()` | Builds the visible tree for the current user. For each item with a `permission`, it evaluates the requirement — superusers always pass; `admin` checks native access; `office_admin` checks the LocationProfile; `ict_officer` checks for a jurisdiction row; `company_admin` checks for a CompanyAdmin row; `storekeeper`/`approver` check `OfficeResponsibility` **at the current context location**; anything else is looked up in the `EffectivePermissionSet`. A list of permissions passes if **any** one matches. Items that fail are dropped, the survivors are nested by `parent`, and the result is sorted. |
| `sortMenuTree(array $tree)` | Recursively sorts every level by `order`. |

`MenuItem` is the value object (with `isActive()` matching the current URL against `active_patterns`).

### Admin screens & routes

Prefix `gov-store/admin/scope`, names `gov.scope.*`, superadmin only (`checkSuperadminAccess()` aborts 403 otherwise):

| Route | Method | Purpose |
|---|---|---|
| `GET /dashboard` | `dashboard()` | Overview of scoping health |
| `GET /config` · `POST /save-strategy` | `config()` / `saveStrategy()` | Set the strategy per reference type |
| `GET /mappings` | `explorer()` | The Boundary Explorer grid — every ownership mapping |
| `POST /mappings/store` · `POST /mappings/delete/{id}` | `storeMapping()` / `destroyMapping()` | Create and remove ownership mappings |
| `GET /reference-search` · `GET /tenant-search` | `referenceSearch()` / `tenantSearch()` | AJAX pickers for the mapping form |

### What the wall does **not** catch

Be honest about the edges. Global scopes and observers cover Eloquent. They do **not** cover:
- explicit `withoutGlobalScopes()` calls (used legitimately in several places above, but each one is a deliberate hole);
- raw `DB::table(...)` queries;
- background jobs and CLI commands — there is no HTTP request, so the context is never initialised and `isActive` stays false;
- the base policy's early `return true` for `admin` users.

Treat these four as the ongoing hardening surface.

---

## 2.6 `classification` — the national product catalogue (UNSPSC)

**Namespace:** `GovStore\Classification` · **Views:** `gov-classification::` · **Depends on:** tenant-scope

### What it is for

Two different problems, solved by one package.

**Problem 1 — a shared vocabulary.** If Office A calls it "Laptop", Office B calls it "Notebook Computer" and Office C calls it "পোর্টেবল কম্পিউটার", national reporting is impossible. The fix is **UNSPSC** — the United Nations Standard Products and Services Code, an international four-level classification (Segment → Family → Class → Commodity) where every item has a stable numeric code. The package imports that whole tree and lets you **map** a UNSPSC code to a Snipe-IT Category.

**Problem 2 — category governance.** Snipe-IT has one flat global list of categories. In a government with hundreds of organisations, that list becomes an unusable swamp within a year. So the package adds **adoption**: an office or ministry explicitly declares which categories it uses, and only those appear in its screens.

### The tables — note the deliberate split

**Reference data (read-only, overwritten by every import):**

| Table | What it holds |
|---|---|
| `gov_catalog_nodes` | The classification tree. `code` (unique — the immutable anchor), `parent_code`, `level` (1=Segment … 4=Commodity), `title_en`, `hid` (materialized path, same trick as geo-areas), `is_selectable`, `scheme`, `version`. |
| `gov_catalog_definitions` | The official English definition for each code. |

**Operational data (editable, never overwritten):**

| Table | What it holds |
|---|---|
| `gov_catalog_enrichments` | Local additions: Bangla title, Bangla definition, local notes. |
| `gov_catalog_synonyms` | Alternative names people actually search for. Typed `official` / `common` / `alias`, with a language. |
| `gov_catalog_snipe_mappings` | The bridge: one UNSPSC `code` → one Snipe-IT `category_id`. (Several codes may map to the same category.) |
| `gov_catalog_import_history` | Audit of every import: scheme, version, filename, rows, warnings, duration, user. |
| `gov_category_governance` | Per-category metadata: `governance_type` (`global` or `company`), which company created it, which user created it. |

> **Why the split matters.** Reference tables are wiped and re-imported when a new UNSPSC version is published. Operational tables must survive that. So operational rows **soft-link by `code` string**, never by database ID — a re-import that renumbers IDs cannot orphan your Bangla translations or your category mappings.

### `CatalogSearchService` — function by function

| Function | What it does |
|---|---|
| `search(string $query, string $scheme = 'UNSPSC', int $limit = 30)` | **Staged relevance search.** If the query is numeric it matches codes: exact code scores 100, code prefix scores 90. If it is text it matches titles and synonyms: exact title 80, title prefix 70, title contains 50, synonym-only hit 30. The score is computed in SQL with a `CASE` expression and used for ordering, so a search for "printer" puts *Printers* above *Printer Cables*. Eager-loads definition, enrichment, synonyms and Snipe mapping. |
| `browse(?string $parentCode, string $scheme)` | Children of one node, code order. Powers drill-down navigation. |
| `hasChildren(CatalogNode $node): bool` | Is this a branch or a leaf? Controls the expand arrow. |
| `ancestors(CatalogNode $node)` / `getAncestorsByHid(string $hid)` | Splits the `hid` path like `/10000000/10100000/10101500/` into codes and fetches all ancestors **in one indexed query**, ordered by level. This is the breadcrumb. |
| `findByCode(string $scheme, string $code): ?CatalogNode` | One node with every relationship loaded. |
| `getRoots(string $scheme)` | All level-1 Segments that are selectable. |
| `getDepth(CatalogNode $node): int` | The node's level. |
| `getSiblings(CatalogNode $node)` | Up to 10 nodes sharing the same parent, excluding itself — used for the "related items" context panel. |

### `CategoryAdoptionService` — function by function

This is the service that decides which office sees which category. It writes directly to `gov_tenant_scope_mappings`, which is why adoption instantly changes what `TenantScope` lets through.

| Function | What it does |
|---|---|
| `useCategory(int $categoryId, string $scopeType, int $scopeId)` | **Adopt.** Validates `scopeType` is `company` or `location`, then `updateOrInsert`s a mapping row with `is_active = true`. From this moment the category is visible to that scope — and *disappears from every office that has not adopted it*. |
| `stopUsingCategory(...)` | **Abandon.** First calls `hasActiveReferences()`; if the office still owns anything in that category, throws *"Governance Violation: Cannot abandon this category. Your office/organization currently owns active items mapped to it."* Otherwise deletes the mapping. |
| `archiveCategory(...)` | **Soft-hide.** Sets `is_active = false` instead of deleting. The relationship is remembered, but the `TenantScope` read filter (which requires `is_active = 1`) stops showing it. Reversible. |
| `restoreCategory(...)` | Sets `is_active = true` again. |
| `isUsedBy(...)` | Does a mapping exist for this category and scope? |
| `hasActiveReferences(...)` | The safety check behind abandonment. Picks the column (`company_id` or `location_id`) from the scope type, then looks for: any Asset at that scope whose model belongs to the category; any Consumable, Accessory, Component or License at that scope in the category. Returns `true` on the first hit. |
| `usageCount(int $categoryId): int` | How many companies have adopted this category — the "popularity" figure on the governance grid. |

### `CatalogCategoryCreator::provisionAndMap(...)` — one-click provisioning

Turns a UNSPSC code straight into a working, mapped, adopted Snipe-IT Category. Arguments: the UNSPSC code, the Snipe-IT category type, the governance type, the target scope type and ID, the creator's user ID, and an optional custom name.

Inside a transaction:
1. Look up the catalog node; the category name defaults to the node's English title unless a custom name was given.
2. **Self-healing duplicate check** — if a Snipe-IT Category with that exact name already exists, reuse it rather than failing. Otherwise create it with sensible defaults (`checkin_email = 0`, `require_acceptance = 0`, `use_default_eula = 0`) and, if Snipe-IT's validation rejects it, surface the real validation message.
3. Guard against a category with no ID (a real failure mode when validation half-succeeds).
4. `updateOrInsert` the UNSPSC → category mapping.
5. `updateOrCreate` the governance metadata: governance type, originating company (only when the scope is a company), creator.
6. **Auto-adopt** — if the governance type is not `global` and a scope ID was given, call `CategoryAdoptionService::useCategory()`.

The point of this method is that a storekeeper who cannot find "Laser Printers" in their catalogue can create it correctly, mapped to the international standard, and adopted by their office — in one click, without an administrator.

### `MyCatalogService` — function by function

Powers the "My Organization Category Catalog" screen.

| Function | What it does |
|---|---|
| `getLocalGrid(int $companyId, int $locationId, int $perPage)` | The adopted-categories grid. Uses `withoutGlobalScopes()` deliberately (the screen does its own, more precise, join-based filtering) and **inner-joins** `gov_tenant_scope_mappings` on "adopted by my company **OR** adopted by my location". Then left-joins governance metadata, the originating company name, and the UNSPSC code. Each row therefore shows: when it was adopted, whether the adoption is active, whether it came via company or location, its governance type, its owner, and its UNSPSC code. |
| `getLocalDetails(int $categoryId, string $scopeType, int $scopeId, int $locationId)` | The drill-down. Verifies the category really is adopted by this exact scope (returns `null` if not — this is the access check). Loads governance info, then counts **physical usage at this location**: assets (via their models), consumables, accessories, components, and licences. Also returns a `scopeNoun` (`"organization"` or `"office location"`) so the screen's wording matches the scope. |
| `getGlobalStandardsGrid(int $perPage)` | The opposite view: categories that have **no mapping at all** — the shared national standards everyone can see. Uses a `whereNotIn` subquery against the mappings table. |

### `CategoryGovernanceService` — function by function

The superadmin's national view.

| Function | What it does |
|---|---|
| `getMasterGrid(int $perPage)` | Every category in the system with: governance metadata, originating company, UNSPSC code, an **adoption count** subquery (how many scopes adopted it), and a **models count** subquery (how many asset models use it). One paginated query, no N+1. |
| `getCategoryDetails(int $categoryId)` | Full drill-down: the category, its governance record (with company and creator names), its UNSPSC mapping (with the node's title and hierarchy path), and global counts of adoptions, assets, models, consumables, accessories, components and licences. |

### The import pipeline

**`CatalogDatasetLocator::findBundle(string $scheme, string $version): array`** — resolves the three pre-compiled CSV files (`compiled_nodes.csv`, `compiled_definitions.csv`, `compiled_synonyms.csv`) in `src/database/data`, and throws a clear error naming the missing file if any is absent.

**`CatalogImportCoordinator::execute(array $paths, string $scheme, string $version, int $userId): array`** — the transaction wrapper. Starts a transaction, runs the three importers in order (nodes → definitions → synonyms), measures elapsed time, writes a `gov_catalog_import_history` audit row, commits. On any exception it rolls back and rethrows, so a half-imported catalogue is impossible. Returns counts and duration.

**The three importers**, each written for volume (UNSPSC is tens of thousands of rows):

| Importer | Method |
|---|---|
| `NodeImporter::import($path, $scheme, $version)` | Streams the CSV row by row, buffering 500 rows at a time, then `upsert`s on `code` updating everything else. Chunked at 500 to keep the SQL parameter count low. Frees the buffer immediately to bound memory. |
| `DefinitionImporter::import($path)` | Same pattern, chunk 500, upsert on `code`. |
| `SynonymImporter::import($path)` | First checks `hasDataRows()` (opens the file, skips the header, peeks at row 1) and returns 0 if the file is effectively empty — this stops an empty file from wiping existing synonyms. Then deletes only English `common`/`acronym` synonyms (leaving hand-curated Bangla and official ones untouched) and bulk-inserts in chunks of 1000. |

`CatalogImportService` is the older single-file implementation, retained alongside the coordinator. It adds `analyzeDiff($metaPath, $scheme)` — a dry-run that reports what *would* change — and `fastImport()`/`importTree()`/`importMetadata()` internals.

**`ImportPerformanceGuard` middleware** wraps the validate and execute routes to raise time and memory limits for these long-running operations.

### Controllers & routes

**Global master catalogue** — prefix `admin/catalog`, `web` + `auth`, deliberately **outside** the tenant context (this is national reference data):

| Route | Controller method | Purpose |
|---|---|---|
| `GET /` | `CatalogDashboardController@index` | Catalogue workspace home |
| `GET /search` | `CatalogSearchController@index` | The human-centred search screen |
| `GET /search/ajax` · `/browse/ajax` · `/ancestors/ajax` · `/context/ajax` | `CatalogSearchController` | Autocomplete, drill-down, breadcrumb, and the context panel (ancestors + siblings) |
| `GET /mapping` · `/mapping/{id}` | `@showMapping` | The per-node mapping editor |
| `GET /snipe-categories/ajax` · `POST /mapping/save` | `@searchSnipeCategories` / `@saveMapping` | Pick a Snipe-IT category and bind it to the code |
| `GET /import` · `POST /import/validate` · `POST /import/execute` | `CatalogAdminController` | The MDM import wizard: preview differences, then commit |
| `GET /external` · `GET /history` | `CatalogAdminController` | External mapping grid and import history |
| `POST /adoption/adopt` · `/abandon` · `/provision` | `CategoryAdoptionController` | Adopt, abandon, or one-click-provision a category |
| `GET /governance` · `/governance/{id}` | `CategoryGovernanceController` | The national governance centre (superadmin) |

**Operational catalogue** — prefix `gov-store/operations/catalog`, **wrapped in `InitializeTenantContext`** because everything here is scoped to your office:

| Route | Controller method | Purpose |
|---|---|---|
| `GET /` · `GET /{id}` | `MyCatalogController@index` / `@show` | My organisation's adopted categories, and their detail |
| `POST /archive` · `POST /restore` | `@archive` / `@restore` | Soft-archive lifecycle |

`CategoryAdoptionController::resolveScope(TenantContext)` is the small helper both controllers use to work out whether the current user is acting at company or location scope, and with which ID.

`CatalogSearchDropdown` is a Livewire component providing a live-search dropdown that can be embedded in other screens (`updatedQuery()` re-runs the search as you type; `selectItem($code)` picks a node).

---

## 2.7 `custom-requests` — the internal shop (request → approve → issue)

**Namespace:** `GovStore\CustomRequests` · **Views:** `govstore::` · **Depends on:** office-membership, store-operations

### What it is for

The employee-facing storefront. An employee browses a catalogue, fills a basket, submits it, it gets approved by the right people, and a storekeeper physically hands the items over — with real Snipe-IT inventory and the official stock ledger both updated.

### The tables

| Table | What it holds |
|---|---|
| `draft_baskets` | One open basket per user. `user_id`, `status` (`draft`), optional `expires_at`. |
| `draft_basket_items` | Lines in the basket: polymorphic `requested_type` + `requested_id`, and `requested_qty`. |
| `custom_service_requests` | The submitted **request document**: `request_number` (auto `SR-YYYY-NNNNNN`), `requested_by`, `request_type`, `purpose`, `justification`, `required_by_date`, `delivery_location_id`, `cost_center`, `resolved_policy`, `assigned_approver_id`, plus **two independent state machines** — `approval_status` and `fulfillment_status` — and timestamps for submitted / approved / closed, with soft deletes. |
| `custom_service_request_items` | The line items. Polymorphic **requested** item *and* a separate polymorphic **fulfilled** item (so a substitution is recorded, not hidden). The quantity ladder `requested_qty → approved_qty → reserved_qty → issued_qty`, plus per-line `line_approval_status` and `line_fulfillment_status`. |
| `custom_service_request_events` | An **append-only timeline**. Every state change writes a row with an `event_type` and a JSON `details` blob. Nothing here is ever updated or deleted — it is the story of the request. |
| `gov_approval_policies` | Polymorphic policy overrides: `(target_type, target_id) → policy_name`. |
| `custom_item_requests` | **Legacy.** The original single-item request table, superseded by the document model above. Model `ItemRequest`, service `RequestService`. |

> **The quantity ladder is the key design idea.** A line does not have "a quantity" — it has four. You asked for 5, the approver allowed 3, 3 were reserved, 2 have actually been handed over. Every stage is preserved, so the audit trail can answer "what was cut, by whom, and when" long after the fact.

> **Two independent state machines.** `approval_status` (`draft → pending_primary → pending_final → approved / partially_approved / rejected → closed`) and `fulfillment_status` (`unstarted → partially_issued → issued / closed / cannot_fulfill`). They are separate because a request can be fully approved but only half-issued, and both facts matter.

### The adapter pattern — how the shop talks to Snipe-IT

A request can target an Asset Model, an Accessory, a Consumable — or, in legacy records, a specific Asset. Those are four different core models with four different checkout mechanics. Rather than a pile of `if` statements everywhere, each gets an **adapter** implementing `RequestableInterface`:

```php
interface RequestableInterface {
    public function getModel();
    public function getDisplayName(): string;
    public function getType(): string;
    public function getAvailableQuantity(): int;
    public function checkout(User $targetUser, User $adminUser, int $quantity = 1, string $notes = ''): bool;
}
```

| Adapter | `getType()` | `getAvailableQuantity()` |
|---|---|---|
| `AssetAdapter` (legacy) | `Asset` | 1 if unassigned, else 0 |
| `AssetModelAdapter` (current) | `Hardware` | count of unassigned physical assets of that model |
| `AccessoryAdapter` | `Accessory` | `numRemaining()` |
| `ConsumableAdapter` | `Consumable` | `numRemaining()` |

**`RequestableFactory::make(string $type, int $id)`** normalises the type string (it accepts `asset`, `assetmodel`, `asset_model`, `accessory`, `consumable` — full class names, basenames, or morph keys) and returns the right adapter, or throws for anything unsupported.

The provider also registers a `Relation::morphMap` (`asset`, `assetmodel`, `asset_model`, `accessory`, `consumable`, `license`) so the database stores short, readable keys instead of fully-qualified PHP class names that would break if a namespace ever changed.

### `PolicyService::resolvePolicy(string $type, int $id): string`

Every catalogue item resolves to exactly one approval policy, checked in order:

1. **Direct item override** — is there a `gov_approval_policies` row for this exact item?
2. **Category inheritance** — is there one for the item's category? (`getCategoryId()` resolves the category differently per type: an Asset goes via its model; an Accessory and a Consumable have the column directly.)
3. **Global default** → `PRIMARY_ONLY`.

| Policy | Meaning |
|---|---|
| `AUTO_APPROVE` | No human needed. Approved the instant it is submitted. (Pens, paper.) |
| `PRIMARY_ONLY` | One approver signs. (Most things.) |
| `PRIMARY_AND_FINAL` | Two approvers must sign, in order. (Expensive or sensitive items.) |

### `BasketService` — function by function

| Function | What it does |
|---|---|
| `getOrCreateDraftBasket(int $userId): DraftBasket` | Returns the user's open basket, creating one if needed (`DraftBasket::getOrCreateForUser`). |
| `addItem(int $userId, string $itemType, int $itemId, int $qty = 1)` | If that exact item is already in the basket, **increment** the existing line by `qty`; otherwise create a new `BasketItem`. |
| `updateItemQty(int $userId, int $itemId, int $qty)` | Rejects `qty < 1` with a translated message. Loads the user's draft basket and the line (both `firstOrFail`, so you cannot touch someone else's basket) and updates the quantity. |
| `removeItem(int $userId, int $itemId)` | Loads the user's draft basket and line, deletes the line. |

**`submitBasket($userId, array $metadata): array` — the clever one.**

1. Reject an empty basket. Reject a requester with no `location_id` (there would be nowhere to deliver to and no approver to find).
2. **Group every draft line by its resolved approval policy.** This is the heart of the method.
3. **Validate approvers exist.** If any group needs a human, check that the requester's office has at least one `primary_approver` or `final_approver` in `gov_office_responsibilities`. If not, refuse — otherwise the request would vanish into a queue nobody can see.
4. Inside a **transaction**, first work out whether the requester themselves holds the primary or final approver duty at this office (the self-approval problem), then for each policy group:
   - Decide the starting `approval_status`:
     - `AUTO_APPROVE` → born `approved`, `approved_at = now()`.
     - `PRIMARY_ONLY` and the requester **is** the primary approver → `approved` immediately (they would only be approving their own request).
     - `PRIMARY_AND_FINAL` and the requester **is** the final approver → `approved`.
     - `PRIMARY_AND_FINAL` and the requester **is** the primary approver → skip straight to `pending_final`.
     - Otherwise → `pending_primary`.
   - Create a **separate `ServiceRequest` document** for that group, with `assigned_approver_id = null`. That null is deliberate: this is a **shared queue** model — any approver at the office can pick it up, so nothing stalls when one person is on leave.
   - Copy that group's lines in as `RequestItem`s. If the parent was born approved, each line starts `approved` with `approved_qty = requested_qty` and fulfilment `waiting`; otherwise `pending` and `unstarted`.
5. Delete the basket lines and the basket itself.
6. Return the list of created requests.

**Why split by policy?** Because a basket containing a box of pens (`AUTO_APPROVE`) and a laptop (`PRIMARY_AND_FINAL`) should not make the pens wait two weeks for the laptop's second signature. Each document then flows at its own natural speed.

### `ApprovalService::processDecision(ServiceRequest $request, User $admin, array $itemDecisions): ServiceRequest`

The approver's decision handler. Decisions arrive **per line**, keyed by line ID, each carrying a status, a quantity and optional notes.

1. Refuse unless the request is currently in `submitted`, `under_review`, `pending_primary` or `pending_final`.
2. Inside a transaction, work out whether this is the **primary** review or the **final** review, then walk every line:
   - **Approved, primary review, `PRIMARY_ONLY`** → set `approved_qty`, line `approved`, fulfilment `waiting`. Count it approved.
   - **Approved, primary review, `PRIMARY_AND_FINAL`** → record `approved_qty` only. The line stays pending; count it as awaiting final.
   - **Approved, final review** → set `approved_qty = min(finalQty, existing approved_qty)` — **the final approver can only reduce, never increase, what the primary allowed.** Line `approved`, fulfilment `waiting`.
   - **Rejected** → `approved_qty = 0`, line `rejected`, fulfilment `cancelled`, and a `line_rejected` timeline event carrying the reason.
   - A line with no decision at all throws — the approver must decide on everything.
3. **Roll the parent document up:**
   - If this was the primary review of a two-step policy and lines are waiting for final → `pending_final`. **But first, the self-approval check:** if the *requester* is themselves the final approver at this office, short-circuit — promote every pending line to approved/waiting and mark the parent `approved`, because there is nobody meaningful left to sign.
   - If nothing at all was approved → `rejected`, fulfilment `closed`, `closed_at` stamped.
   - If some lines were rejected but others approved → `partially_approved`.
   - Otherwise → `approved`.
4. Save the parent with `assigned_approver_id = null` (shared queue), and record `approved_by` / `approved_at` when the outcome was approved or partially approved.
5. Write one timeline event named after the outcome.

### `FulfillmentService` — function by function

This is where the storefront finally touches real inventory, and it does so **two different ways** depending on what the item is.

**`issueItems(ServiceRequest $request, User $storekeeper, array $issuePayload, array $substitutions = []): ServiceRequest`**

Refuses if the request is already `closed` or `cannot_fulfill`. Then, inside a transaction, for every line whose approval status is `approved`:

**Step 1 — substitution.** If the storekeeper picked a different item for this line, build the alternative's adapter, record `fulfilled_type` / `fulfilled_id` on the line (leaving `requested_*` untouched so the original ask is preserved), and write an `item_substituted` timeline event naming the replacement.

**Step 2 — branch by item kind.**

*Scenario A — Asset Models (serialised hardware).* The payload contains an **array of specific asset IDs** the storekeeper scanned or picked. For each: verify the asset exists and is not already assigned; set `assigned_to` to the requester, `assigned_type` to `User`, and `location_id` to the requester's office; save; call Snipe-IT's native `logCheckout()` with the request number so the asset's own history shows where it went. Then update `issued_qty` and set the line to `issued` or `partially_issued`, and write an `item_issued` event. Over-issuing beyond the approved quantity throws.

*Scenario B — bulk items (consumables, accessories, components).* These are **not** issued one by one. They are packed into a ledger payload — type, ID, quantity, line ID — and handled after the loop.

**Step 3 — post the bulk items to the official ledger.** `$this->stockIssuer->issueSystemStock($ledgerPayload, $requesterId, $request)` hands off to the `store-operations` package through the `StockIssuingServiceInterface`. That call creates a real **Goods Issue document**, writes immutable ledger movements, and returns a map of line ID → generated GI document number. For each line the service then: calls the adapter's `checkout()` so Snipe-IT's own history is also written (with the GI number in the note), updates `issued_qty`, sets the line status, and writes an `item_issued` event carrying the GI reference.

> **Why both?** The ledger is the legally meaningful record with running balances. Snipe-IT's native tables are what the rest of the application reads. Writing to both keeps the official register correct *and* the everyday UI accurate.

**Step 4 — close the parent if finished.** If every approved line is fully issued, set `approval_status = 'closed'`, `fulfillment_status = 'issued'`, stamp `closed_at`, and write a `closed` event. Otherwise, if anything at all has been issued, set `fulfillment_status = 'partially_issued'`.

**`forceClose(ServiceRequest $request, User $storekeeper, string $reason = null)`** — the escape hatch for "we simply do not have it". Cancels every approved-but-unissued line, closes both state machines, stamps `closed_at`, and writes a `closed` event recording the reason.

### `CatalogService::getAvailableItems(): array`

Builds the browsable catalogue. Five passes, and only items with availability greater than zero are included:

1. **Asset Models** — for each model, count physical assets that are unassigned **and** flagged `requestable`. This is the "template shift": you browse models, not individual machines. Type key `asset_model`.
2. **Accessories** — `qty − users_count`.
3. **Consumables** — `qty − users_count`.
4. **Components** — `qty − assets_count`.
5. **Licenses** — `seats − assigned_seats_count`.

Each entry carries id, type, name, category, image URL, available quantity, creation timestamp (for "newest" sorting), and a small details list. Results are sorted by name and cast to objects for Blade.

Because every query runs through the global scopes, **the catalogue is already limited to the user's office** — no filtering code is needed here at all.

### `RequestService::submitRequest(...)` — legacy

The old single-item path. Prevents a duplicate pending request for the same item by the same user, then creates an `ItemRequest`. On approval, an `ItemApproved` event fires and the `ProcessItemCheckout` listener calls the adapter's `checkout()`. Retained for historical records.

### Controllers & routes

Prefix `gov-requests`, names `gov.requests.*`:

| Area | Route | Controller method | Notes |
|---|---|---|---|
| Catalogue | `GET /catalog` · `GET /catalog/search` | `GovRequestController@catalog` / `@search` | Search also feeds the substitution picker |
| My requests | `GET /my-requests` | `GovRequestController@index` | The employee's own tracking screen |
| Basket | `GET /basket`, `POST /basket/add · update · remove/{id} · submit` | `BasketController` | Thin wrappers over `BasketService` |
| Approval | `GET /admin` · `GET /admin/{id}` · `POST /admin/{id}/process` | `GovApprovalController` | See below |
| Fulfilment | `GET /fulfillment` · `/{id}` · `POST /{id}/issue` · `POST /{id}/close` | `GovFulfillmentController` | See below |
| Register | `GET /fulfillment-register` · `/{id}` | `FulfillmentRegisterController` | The completed-requests archive |
| Settings | `GET/POST /admin/settings/policies*` | `GovApprovalController@policiesIndex` / `@policiesStore` | Per-category approval policy (superadmin) |

**`GovApprovalController`**
- `checkApproverAccess()` — superusers and admins pass; otherwise the user must hold `primary_approver` or `final_approver` somewhere, or 403.
- `index()` — two lists: **pending** (statuses `submitted`, `under_review`, `pending_primary`, `pending_final`) and **processed** (everything else). For non-superusers the pending list is filtered to `delivery_location_id IN (offices where I am an approver)` — this is the shared queue — while the processed list is filtered to what *they personally* approved, so history stays meaningful.
- `show($id)` — loads the request with requester, items, requested targets and the full event timeline. **Side effect:** if the status is still `submitted`, it flips to `under_review` and writes an `under_review` event, so colleagues can see someone has picked it up.
- `process($id)` — delegates to `ApprovalService::processDecision()`, catching exceptions into a flash message.
- `policiesIndex()` / `policiesStore()` — superadmin-only screen mapping each Snipe-IT Category to an approval policy name.

**`GovFulfillmentController`**
- `checkStorekeeperAccess()` — superusers and admins pass; otherwise a `storekeeper` responsibility is required, or 403.
- `index()` — requests that are `approved` or `partially_approved` and not yet `closed`/`issued`, filtered to the offices where this user is storekeeper, **oldest approval first** (a simple FIFO fairness rule).
- `show($id)` — loads the request, then for every approved Asset Model line **pre-loads the physically available units**: assets of that model with no assignee, restricted to the storekeeper's own offices. This is what fills the barcode-scanner picker so the storekeeper chooses actual serial numbers.
- `process($id)` — validates that an `issue` array is present, then calls `FulfillmentService::issueItems()` with the issue payload and any substitutions.
- `close($id)` — calls `forceClose()` with the typed reason.

**`FulfillmentRegisterController`**
- `checkAccess()` — superusers and admins pass; otherwise any of `storekeeper`, `primary_approver`, `final_approver`, `office_admin`.
- `index()` — every request whose fulfilment status is `issued`, `partially_issued`, `closed` or `cannot_fulfill`, newest-closed first, filtered to the user's offices.
- `show($id)` — the request plus **all Goods Issue documents generated for it**, found by `reference_type = ServiceRequest::class` and `reference_id = $id`. This is the join that connects a request to its official store paperwork. Asset Model lines have no GI document (they are native Snipe-IT checkouts), and the query simply returns nothing for them.

---

## 2.8 `store-operations` — the government store register

**Namespace:** `GovStore\StoreOperations` · **Views:** `storeops::` · **Depends on:** tenant-scope

### What it is for

A government store does not run on informal checkouts. It runs on **documents** and a **register**:

- A **Goods Receipt (GRN)** records stock coming in, against a supplier challan.
- A **Goods Issue (GI)** records stock going out.
- The **Kardex** (stock register) shows every movement of every item with a **running balance**, and is never edited.

On top of that, government rules about *what data must be captured* differ by product: a laptop needs a unique serial number and a warranty period; a box of pens needs only a quantity. Hard-coding those rules would mean a code change for every new policy. So this package includes a **rules engine** administrators configure through a screen.

This is the largest and most sophisticated package in the system.

### The three big ideas

**1 — The document engine.** One generic `gov_documents` table serves all document types (`receipt`, `issue`, …), with generic line items and a generic metadata table. Adding a new document type does not mean new tables.

**2 — The immutable ledger.** `gov_inventory_movements` is append-only. Each row records direction (`IN`/`OUT`), quantity, and — crucially — `balance_after`. Nothing is ever updated or deleted. Snipe-IT's own `qty` columns become a *projection* kept in step by an event listener; the ledger is the truth.

**3 — Capabilities (the plugin system).** Each rule is a small class implementing one interface. Policies are built by composing capabilities, policies are assigned to targets, and at document time the system **compiles** all applicable policies into a single frozen snapshot stored on the document.

### The tables

**Document engine:**

| Table | What it holds |
|---|---|
| `gov_documents` | Generic document header. UUID id, unique `document_number`, `type`, `status`, **`compiled_profile_snapshot` (JSON)**, `reference_no` / `reference_date` / `purchase_type`, plus `company_id`, `location_id`, `created_by`. |
| `gov_document_items` | Generic line: UUID id, `document_id`, polymorphic `product_type` + `product_id`, `quantity`, `unit_cost`. |
| `gov_document_item_meta` | Key-value-per-row metadata (an EAV table): `field_key`, `value`, and **`row_index`** — so a line for 5 laptops stores 5 serial numbers, indexed 0–4. |
| `gov_document_references` | Attached legal references: challans, Nothi numbers, purchase orders. |
| `gov_document_attachments` | Uploaded scans and PDFs. |
| `gov_document_timelines` | The state audit trail: which state, who, when, with notes. |
| `gov_asset_registrations` | The bridge from a receipt line to the physical Snipe-IT assets it created: `intake_item_id`, `asset_id`, `asset_tag`, `serial_number`. |

**Ledger and legacy typed documents:**

| Table | What it holds |
|---|---|
| `gov_inventory_movements` | **The kardex.** Polymorphic `stockable_type` + `stockable_id`, `movement_type` (`IN`/`OUT`), `quantity`, `balance_after`, polymorphic document reference, company, location, creator, `created_at` only (**no `updated_at` — immutability is enforced in the schema**). |
| `gov_goods_receipts` / `_items` | The original typed receipt tables. |
| `gov_goods_issues` / `_items` | Typed issue tables — **still actively used** for system-generated issues from the request workflow. |
| `gov_stock_adjustments` / `_items` | Corrections: physical count, damage, loss, expiry. |

**Policy engine:**

| Table | What it holds |
|---|---|
| `gov_profiles` | A **policy** (the code calls them "GPOs"). `name`, `scope` (`GLOBAL`/`COMPANY`/`LOCATION`), optional polymorphic owner, `status` (`DRAFT`/`PUBLISHED`/`ARCHIVED`), `version`, and optional `company_id`/`location_id`. |
| `gov_profile_capabilities` | Which capabilities a policy carries: `capability_code`, **`behavior`** (`ENFORCE` / `DISABLE` / `INHERIT`), and a JSON `config_payload`. |
| `gov_profile_assignments` | Which targets a policy applies to: polymorphic `target_type` + `target_id`, `scope_level`, `scope_id`, `assigned_by`, and **`effective_from` / `effective_to`** — assignments are time-boxed, so "expiring" one is a soft operation that preserves history. |

> **Migration history, so you are not confused by the files.** The profile tables were rebuilt three times: first as a recursive parent/child tree (`2024_03_02`), then as a four-layer Global→MajorType→Category→Model hierarchy (`2024_03_04`), and finally (`2024_03_08`) as the current **category-agnostic policy catalogue** where a policy is an independent document that gets *assigned* to targets. `2024_04_01` then added multi-tenancy columns and the three-state `behavior` toggle. Only the final shape is live.

### The capability system

**`CapabilityInterface`** — every rule implements four methods:

```php
getRequirements(array $config = []): array   // which metadata fields must be filled
validate(array $data, array $config = []): array  // returns error messages, empty = OK
execute(object $item, array $config = []): void   // what to do when the document is posted
renderUI(object $item = null, array $config = []): string  // extra HTML for the grid row
```

**`CapabilityRegistry`** — the master list mapping string codes to classes, plus a plain-English dictionary used by the admin UI.

| Code | Name | Group | What it actually does |
|---|---|---|---|
| `require_quantity` | Quantity Required | Receiving Validation | `validate()` insists on an integer `qty ≥ 1`. No metadata fields, no execution — quantity lives on the line itself. |
| `require_serial` | Require Serial Numbers | Identification | Declares the field `serial_number`. `validate()` requires a serial for **every** unit and enforces `distinct`, so a double-scan is rejected. `renderUI()` renders an expanding sub-grid, one row per unit. |
| `require_warranty` | Capture Warranty Period | Information Requirements | Declares `warranty_months` with rules `required|integer|min:0`. |
| `post_inventory` | Post to Kardex Ledger | Inventory Automation | `execute()` posts a movement — direction `IN` for a receipt, `OUT` otherwise — through `LedgerPostingService`. |
| `create_assets` | Create Physical Assets | Execution Automation | `execute()` materialises real Snipe-IT assets. See below. |
| `adjust_inventory` | *(stock adjustments)* | — | Reads a per-line `adjustment_direction` from metadata (default `IN`) and posts accordingly. `renderUI()` renders the direction chooser. |
| `transfer_inventory` | *(inter-office transfer)* | — | Requires `destination_location_id`. `execute()` posts **two** movements: an `OUT` at the source office and an `IN` at the destination — genuine double-entry. Throws if no destination was chosen. |

**`CapabilityRegistry` methods:** `make(string $code)` resolves the class through the container and throws a clear error for an unregistered code; `getClass(string $code)`; `getRegistry()`; `getDictionary()`.

**`CreateAssetsCapability::execute()` — the most consequential single method in the package.** For a line with quantity N:

1. Fetch the line's metadata grouped by `row_index`.
2. Loop `range(0, N-1)`. For each unit:
   - Read `serial_number` and `asset_tag` from that row's metadata.
   - If no serial was captured, generate a traceable one: `SN-AUTO-<document number>-<product id>-<row>`.
   - If no tag was captured, generate `TAG-AUTO-<document number>-<uniqid>`.
   - Create a **native Snipe-IT `Asset`** with the line's product as `model_id`, the serial and tag, a status (from config, defaulting to "Ready to Deploy"), and the document's company and location. Throw if the save fails.
   - Insert a row into `gov_asset_registrations` linking that physical asset back to the receipt line — this is the traceability chain from a supplier challan to a specific serial number.
   - Call Snipe-IT's own `logCheckout()` so the asset's history shows *"Received under dynamic GRN: …"*.

**`ProfileCompilerService::compileItem()` — the merge engine.** For one product it resolves the **applicable policy layers in precedence order, lowest first**, and merges them:

```
GLOBAL  →  COMPANY  →  LOCATION  →  CATEGORY  →  MODEL
(weakest)                                        (strongest)
```

For each layer it looks for an active `ProfileAssignment` (active = `effective_from` in the past and `effective_to` null or in the future). Then for each capability in that layer's policy:

- **`INHERIT`** → skip. Whatever a lower layer decided stands.
- **`DISABLE`** → record `enforced = false`, keeping the trace (`source_policy`, `layer`) so the admin simulator can explain *why* a rule is off.
- **`ENFORCE`** → record `enforced = true` and **merge** the config on top of any config from a lower layer.

Results are cached per company+location+product for the request. Related methods: `compileDocument(Document)` compiles every line and returns `['items' => [ 'type_id' => [...] ]]`; `resolveItemHierarchy()` works out whether the product is an Asset Model and finds its Snipe-IT category; `getActiveAssignment()` performs the time-boxed lookup and short-circuits safely on a null target ID (which happens for superadmins with no company or location).

**`CompiledProfile` (DTO)** — reads the frozen snapshot back safely.
- `getRawCapabilities($type, $id)` — everything including disabled rules and trace metadata, defensively cast to an array so a corrupted snapshot cannot crash a `foreach`.
- `getCapabilitiesForProduct($type, $id)` — only enforced rules, stripped down to their config. Handles both the v2 format (with the `enforced` wrapper) and legacy v1 snapshots that predate it.
- `getPresentationCapabilities(...)` — normalised for the Blade workspace, so disabled rules render no UI.
- `getPipelineCapabilities(...)` — alias used by the posting pipeline.

> **Why freeze a snapshot onto the document at all?** Because policies change. If an administrator tightens the serial-number rule next month, a receipt drafted today must still post under the rules that were in force when it was drafted. The frozen `compiled_profile_snapshot` guarantees that.

### The document lifecycle

```
DRAFT  →  READY  →  POSTED        (and CANCELLED, before posting)
```

`DocumentState` is a PHP enum. `HasDocumentState` trait provides:
- `getState()` — maps the legacy string `SUBMITTED` to `POSTED`, and falls back to `DRAFT` for any unknown value rather than crashing.
- `transitionTo(DocumentState $new, int $userId, string $notes)` — **refuses to alter a POSTED or CANCELLED document**, updates the status, and writes a `gov_document_timelines` row.
- `timelines()` — the polymorphic audit trail.

### The services — function by function

**`DocumentNumberService::generate(string $prefix, string $table, string $column): string`**
Produces `GR-2026-000001`, `GI-2026-000042`. Finds the highest existing number with this year's prefix using the raw `DB` facade (deliberately bypassing Eloquent scopes — the sequence must be globally unique, not per-office), increments, and zero-pads to six digits.

**`DocumentLineItemManager::processLines(array $rawLines, string $direction = 'IN'): array`**
Normalises grid input. Lowercases the type to its basename, casts IDs and quantities, drops any line with `qty <= 0`, and **auto-merges duplicates** — if the same product appears twice, quantities are summed and the higher unit cost wins. Then, for `OUT` documents only, it builds each item's stock adapter and refuses if available stock is less than the requested quantity, with a message naming the item and both numbers.

**`LedgerPostingService::postMovement(...)` — the exclusive gateway to the ledger.**
Every write to `gov_inventory_movements` goes through this one method. Nothing else may insert there.

1. Reject a quantity of zero or less; reject any direction that is not `IN` or `OUT`.
2. **Read the latest `balance_after` for this stockable with `lockForUpdate()`** — a pessimistic database lock. This is what makes concurrent postings safe: two storekeepers issuing the same consumable at the same instant cannot both read the same balance.
3. Compute the new balance: add for `IN`, subtract for `OUT`.
4. **Refuse to go negative** on an `OUT` — *"Ledger violation: Insufficient stock…"*.
5. Insert the immutable movement row with the computed `balance_after`, the document reference, company, location and user.
6. Fire `InventoryMovementCreated`.

**`InventoryLedgerService::getKardexFor(string $modelClass, int $id, int $limit = 100)`**
Fetches the chronological movement history for one item. It queries **both** the fully-qualified class name and the short morph key, because older rows were written with full class paths and newer ones use morph keys — merging them means the stock card shows unbroken history across that change.

**`ProductResolver::search(string $term, ?StockableType $type, int $limit)`**
One search across Consumables, Accessories, Components and Asset Models. Matches on `name`, and additionally on `item_no` or `model_number` **only if those columns actually exist** on that table (checked with `Schema::hasColumn`). Stock is calculated differently per type: counter-based items read their `qty`; **Asset Models count live rows in the `assets` table**, because a model has no quantity of its own. Results are unified, sorted by name, and returned with a type label.

**`GoodsReceiptService`**
- `saveDraft($headerData, $rawLines, $userId, ?Document $document)` — inside a transaction: refuse to edit a non-draft; create the header (generating a `GR-` number, setting type `receipt`, status `DRAFT`, and stamping the tenant's company and location) or update it; normalise the lines; **replace** all line items; reload the relationship; then **compile the profile snapshot and store it on the document**.
- `post($document, $userId)` — delegates entirely to `PostingPipelineManager::materialize()`.

**`PostingPipelineManager::materialize(Document $document, int $userId): void`**
The finalisation. Refuses an already-posted document, refuses an empty one, and refuses one with no frozen snapshot. Then, inside a **transaction**:
1. Lock the status to `POSTED`.
2. For each line, read its enforced capabilities out of the snapshot, instantiate each through `CapabilityRegistry::make()`, and call `execute()`. This is where the ledger gets written and physical assets get created — **driven entirely by configuration, not by code branches**.
3. Write a final `POSTED` timeline entry.

Because it is one transaction, a document either posts completely or not at all.

**`GoodsIssueService`**
- `saveDraft(...)` — same shape as the receipt service but with direction `OUT` (so the stock check runs) and a `GI-` number. Empty drafts are allowed on purpose, so a storekeeper can open a blank document and fill it in.
- `post($issue, $userId)` — refuses an already-posted or empty document, then inside a transaction posts an `OUT` movement per line via `LedgerPostingService` and transitions to `POSTED`. Note this typed path posts directly rather than through the capability pipeline.

**`SystemGoodsIssueService::issueSystemStock(array $items, int $issuedToUserId, $referenceDocument): array`**
The implementation of `StockIssuingServiceInterface`, and **the bridge from the request workflow into the store register**.

- **Classification phase.** For each incoming item, try `StockableType::fromString()`. If it succeeds, build the adapter, check available stock (throwing a translated "insufficient stock" message with both numbers if short), and add it to the ledger list. If it throws *"Unsupported stockable type"* — which is what happens for Assets and Licenses — **swallow the error and skip the item**, because those follow Snipe-IT's own native checkout path instead. Any other exception is rethrown.
- If nothing is ledger-eligible, return an empty array; the caller carries on.
- **Transaction phase.** Generate a `GI-` number; create a `GoodsIssue` with `issue_type = 'SYSTEM_FULFILLMENT'`, the recipient, and a polymorphic reference back to the originating Service Request; create the issue items; compute each new balance; insert the movement; fire `InventoryMovementCreated`. Returns a map of request line ID → GI number so the caller can show the reference in its timeline.

**`DocumentValidationService`**
- `validateDocument(Document $document, array $requestData): array` — for each line, pulls its capabilities from the snapshot, extracts that line's submitted input (parsing the grid's composed `type_id` keys), and calls each capability's `validate()`. Errors are collected keyed by product name. Written very defensively, because a snapshot from an older engine version may have a different shape.
- `evaluateDocument(Document $document): array` — builds the **live completion checklist** shown beside the grid. Counts the header requirements (reference number, reference date), then per line: is the quantity greater than zero, and for each required metadata field, are there **at least as many filled values as the line quantity** (5 laptops need 5 serials). Returns `is_valid`, a `progress` percentage, and the full checklist with pass/fail per item. This is the server-side truth — the JavaScript progress bar merely displays it.

### Events, listeners and observers

**`InventoryMovementCreated`** fires on every ledger write, and two listeners react independently (single-responsibility, so one failing does not obscure the other):

- **`UpdateSnipeQuantity`** — the projection engine. Builds the item's adapter and increments or decrements Snipe-IT's own `qty` column. On failure it logs `Projection Engine Failure` at **critical** and **rethrows**, deliberately aborting the surrounding transaction: a ledger that disagrees with the projection is worse than a failed posting.
- **`WriteNativeAuditLogs`** — writes a Snipe-IT `Actionlog` entry so the movement shows up in the ordinary history screens, with a readable note naming the document and the resulting balance. Failures here are logged at `error` and **swallowed** — a missing audit line must not roll back a valid stock movement.

**`SnipeCategoryObserver`** — when a Snipe-IT Category is created, automatically assign the matching global policy: *System Default Asset Standard* for `category_type = 'asset'`, *System Default Consumable Standard* otherwise. Wrapped in try/catch and logged, so a policy problem can never block category creation.

**`StockableFactory::make(StockableType|string $type, int $id)`** returns the `ConsumableAdapter`, `AccessoryAdapter` or `ComponentAdapter`. **`StockableType::fromString()`** is a defensive parser accepting `App\Models\Consumable`, `Consumable`, `consumable`, `assetmodel` and `asset_model`, and throwing *"Unsupported stockable type"* for anything else — which is precisely the signal `SystemGoodsIssueService` uses to route Assets away from the ledger.

Each adapter implements `StockableInterface`: `getCurrentQuantity()`, `incrementQuantity()`, `decrementQuantity()`, `getDisplayName()`.

### Controllers & routes

Prefix `gov-store/operations`, wrapped in `web`, `auth` and `InitializeTenantContext`.

**`StockRegisterController`**
- `index()` — the stock register dashboard (route `storeops.register.index`).
- `kardex($type, $id)` — the stock card for one item, from `InventoryLedgerService::getKardexFor()`. Also surfaced as a **tab on the native Snipe-IT consumable/accessory/component pages**, via the `TabRegistry`.

**`DocumentWorkspaceController`** — the unified document editor.

| Method | What it does |
|---|---|
| `hub()` | Lists all documents of every type, newest first, paginated. |
| `initialize()` | Creates a blank `DRAFT` of the requested type by calling the relevant service's `saveDraft` with empty data, then redirects into the workspace. |
| `workspace($type, $id)` | Loads the document with items, their products, their metadata, the timeline and the creator, and renders one Blade shell that behaves as an **editor** when the document is a draft and a **read-only viewer** once posted. |
| `saveDraft($type, $id)` | The AJAX autosave. Parses the grid's composed `type_id` keys back into type and ID; delegates to the receipt or issue service; then **replaces the per-line metadata** — deleting and re-creating `gov_document_item_meta` rows with their `row_index`, which is how five serial numbers stay attached to the right five units. Finally runs `evaluateDocument()` and returns the fresh checklist as JSON so the progress bar updates as you type. |
| `post($type, $id)` | Autosaves first, then runs `validateDocument()`. If there are errors it flattens them into one readable message and returns. Otherwise it calls `PostingPipelineManager::materialize()`. |
| `searchProducts()` | The Select2 feed for the grid. Returns a **composed ID** (`App\Models\Consumable_3`) so one dropdown can offer four different product types unambiguously, plus the current stock for display. |
| `productProfile($type, $id)` | Returns the compiled capability profile for one product as JSON. The grid calls this the moment you pick a product, and renders the required extra inputs on the fly. |
| `preview($type, $id)` | Pre-posting summary: line count, total quantity, total value, reference number. |
| `print($type, $id)` | The A4 government printout — **only for POSTED documents** (403 otherwise). |
| `uploadAttachment` / `deleteAttachment` | File handling, both refusing to touch a finalised document. Uploads are validated by MIME type and capped at 10 MB. |

**`GoodsReceiptController`** and **`GoodsIssueController`** — the older typed create/store/submit screens, retained alongside the generic workspace.

**`ProfileAdminController`** — the **Product Rules Studio** (`/settings/product-rules`, superadmin).

| Method | What it does |
|---|---|
| `index()` | The hub: counts of categories, locations and published policies; a recent-assignments activity feed; a sidebar tree of locations, hardware categories and consumable categories; the list of published policies. |
| `inspector()` | The panel that answers *"what rules actually apply to this target?"* Compiles the effective rules for the chosen category or location, then walks the **capability dictionary** so that every known rule is shown — including ones that are inherited or explicitly disabled — grouped by dictionary group, each with its name, description, current state, and which policy and layer decided it. |
| `assignPolicy()` | Applies a policy to a target. **Soft-expires** any currently active assignment for that exact target by setting `effective_to = now()`, then creates the new one. History is preserved; nothing is deleted. |
| `unassignPolicy($id)` | Expires one assignment. |
| `createRule($template)` / `storeRule()` | The creation wizard. Three templates: **hardware** (quantity + serial + create-assets), **consumable** (quantity + post-to-ledger), or **blank** (everything inherited). `storeRule()` validates a unique name and clones the template's capabilities into a new `DRAFT` policy at version 1.0. |
| `editPolicy($id)` / `saveDraftPolicy($id)` | Edit a draft's capabilities and their three-state behaviour. |
| `getImpactAnalysis($id)` | **Blast-radius calculation before publishing.** Counts the categories the policy is assigned to, works out every asset model in those categories, and then counts how many **draft documents** currently contain affected products. Returns a risk level: HIGH above 5 affected drafts, MEDIUM above 0, otherwise LOW. This tells an administrator whether publishing will disrupt work in progress. |
| `publishPolicy($id)` | Atomic promotion. Inside a transaction: archive any other published policy with the same name, bump the version, and set this one to `PUBLISHED`. Only drafts can be published. |
| `simulator()` / `runSimulation()` | Pick a location and a category and see the merged result **as the storekeeper would experience it**: which extra input fields appear, and which automations will fire on posting — each labelled with the policy and layer that caused it. |
| `duplicateRule($id)` | Clones a policy and all its capability behaviours as a new draft, resolving name collisions by appending a counter. |
| `confirmationHub($id)` | The post-creation landing page showing what was created and offering the obvious next steps. |
| `searchApi()` | Unified search across categories, locations and policies for the studio's search box. |

**`RepairLedgerBalances` console command** — recalculates `balance_after` across the ledger, for repairing historical data written before the balance column existed.

**`TabRegistry` / `Tab`** — a tiny registry letting a package add a tab to a native Snipe-IT page without editing its Blade template. The provider registers the Kardex tab onto the consumable, accessory and component pages; `InjectStoreOperationsUi` middleware compiles the registry into a script and injects it into the response.

---
---

# PART 3 — END-TO-END STORIES

## Story 1 — From an empty system to an employee holding a laptop

```
1. GEOGRAPHY            (geo-areas)
   gov_geo_areas is seeded from CSV. The whole country's administrative tree exists,
   every area carrying an `hid` path.

2. MINISTRIES           (organization)
   A superadmin imports bangladesh_ministries_bilingual.csv. Snipe-IT Companies are
   created or renamed to match the official organogram, and gov_ministries_directory
   links them.

3. TERRITORIES          (organization)
   The superadmin assigns ICT Officers to geo areas (gov_ict_jurisdictions). The
   observer attaches each officer to the "ICT Operations" permission group.

4. PROVISION AN OFFICE  (organization + geo-areas)
   An ICT Officer creates an office. isWithinBoundary() confirms the chosen place is
   inside their territory. A core Location + a gov_location_profiles row are created.
   Lifecycle = 'provisioned'.  Assign an Office Admin → 'configured'.

5. STAFF THE OFFICE     (office-membership + organization)
   Staff get gov_office_memberships. The Office Admin assigns duties, writing
   gov_office_responsibilities (storekeeper, primary_approver).
   OfficeReadinessService sees admin + approver + storekeeper + users
   → lifecycle = 'operational'. The office may now trade.

6. STOCK THE OFFICE     (store-operations)
   The storekeeper opens a Goods Receipt workspace. Picking a product fetches its
   compiled capability profile, so the grid asks for exactly what the policy requires
   (serials for laptops, just a quantity for pens). Posting runs the pipeline:
   post_inventory writes IN movements to the kardex; create_assets instantiates real
   Snipe-IT assets with tags and serials, linked back to the receipt.

7. CATALOGUE            (classification)
   Categories are adopted by the office (gov_tenant_scope_mappings), so only relevant
   ones appear. Missing ones can be provisioned in one click from the UNSPSC tree.

8. A USER LOGS IN       (office-membership → tenant-scope)
   SetWorkingContext puts their home membership ID in the session.
   InitializeTenantContext reads it, resolves company + location + allowedLocationIds,
   resolves their duty into a permission profile, and injects those permissions
   in memory. From this instant every query is filtered to their office.

9. SHOP                 (custom-requests)
   They browse the catalogue — already scoped, no filtering code required — and add
   items to a basket (draft_baskets / draft_basket_items).

10. SUBMIT              (custom-requests)
    submitBasket resolves each line's policy, splits the basket into one request
    document per policy, auto-approves the AUTO_APPROVE group, and routes the rest to
    the shared approver queue for that office.

11. APPROVE             (custom-requests + responsibilities)
    An approver opens the request (status flips to under_review). They decide per line,
    adjusting quantities. Under PRIMARY_AND_FINAL it moves to pending_final, where the
    final approver may only reduce. Approved lines become 'waiting'.

12. FULFIL              (custom-requests → store-operations → Snipe-IT)
    The storekeeper opens the fulfilment screen. For hardware, they pick specific
    physical assets from the pre-loaded available list; each is assigned natively and
    logged. For consumables and accessories, the lines are handed to
    SystemGoodsIssueService, which creates a Goods Issue document, writes OUT ledger
    movements with running balances, and returns the GI numbers. Listeners then update
    Snipe-IT's quantities and write native audit entries.
    When every approved line is fully issued, the request closes.

13. THE PAPER TRAIL
    The Fulfillment Register shows the closed request beside the Goods Issue documents
    it generated. The Kardex shows every movement with its running balance. The event
    timeline shows every decision. gov_asset_registrations traces each serial number
    back to the supplier challan it arrived on.
```

## Story 2 — An employee transfers to another district

```
1. The employee opens "My Office Memberships". The ClearanceEngine runs three rules.

2. Two fail:
     ✗ Physical inventory — "you hold 2 active assets"
     ✗ Office responsibility — "you hold 1 office responsibility"
   The Request Release button stays disabled, and each reason is shown in plain words.

3. They return their two laptops. The storekeeper checks them in. Rule 1 now passes.

4. For the duty, they propose a handshake to a colleague at the same office.
   proposeHandshake verifies they actually hold it and that no duplicate is pending.

5. The colleague sees the pending box and accepts. acceptHandshake runs one
   transaction: delete the old responsibility, create the new one, cancel any other
   pending handshakes for that duty, mark this one accepted, write an audit entry.
   Rule 2 now passes.

6. All green. They click Request Release. The server re-runs the engine (never trust
   the button) and sets the membership to release_requested.

7. They appear in the global floating pool.

8. The receiving office admin opens their Staff hub, finds them in the Claim dropdown,
   and claims them. One transaction: old home membership → released; new home
   membership → active; core users.location_id synced with saveQuietly().

9. Next request, SetWorkingContext picks up the new home membership,
   InitializeTenantContext rebuilds the boundary, and the employee now sees the new
   office's catalogue, stock and colleagues — with no change to their user account
   beyond one location field.

   If they had vanished without clearing, a superadmin would use the Override Console:
   force-release with a mandatory ≥10-character justification, permanently recorded
   in gov_override_audit_logs.
```

## Story 3 — What happens when someone tries to peek

```
An employee at Office A guesses the URL of an asset belonging to Office B.

  1. InitializeTenantContext has already set locationId = A.
  2. MinistryLocationScope adds "AND location_id = A" to the query.
  3. The asset is not found. They get a 404 — not a 403, because the system does not
     even confirm the record exists.

They try the JSON API instead.

  4. InitializeTenantContext is pushed onto the 'api' middleware group too.
     Same scope, same 404.

They POST an update to an Office B asset ID directly.

  5. TenantMutationObserver fires 'updating' → TenantBoundaryService::verify().
  6. AssetBoundaryPolicy::canMutate() sees location_id ≠ context location → false.
  7. A SECURITY VIOLATION warning is logged with their user ID, and a
     TenantBoundaryException (code OWNERSHIP) is thrown.
  8. HandleBoundaryExceptions converts it to a clean 403.

They create a new asset at their own office, but attach a supplier belonging to B.

  9. validateRelationshipIntegrity() runs two lookups: the supplier exists (so not 404),
     but is not visible to them (403, code RELATIONSHIP).

They are an ordinary employee and try to check out an asset at their own office.

 10. verifyAssetMutation() sees assigned_to changing, looks up their responsibility,
     and asks ResponsibilityRegistry::can($role, 'checkout_assets').
 11. 'employee' is not in the registry → false → 403, code ROLE_VIOLATION.
     Only the storekeeper can move stock.
```

---
---

# PART 4 — REFERENCE

## 4.1 Every table, what it holds, who owns it

### Main project — Snipe-IT

| Table | Holds |
|---|---|
| `assets` | Individually tracked physical items |
| `models` | Asset Model templates |
| `categories` | Groupings, with acceptance/EULA behaviour |
| `manufacturers` · `suppliers` | Who made it · who sold it |
| `depreciations` | Depreciation schedules |
| `status_labels` | States with deployable/pending/archived meaning |
| `custom_fields` · `custom_fieldsets` · `custom_field_custom_fieldset` · `models_custom_fields` | User-defined extra fields |
| `accessories` · `accessories_checkout` | Returnable bulk items and their handouts |
| `consumables` · `consumables_users` | Used-up items and their issues |
| `components` · `components_assets` | Parts installed into assets |
| `licenses` · `license_seats` | Software entitlements and their units |
| `kits` · `kits_models` · `kits_licenses` · `kits_accessories` · `kits_consumables` | Predefined bundles |
| `users` · `users_groups` · `permission_groups` · `company_user` | People and permissions |
| `companies` · `locations` · `departments` | Organisational and physical structure |
| `checkout_acceptances` | EULA acceptance and signatures |
| `requests` · `requested_assets` · `checkout_requests` | Native request feature (unused by Gov-Store) |
| `maintenances` · `maintenance_types` | Repair history |
| `action_logs` | The universal activity log |
| `settings` | The single system settings row |
| `imports` · `report_templates` | CSV imports and saved report definitions |
| `oauth_*` · `saml_nonces` · `throttle` · `login_attempts` · `password_resets` | Authentication plumbing |

### Custom packages — Gov-Store

| Table | Package | Holds |
|---|---|---|
| `gov_geo_areas` | geo-areas | The administrative map, with `hid` paths |
| `gov_location_profiles` | organization | Office extension: geo pin, admin, lifecycle, invite code |
| `gov_ict_jurisdictions` | organization | ICT officer → territory |
| `gov_location_roles` | organization | Legacy approver/storekeeper matrix |
| `gov_organization_activity_logs` | organization | Office audit trail |
| `gov_ministries_directory` | organization | The government organogram ↔ Companies |
| `gov_company_admins` | organization | Ministry administrators |
| `gov_office_memberships` | office-membership | User ↔ office, with home flag and status |
| `gov_office_responsibilities` | office-membership | **The live duty matrix** |
| `gov_role_handshakes` | office-membership | Pending peer duty handovers |
| `gov_role_assignments` | office-membership | Admin-driven role assignments |
| `gov_override_audit_logs` | office-membership | Superadmin emergency overrides |
| `gov_employee_verification_tokens` | office-membership | 6-character identity codes |
| `gov_user_onboardings` | user-onboarding | Queue of staff with no office yet |
| `gov_tenant_scopes` | tenant-scope | Scope strategy per reference type |
| `gov_tenant_scope_mappings` | tenant-scope | **Who owns / has adopted which reference row** |
| `gov_catalog_nodes` · `gov_catalog_definitions` | classification | UNSPSC tree and definitions (reference, overwritten) |
| `gov_catalog_enrichments` · `gov_catalog_synonyms` | classification | Bangla translations and search aliases (operational, preserved) |
| `gov_catalog_snipe_mappings` | classification | UNSPSC code → Snipe-IT category |
| `gov_catalog_import_history` | classification | Import audit |
| `gov_category_governance` | classification | Per-category ownership metadata |
| `draft_baskets` · `draft_basket_items` | custom-requests | The shopping basket |
| `custom_service_requests` | custom-requests | The request document |
| `custom_service_request_items` | custom-requests | Lines, with the four-stage quantity ladder |
| `custom_service_request_events` | custom-requests | Append-only request timeline |
| `gov_approval_policies` | custom-requests | Per-item/category approval policy |
| `custom_item_requests` | custom-requests | Legacy single-item requests |
| `gov_documents` · `gov_document_items` · `gov_document_item_meta` | store-operations | The generic document engine |
| `gov_document_references` · `_attachments` · `_timelines` | store-operations | Challans, scans, state history |
| `gov_inventory_movements` | store-operations | **The immutable kardex ledger** |
| `gov_goods_receipts` / `_items` · `gov_goods_issues` / `_items` · `gov_stock_adjustments` / `_items` | store-operations | Typed store documents |
| `gov_asset_registrations` | store-operations | Receipt line → created physical asset |
| `gov_profiles` · `gov_profile_capabilities` · `gov_profile_assignments` | store-operations | The policy engine |

## 4.2 Roles and what each one may do

| Role | How you become it | Sees | Can do |
|---|---|---|---|
| **Superadmin** | Snipe-IT `superuser` flag | Everything, everywhere | Everything. Bypasses all scoping. |
| **Company Admin** | a `gov_company_admins` row | Every office in their Ministry | Provision offices, manage users/locations/departments Ministry-wide, advanced reports. **Read-only** over inventory. |
| **ICT Officer** | a `gov_ict_jurisdictions` row | Every office inside their geographic territory | Provision and onboard offices, manage jurisdictions, create users and locations. **Read-only** over inventory. |
| **Office Admin** | `office_admin_id` on the office profile | Their own office | Configure the office, assign duties, manage local staff, claim transferring employees, generate invite codes. **Read-only** over inventory. |
| **Storekeeper** | an `OfficeResponsibility` row with slug `storekeeper` | Their own office | **The only role that may physically move stock.** Receive goods, issue goods, adjust stock, audit, fulfil requests. |
| **Primary / Final Approver** | slug `primary_approver` / `final_approver` | Their own office | Approve and reject requests. **Read-only** over inventory — a deliberate separation of duties from the storekeeper. |
| **Employee** | the default | Their own office | Browse the catalogue, fill a basket, submit requests, manage their own memberships and delegations. |

## 4.3 "I want to change X — where do I go?"

| I want to… | Go to |
|---|---|
| change what a storekeeper is allowed to do | `packages/gov-store/tenant-scope/src/config/permissions.php` (UI permissions) and `Validators/ResponsibilityRegistry.php` (operational rights) |
| add a new leaving-clearance rule | write a class implementing `IClearanceRule` in `office-membership/src/Rules/`, register it in `OfficeMembershipServiceProvider::register()` |
| add a new data-capture rule for receiving goods | write a class implementing `CapabilityInterface` in `store-operations/src/Capabilities/`, add it to `CapabilityRegistry::$registry` and `$dictionary` |
| change how offices become "operational" | `organization/src/Services/OfficeReadinessService.php` |
| change how a basket is split at submission | `custom-requests/src/Services/BasketService::submitBasket()` |
| change who may approve what | `custom-requests/src/Services/PolicyService.php` and the policies screen |
| change how the isolation wall is computed | `tenant-scope/src/Http/Middleware/InitializeTenantContext.php` |
| change what a role can see | the scopes in `tenant-scope/src/Scopes/` |
| change what a role can change | `tenant-scope/src/Services/TenantBoundaryService.php` and `src/Policies/` |
| add a sidebar menu item | `$registry->register([...])` inside your package's service provider |
| add a tab to a native Snipe-IT page | `store-operations/src/UI/TabRegistry.php` |
| add a new document type | `gov_documents` already supports it — add a service and a route; the workspace is generic |
| add a UI string | a translation file in the package's `resources/lang/`, then use `__('namespace::file.key')` |
| **change anything in the main Snipe-IT project** | **Don't.** Find the extension point in a package instead. |

## 4.4 Glossary

| Term | Meaning |
|---|---|
| **Adapter** | A small class that gives several different kinds of thing one common interface, so calling code does not need to know which is which. |
| **Adoption** | An office or ministry declaring "we use this category". Written to `gov_tenant_scope_mappings`; instantly changes what the office sees. |
| **Boundary** | The slice of data a person may see and change. |
| **Bounded context** | A self-contained module that owns its own tables and rules. Each Gov-Store package is one. |
| **Capability** | One configurable rule in the store-operations engine (require a serial, post to the ledger, create assets). |
| **Checkout / Checkin** | Give a thing to someone / take it back. |
| **Clearance** | The pass/fail gate an employee must satisfy before leaving an office. |
| **Compiled snapshot** | The frozen set of rules stored on a document, so it posts under the rules in force when it was drafted. |
| **FMCS** | Full Multiple Company Support — Snipe-IT's own per-company scoping switch. Separate from, and weaker than, tenant-scope. |
| **GPO** | The code's nickname for a policy in the store-operations rules engine. |
| **GRN / GI** | Goods Receipt Note / Goods Issue — official documents for stock in and stock out. |
| **Handshake** | A proposed peer-to-peer transfer of a duty, requiring the recipient to accept. |
| **`hid`** | A hierarchy path string. `LIKE 'parent%'` fetches an entire subtree in one indexed query. Used for geography and for the UNSPSC catalogue. |
| **Kardex** | The stock register — every movement of every item, with a running balance. |
| **Membership vs Responsibility** | *Membership* = which office you belong to. *Responsibility* = which duty you hold there. Different things, different tables. |
| **Morph map** | A lookup that lets the database store `consumable` instead of `App\Models\Consumable`, so renaming a class does not break stored data. |
| **Policy (approval)** | `AUTO_APPROVE` / `PRIMARY_ONLY` / `PRIMARY_AND_FINAL` — how many humans must sign. |
| **Policy (store rules)** | A named, versioned bundle of capabilities assigned to categories, offices or ministries. |
| **Polymorphic** | A link that can point at several different tables, stored as a type plus an ID. |
| **Projection** | A derived copy kept in step with a source of truth. Snipe-IT's `qty` columns are projections of the ledger. |
| **Scope (global)** | An invisible filter automatically added to every query on a model. |
| **Service Request** | The document produced when a basket is submitted. |
| **Tenant** | One office or ministry — the unit of isolation. |
| **Transformer** | The class that shapes a model into API JSON. API controllers must always use one. |
| **Working context** | The office a person is currently acting as. Held in session key `gov_working_membership_id`; switchable if they have several memberships. |
| **Zero-Touch** | Extending Snipe-IT without editing any of its files. |

---

*Generated from the codebase. Main project = Snipe-IT (everything outside `packages/`). Custom packages = `packages/gov-store/{geo-areas, organization, office-membership, user-onboarding, tenant-scope, classification, custom-requests, store-operations}`. Database diagrams: [ERD.md](ERD.md). Shorter architecture tour: [workflow.md](workflow.md).*
