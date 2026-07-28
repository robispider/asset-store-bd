# Database ER Diagram — Asset-Store-BD

> Reverse-engineered from the **live database** `asset_store_bd` (67 tables) + all migrations. Split into subject-area diagrams because one graph of 67 tables is unreadable. Mermaid `erDiagram` blocks render on GitHub / any Mermaid viewer.
>
> **Two layers:** **Core Snipe-IT** inventory (≈50 tables) + **Gov-Store** custom packages (`custom_service_*`, `gov_*` — 11 tables).
>
> **Important:** core Snipe-IT declares its relations in **Eloquent, not as DB foreign keys** (verified — the DB has almost no FK constraints on core tables). The gov-store tables *do* declare real FKs. So the lines below combine: (a) real DB FKs, and (b) Eloquent relationships for core. Polymorphic links can't be drawn as one FK — they're listed separately in §9.

## Contents
- [Legend](#legend)
- [1. Core — Reference data & Assets](#1-core--reference-data--assets)
- [2. Core — Checkout / Assignment / History](#2-core--checkout--assignment--history)
- [3. Core — Users, Groups, Org units](#3-core--users-groups-org-units)
- [4. Core — Predefined Kits](#4-core--predefined-kits)
- [5. Gov-Store — Service Requests](#5-gov-store--service-requests)
- [6. Gov-Store — Organization & Geo](#6-gov-store--organization--geo)
- [7. Gov-Store — Tenant Scope](#7-gov-store--tenant-scope)
- [8. Cross-layer bridge (how gov links to core)](#8-cross-layer-bridge-how-gov-links-to-core)
- [9. Polymorphic relationships](#9-polymorphic-relationships-not-single-fks)
- [10. Confirmed DB foreign keys](#10-confirmed-db-foreign-keys-live-schema)
- [11. Full table inventory (67)](#11-full-table-inventory-67)
- [12. Appendix — full columns](#12-appendix--full-columns)

---

## Legend

- `PK` primary key · `FK` foreign key (Eloquent or DB) · `poly` polymorphic (`*_type` + `*_id`) · `UK` unique.
- Crow's-foot: `||--o{` one-to-many · `||--||` one-to-one · `}o--o|` many-to-optional-one · `}o--||` many-to-one.
- Entity names = table names (UPPERCASE). Only key/identifying columns shown per entity; full columns in §12.

---

## 1. Core — Reference data & Assets

```mermaid
erDiagram
    COMPANIES     ||--o{ ASSETS : "company_id"
    LOCATIONS     ||--o{ ASSETS : "location_id / rtd_location_id"
    SUPPLIERS     ||--o{ ASSETS : "supplier_id"
    STATUS_LABELS ||--o{ ASSETS : "status_id"
    MODELS        ||--o{ ASSETS : "model_id"

    MANUFACTURERS ||--o{ MODELS : "manufacturer_id"
    CATEGORIES    ||--o{ MODELS : "category_id"
    DEPRECIATIONS ||--o{ MODELS : "depreciation_id"
    CUSTOM_FIELDSETS ||--o{ MODELS : "fieldset_id"

    CUSTOM_FIELDSETS ||--o{ CUSTOM_FIELD_CUSTOM_FIELDSET : ""
    CUSTOM_FIELDS    ||--o{ CUSTOM_FIELD_CUSTOM_FIELDSET : ""
    MODELS           ||--o{ MODELS_CUSTOM_FIELDS : ""
    CUSTOM_FIELDS    ||--o{ MODELS_CUSTOM_FIELDS : ""

    ASSETS {
        int id PK
        string asset_tag UK
        int model_id FK
        int status_id FK
        int supplier_id FK
        int company_id FK
        int location_id FK
        int rtd_location_id FK
        int assigned_to "poly (assigned_type)"
        bool requestable
        datetime last_checkout
        datetime last_checkin
    }
    MODELS {
        int id PK
        string name
        int manufacturer_id FK
        int category_id FK
        int depreciation_id FK
        int fieldset_id FK
        bool requestable
    }
    CATEGORIES {
        int id PK
        string category_type
        bool require_acceptance
        text eula_text
        bool checkin_email
    }
    MANUFACTURERS { int id PK
        string name }
    SUPPLIERS { int id PK
        string name }
    DEPRECIATIONS { int id PK
        int months }
    STATUS_LABELS { int id PK
        bool deployable
        bool pending
        bool archived }
    COMPANIES { int id PK
        int parent_id FK }
    LOCATIONS { int id PK
        int parent_id FK
        int company_id FK }
    CUSTOM_FIELDS { int id PK
        string db_column
        bool field_encrypted }
    CUSTOM_FIELDSETS { int id PK
        string name }
    CUSTOM_FIELD_CUSTOM_FIELDSET { int id PK
        int custom_field_id FK
        int custom_fieldset_id FK }
    MODELS_CUSTOM_FIELDS { int id PK
        int asset_model_id FK
        int custom_field_id FK }
```

---

## 2. Core — Checkout / Assignment / History

`assets.assigned_to` + `assigned_type` is polymorphic (User / Location / Asset). Accessories, consumables, components, licenses each have their own pivot.

```mermaid
erDiagram
    LICENSES   ||--o{ LICENSE_SEATS : "license_id"
    LICENSE_SEATS }o--o| USERS  : "assigned_to"
    LICENSE_SEATS }o--o| ASSETS : "asset_id"

    ACCESSORIES ||--o{ ACCESSORIES_CHECKOUT : "accessory_id"
    ACCESSORIES_CHECKOUT }o--o| USERS : "assigned_to (poly)"

    CONSUMABLES ||--o{ CONSUMABLES_USERS : "consumable_id"
    CONSUMABLES_USERS }o--|| USERS : "assigned_to"

    COMPONENTS ||--o{ COMPONENTS_ASSETS : "component_id"
    COMPONENTS_ASSETS }o--|| ASSETS : "asset_id"

    ASSETS ||--o{ MAINTENANCES : "asset_id"
    MAINTENANCE_TYPES ||--o{ MAINTENANCES : "maintenance_type_id"
    SUPPLIERS ||--o{ MAINTENANCES : "supplier_id"

    ASSETS ||--o{ REQUESTS : "asset_id"
    USERS  ||--o{ REQUESTS : "user_id"
    ASSETS ||--o{ REQUESTED_ASSETS : "asset_id"
    USERS  ||--o{ REQUESTED_ASSETS : "user_id"

    USERS ||--o{ CHECKOUT_ACCEPTANCES : "assigned_to_id"
    USERS ||--o{ ACTION_LOGS : "created_by"

    LICENSES {
        int id PK
        int seats
        int manufacturer_id FK
        int category_id FK
        int supplier_id FK
        int depreciation_id FK
        int company_id FK
        date expiration_date
    }
    LICENSE_SEATS { int id PK
        int license_id FK
        int assigned_to FK
        int asset_id FK }
    ACCESSORIES { int id PK
        int qty
        bool requestable
        int category_id FK
        int manufacturer_id FK }
    ACCESSORIES_CHECKOUT { int id PK
        int accessory_id FK
        int assigned_to "poly (assigned_type)" }
    CONSUMABLES { int id PK
        int qty
        bool requestable
        int category_id FK }
    CONSUMABLES_USERS { int id PK
        int consumable_id FK
        int assigned_to FK }
    COMPONENTS { int id PK
        int qty
        int category_id FK }
    COMPONENTS_ASSETS { int id PK
        int component_id FK
        int asset_id FK
        int assigned_qty }
    MAINTENANCES { int id PK
        int asset_id FK
        int maintenance_type_id FK
        int supplier_id FK
        decimal cost }
    CHECKOUT_ACCEPTANCES { int id PK
        string checkoutable_type "poly"
        int checkoutable_id "poly"
        int assigned_to_id FK
        datetime accepted_at
        datetime declined_at }
    ACTION_LOGS { int id PK
        string action_type
        string item_type "poly"
        int item_id "poly"
        string target_type "poly"
        int target_id "poly"
        int created_by FK
        int company_id }
    REQUESTS { int id PK
        int asset_id FK
        int user_id FK }
    REQUESTED_ASSETS { int id PK
        int asset_id FK
        int user_id FK }
```

---

## 3. Core — Users, Groups, Org units

```mermaid
erDiagram
    COMPANIES   ||--o{ USERS : "company_id"
    LOCATIONS   ||--o{ USERS : "location_id"
    DEPARTMENTS ||--o{ USERS : "department_id"
    USERS       ||--o{ USERS : "manager_id (self)"

    COMPANIES ||--o{ DEPARTMENTS : "company_id"
    LOCATIONS ||--o{ DEPARTMENTS : "location_id"

    USERS ||--o{ USERS_GROUPS : ""
    PERMISSION_GROUPS ||--o{ USERS_GROUPS : ""
    USERS ||--o{ COMPANY_USER : ""
    COMPANIES ||--o{ COMPANY_USER : ""

    USERS {
        int id PK
        string username UK
        string email
        int location_id FK
        int company_id FK
        int department_id FK
        int manager_id FK
        text permissions
        bool activated
    }
    PERMISSION_GROUPS { int id PK
        string name
        text permissions }
    USERS_GROUPS { int user_id FK
        int group_id FK }
    COMPANY_USER { int id PK
        int company_id FK
        int user_id FK }
    DEPARTMENTS { int id PK
        int company_id FK
        int location_id FK
        int manager_id FK }
```

---

## 4. Core — Predefined Kits

```mermaid
erDiagram
    KITS ||--o{ KITS_MODELS : ""
    KITS ||--o{ KITS_LICENSES : ""
    KITS ||--o{ KITS_ACCESSORIES : ""
    KITS ||--o{ KITS_CONSUMABLES : ""
    MODELS      ||--o{ KITS_MODELS : "model_id"
    LICENSES    ||--o{ KITS_LICENSES : "license_id"
    ACCESSORIES ||--o{ KITS_ACCESSORIES : "accessory_id"
    CONSUMABLES ||--o{ KITS_CONSUMABLES : "consumable_id"

    KITS { int id PK
        string name }
    KITS_MODELS { int id PK
        int kit_id FK
        int model_id FK
        int quantity }
    KITS_LICENSES { int id PK
        int kit_id FK
        int license_id FK
        int quantity }
    KITS_ACCESSORIES { int id PK
        int kit_id FK
        int accessory_id FK
        int quantity }
    KITS_CONSUMABLES { int id PK
        int kit_id FK
        int consumable_id FK
        int quantity }
```

---

## 5. Gov-Store — Service Requests

The basket→request→approval→fulfillment core. `custom_service_requests` = the document/basket; items carry the **separation of quantities** (`requested/approved/reserved/issued`); events = immutable timeline.

```mermaid
erDiagram
    CUSTOM_SERVICE_REQUESTS ||--o{ CUSTOM_SERVICE_REQUEST_ITEMS : "request_id"
    CUSTOM_SERVICE_REQUESTS ||--o{ CUSTOM_SERVICE_REQUEST_EVENTS : "request_id"

    USERS ||--o{ CUSTOM_SERVICE_REQUESTS : "requested_by"
    USERS ||--o{ CUSTOM_SERVICE_REQUESTS : "approved_by"
    USERS ||--o{ CUSTOM_SERVICE_REQUESTS : "assigned_approver_id"
    LOCATIONS ||--o{ CUSTOM_SERVICE_REQUESTS : "delivery_location_id"
    USERS ||--o{ CUSTOM_SERVICE_REQUEST_EVENTS : "user_id"

    GOV_APPROVAL_POLICIES }o..o{ CATEGORIES : "target (poly) — category"

    CUSTOM_SERVICE_REQUESTS {
        int id PK
        string request_number UK "SR-YYYY-000000"
        int requested_by FK
        int approved_by FK
        int assigned_approver_id FK
        string request_type
        string resolved_policy
        string approval_status "draft→submitted→…→closed"
        string fulfillment_status
        int delivery_location_id FK
        datetime submitted_at
        datetime approved_at
        datetime closed_at
        datetime deleted_at
    }
    CUSTOM_SERVICE_REQUEST_ITEMS {
        int id PK
        int request_id FK
        string requested_type "poly"
        int requested_id "poly"
        string fulfilled_type "poly"
        int fulfilled_id "poly"
        int requested_qty
        int approved_qty
        int reserved_qty
        int issued_qty
        string line_approval_status
        string line_fulfillment_status
    }
    CUSTOM_SERVICE_REQUEST_EVENTS {
        int id PK
        int request_id FK
        int user_id FK
        string event_type
        json details
        datetime created_at
    }
    GOV_APPROVAL_POLICIES {
        int id PK
        string target_type "poly (category)"
        int target_id "poly"
        string policy_name
    }
```

---

## 6. Gov-Store — Organization & Geo

Extends core `locations` with a 1:1 profile + a roles row, ties them to the Bangladesh geo hierarchy, and tags ICT officers to a geo boundary.

```mermaid
erDiagram
    LOCATIONS ||--|| GOV_LOCATION_PROFILES : "location_id (UK)"
    LOCATIONS ||--|| GOV_LOCATION_ROLES : "location_id (UK)"
    LOCATIONS ||--o{ GOV_ORGANIZATION_ACTIVITY_LOGS : "location_id"

    GOV_GEO_AREAS ||--o{ GOV_LOCATION_PROFILES : "geo_area_id"
    GOV_GEO_AREAS ||--o{ GOV_ICT_JURISDICTIONS : "geo_area_id"
    GOV_GEO_AREAS ||--o{ GOV_GEO_AREAS : "parent_geo_code (self)"

    USERS ||--o{ GOV_LOCATION_PROFILES : "office_admin_id / geo_area_verified_by"
    USERS ||--o{ GOV_ICT_JURISDICTIONS : "user_id"
    USERS ||--o{ GOV_LOCATION_ROLES : "approver/delegate/storekeeper ids"
    USERS ||--o{ GOV_ORGANIZATION_ACTIVITY_LOGS : "performed_by"

    GOV_GEO_AREAS {
        int GeoAreaId PK
        string hid "hierarchy path, LIKE-prefix"
        string geo_type
        int parent_geo_code
        int geo_code
        string en_name
        string bn_name
        int GeoLevel
    }
    GOV_LOCATION_PROFILES {
        int id PK
        int location_id FK,UK
        int geo_area_id FK
        int office_admin_id FK
        string lifecycle_status "provisioned→configured→operational"
        int geo_area_verified_by FK
    }
    GOV_LOCATION_ROLES {
        int id PK
        int location_id FK,UK
        int primary_approver_id FK
        int primary_delegate_id FK
        int final_approver_id FK
        int final_delegate_id FK
        int storekeeper_id FK
        int storekeeper_delegate_id FK
    }
    GOV_ICT_JURISDICTIONS {
        int id PK
        int user_id FK,UK
        int geo_area_id FK
    }
    GOV_ORGANIZATION_ACTIVITY_LOGS {
        int id PK
        int location_id FK
        int performed_by FK
        string event_type
        json details
    }
```

---

## 7. Gov-Store — Tenant Scope

Config table (`gov_tenant_scopes`, one row per reference type + strategy) + a polymorphic mapping table binding a reference row to a company/location boundary.

```mermaid
erDiagram
    GOV_TENANT_SCOPES {
        int id PK
        string reference_type UK "categories/models/suppliers/…"
        string scope_strategy "global|company|office"
        bool show_only_used
    }
    GOV_TENANT_SCOPE_MAPPINGS {
        int id PK
        string scope_type "company | location"
        int scope_id "→ companies.id | locations.id (poly)"
        string reference_type "category/model/supplier/manufacturer/location (poly)"
        int reference_id "→ that table's id (poly)"
    }
    GOV_TENANT_SCOPES ||..o{ GOV_TENANT_SCOPE_MAPPINGS : "reference_type (config, not FK)"
```

---

## 8. Cross-layer bridge (how gov links to core)

The single most important integration view — where custom tables touch Snipe-IT core.

```mermaid
erDiagram
    USERS ||--o{ CUSTOM_SERVICE_REQUESTS : "requester/approver"
    LOCATIONS ||--o{ CUSTOM_SERVICE_REQUESTS : "delivery"
    LOCATIONS ||--|| GOV_LOCATION_PROFILES : "1:1 profile"
    LOCATIONS ||--|| GOV_LOCATION_ROLES : "1:1 roles"

    CUSTOM_SERVICE_REQUEST_ITEMS }o..o{ ASSETS : "requested/fulfilled (poly)"
    CUSTOM_SERVICE_REQUEST_ITEMS }o..o{ ACCESSORIES : "requested/fulfilled (poly)"
    CUSTOM_SERVICE_REQUEST_ITEMS }o..o{ CONSUMABLES : "requested/fulfilled (poly)"

    GOV_TENANT_SCOPE_MAPPINGS }o..o{ CATEGORIES : "reference (poly)"
    GOV_TENANT_SCOPE_MAPPINGS }o..o{ MODELS : "reference (poly)"
    GOV_TENANT_SCOPE_MAPPINGS }o..o{ SUPPLIERS : "reference (poly)"
    GOV_TENANT_SCOPE_MAPPINGS }o..o{ MANUFACTURERS : "reference (poly)"

    GOV_APPROVAL_POLICIES }o..o{ CATEGORIES : "target (poly)"
```

> Fulfillment turns an approved item into a real Snipe-IT checkout via Adapters — the item's `fulfilled_type/id` points at the concrete core row that was issued (supports substitution vs the originally `requested_*`).

---

## 9. Polymorphic relationships (not single FKs)

| Table.columns | `*_type` values → target table |
|---|---|
| `assets.assigned_type` + `assigned_to` | `User`→users, `Location`→locations, `Asset`→assets |
| `accessories_checkout.assigned_type` + `assigned_to` | `User`→users (also asset/location) |
| `action_logs.item_type/item_id` | asset / accessory / consumable / component / license / user |
| `action_logs.target_type/target_id` | user / location / asset |
| `checkout_acceptances.checkoutable_type/id` | asset / accessory / consumable / licenseseat |
| `maintenances.checked_out_to_type/id` | user / asset |
| `custom_service_request_items.requested_type/id` | `asset`→assets, `accessory`→accessories, `consumable`→consumables |
| `custom_service_request_items.fulfilled_type/id` | same set (supports substitution) |
| `gov_tenant_scope_mappings.scope_type/scope_id` | `company`→companies, `location`→locations |
| `gov_tenant_scope_mappings.reference_type/reference_id` | category / model / supplier / manufacturer / location |
| `gov_approval_policies.target_type/target_id` | `category`→categories |

> Morph aliases (`asset`,`accessory`,`consumable`) are registered in `CustomRequestServiceProvider::boot()` via `Relation::morphMap()`.

---

## 10. Confirmed DB foreign keys (live schema)

These are the **actual** `FOREIGN KEY` constraints present in the DB (core Snipe-IT deliberately has none on business tables):

```
custom_service_request_events.request_id      -> custom_service_requests.id
custom_service_request_events.user_id         -> users.id
custom_service_request_items.request_id       -> custom_service_requests.id
custom_service_requests.requested_by          -> users.id
custom_service_requests.approved_by           -> users.id
custom_service_requests.assigned_approver_id  -> users.id
gov_ict_jurisdictions.user_id                 -> users.id
gov_ict_jurisdictions.geo_area_id             -> gov_geo_areas.GeoAreaId
gov_location_profiles.location_id             -> locations.id
gov_location_profiles.geo_area_id             -> gov_geo_areas.GeoAreaId
gov_location_profiles.office_admin_id         -> users.id
gov_location_profiles.geo_area_verified_by    -> users.id
gov_location_roles.location_id                -> locations.id
gov_location_roles.primary_approver_id        -> users.id
gov_location_roles.primary_delegate_id        -> users.id
gov_location_roles.final_approver_id          -> users.id
gov_location_roles.final_delegate_id          -> users.id
gov_location_roles.storekeeper_id             -> users.id
gov_location_roles.storekeeper_delegate_id    -> users.id
gov_organization_activity_logs.location_id    -> locations.id
gov_organization_activity_logs.performed_by   -> users.id
telescope_entries_tags.entry_uuid             -> telescope_entries.uuid
```

---

## 11. Full table inventory (67)

**Core inventory & reference (18):** `assets` `models` `categories` `manufacturers` `suppliers` `locations` `companies` `departments` `status_labels` `depreciations` `custom_fields` `custom_fieldsets` `custom_field_custom_fieldset` `models_custom_fields` `maintenances` `maintenance_types` `asset_logs` `asset_uploads`

**Checkoutables & pivots (11):** `licenses` `license_seats` `accessories` `accessories_checkout` `consumables` `consumables_users` `components` `components_assets` `checkout_acceptances` `requests` `requested_assets`

**Users / auth / access (10):** `users` `users_groups` `permission_groups` `company_user` `throttle` `login_attempts` `password_resets` `saml_nonces` `settings` `action_logs`

**Kits (5):** `kits` `kits_models` `kits_licenses` `kits_accessories` `kits_consumables`

**OAuth / Passport (5):** `oauth_access_tokens` `oauth_auth_codes` `oauth_clients` `oauth_personal_access_clients` `oauth_refresh_tokens`

**Imports / reports / framework (4):** `imports` `report_templates` `migrations` `telescope_entries` (+`telescope_entries_tags` `telescope_monitoring`)

**Gov-Store — service requests (4):** `custom_service_requests` `custom_service_request_items` `custom_service_request_events` `gov_approval_policies`

**Gov-Store — organization/geo (5):** `gov_location_profiles` `gov_location_roles` `gov_ict_jurisdictions` `gov_organization_activity_logs` `gov_geo_areas`

**Gov-Store — tenant scope (2):** `gov_tenant_scopes` `gov_tenant_scope_mappings`

---

## 12. Appendix — full columns (key tables)

<details><summary>Gov-Store tables</summary>

```
custom_service_requests: id, request_number, requested_by, approved_by, assigned_approver_id,
  request_type, resolved_policy, purpose, justification, required_by_date, delivery_location_id,
  cost_center, approval_status, fulfillment_status, submitted_at, approved_at, closed_at,
  created_at, updated_at, deleted_at
custom_service_request_items: id, request_id, requested_type, requested_id, fulfilled_type,
  fulfilled_id, requested_qty, approved_qty, reserved_qty, issued_qty, line_approval_status,
  line_fulfillment_status, notes, created_at, updated_at
custom_service_request_events: id, request_id, user_id, event_type, details, created_at
gov_approval_policies: id, target_type, target_id, policy_name, created_at, updated_at
gov_geo_areas: GeoAreaId, hid, geo_type, parent_geo_code, geo_code, bn_name, domain, en_name,
  GeoLevel, created_at, updated_at
gov_ict_jurisdictions: id, user_id, geo_area_id, created_at, updated_at
gov_location_profiles: id, location_id, geo_area_id, office_admin_id, lifecycle_status,
  geo_area_verified_at, geo_area_verified_by, created_at, updated_at
gov_location_roles: id, location_id, primary_approver_id, primary_delegate_id,
  primary_delegate_until, final_approver_id, final_delegate_id, final_delegate_until,
  storekeeper_id, storekeeper_delegate_id, storekeeper_delegate_until, created_at, updated_at
gov_organization_activity_logs: id, location_id, performed_by, event_type, details, created_at
gov_tenant_scopes: id, reference_type, scope_strategy, show_only_used, created_at, updated_at
gov_tenant_scope_mappings: id, scope_type, scope_id, reference_type, reference_id,
  created_at, updated_at
```
</details>

<details><summary>Core tables (key entities)</summary>

```
assets: id, name, asset_tag, model_id, serial, purchase_date, asset_eol_date, eol_explicit,
  purchase_cost, order_number, assigned_to, notes, image, created_by, physical, status_id,
  archived, warranty_months, depreciate, supplier_id, requestable, rtd_location_id,
  _snipeit_mac_address_1, accepted, last_checkout, last_checkin, expected_checkin, company_id,
  assigned_type, last_audit_date, next_audit_date, location_id, checkin_counter,
  checkout_counter, requests_counter, byod, created_at, updated_at, deleted_at
models: id, name, model_number, min_amt, manufacturer_id, category_id, require_serial,
  depreciation_id, created_by, eol, image, fieldset_id, notes, requestable, …
categories: id, name, tag_color, eula_text, use_default_eula, require_acceptance,
  alert_on_response, category_type, checkin_email, image, notes, …
users: id, email, password, permissions, activated, first_name, last_name, username,
  location_id, company_id, department_id, manager_id, employee_num, jobtitle, ldap_import,
  two_factor_secret, scim_externalid, vip, remote, start_date, end_date, …
licenses: id, name, serial, seats, depreciation_id, supplier_id, manufacturer_id, category_id,
  company_id, expiration_date, termination_date, maintained, reassignable, min_amt, …
license_seats: id, license_id, assigned_to, unreassignable_seat, notes, asset_id, …
accessories: id, name, category_id, qty, requestable, location_id, company_id, min_amt,
  manufacturer_id, model_number, supplier_id, …
accessories_checkout: id, accessory_id, assigned_to, assigned_type, note, created_by, …
consumables: id, name, category_id, location_id, supplier_id, qty, requestable, company_id,
  min_amt, manufacturer_id, item_no, …
consumables_users: id, consumable_id, assigned_to, note, created_by, …
components: id, name, category_id, location_id, company_id, supplier_id, qty, manufacturer_id,
  min_amt, serial, …
components_assets: id, assigned_qty, component_id, asset_id, note, created_by, …
maintenances: id, asset_id, supplier_id, maintenance_type_id, asset_maintenance_type, name,
  is_warranty, start_date, completion_date, cost, checked_out_to_id, checked_out_to_type, …
action_logs: id, created_by, action_type, target_id, target_type, location_id, note, item_type,
  item_id, expected_checkin, quantity, accepted_id, company_id, action_date, action_source,
  remote_ip, user_agent, …
checkout_acceptances: id, checkoutable_type, checkoutable_id, assigned_to_id, qty,
  signature_filename, accepted_at, declined_at, stored_eula, …
locations: id, name, city, state, country, parent_id, manager_id, company_id, ldap_ou, …
companies: id, name, parent_id, email, phone, …
departments: id, name, company_id, location_id, manager_id, …
status_labels: id, name, deployable, pending, archived, color, show_in_nav, default_label
custom_fields: id, name, format, element, field_values, field_encrypted, db_column,
  show_in_email, is_unique, display_in_user_view, …
requests: id, asset_id, user_id, request_code
requested_assets: id, asset_id, user_id, accepted_at, denied_at, notes
```
</details>

---

*Source: live DB `asset_store_bd` (information_schema) + package migrations. Core relations are Eloquent-defined (no DB FKs); gov-store relations are real DB FKs (§10). Regenerate FK list: `SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL;`*