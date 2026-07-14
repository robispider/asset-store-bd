# Gov-Store Extension — Codebase Audit Report

**Generated:** 2026-07-14  
**Scope:** All packages under `packages/gov-store/`  
**Purpose:** Architecture, workflows, models, services, observers, and controllers audit — no code creation.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Package Overview Map](#2-package-overview-map)
3. [Package 1: Tenant-Scope](#3-package-1-tenant-scope)
4. [Package 2: Office-Membership](#4-package-2-office-membership)
5. [Package 3: Organization](#5-package-3-organization)
6. [Package 4: Geo-Areas](#6-package-4-geo-areas)
7. [Package 5: Custom-Requests](#7-package-5-custom-requests)
8. [Cross-Package Dependencies](#8-cross-package-dependencies)
9. [Architecture Patterns & Conventions](#9-architecture-patterns--conventions)
10. [Security & IAM Flow](#10-security--iam-flow)
11. [Gaps, Risks & Observations](#11-gaps-risks--observations)

---

## 1. Executive Summary

The Gov-Store extension is a **multi-tenant government e-commerce and inventory management system** built on top of Snipe-IT. It consists of **5 bounded contexts (packages)** that implement:

| Package | Domain | Responsibility |
|---------|--------|----------------|
| `tenant-scope` | Multi-Tenancy Engine | Query isolation, permission resolution, tenant context, boundary enforcement |
| `office-membership` | Membership & Role Management | Office memberships, role assignments, peer handshakes, verification codes |
| `organization` | Office Provisioning | Office creation, geo-verification, ICT jurisdiction, office lifecycle |
| `geo-areas` | Geographic Reference | Bangladesh geographic hierarchy (Division → District → Upazilla → Union) |
| `custom-requests` | E-Commerce & Fulfillment | Item requests, approval workflows, shopping basket, fulfillment, checkout |

**Total Statistics:**

| Category | Count |
|----------|-------|
| Packages | 5 |
| Models | 18 |
| Services | 22 |
| Controllers | 13 |
| Observers | 4 |
| Middleware | 6 (1 missing file) |
| Events/Listeners | 1 pair |
| Routes | ~40 endpoints |
| Migrations | 9 files (13 tables) |

---

## 2. Package Overview Map

```
packages/gov-store/
├── tenant-scope/          ← LAYER 1-7: Core IAM & Query Isolation Engine
│   ├── Models: TenantScopeConfig, TenantScopeMapping
│   ├── Services: AssignmentResolver, BoundaryResolver, CapabilityProfileResolver,
│   │             ReferenceOwnershipService, SnipePermissionAdapter, TenantBoundaryService
│   ├── Contexts: TenantContext (singleton)
│   ├── Scopes: MinistryLocationScope, TenantScope, UserScope
│   ├── Policies: AssetBoundaryPolicy, CategoryBoundaryPolicy,
│   │             ReferenceBoundaryPolicy, TransactionalBoundaryPolicy
│   ├── Middleware: InitializeTenantContext, InjectTenantScopeUi, HandleBoundaryExceptions
│   └── Observers: TenantMutationObserver
│
├── office-membership/     ← LAYER 3-4: Membership & Role Assignment
│   ├── Models: OfficeMembership, RoleAssignment, OfficeResponsibility,
│   │             RoleHandshake, EmployeeVerificationToken, OverrideAuditLog
│   ├── Services: OfficeMembershipService, RoleAssignmentService,
│   │             RoleHandshakeService, ClearanceEngine, LegacyUserSynchronizationService
│   ├── Controllers: MembershipController, RoleHandshakeController,
│   │                  MembershipAdminController, RoleAssignmentController
│   ├── Middleware: InjectMembershipUi, SetWorkingContext
│   └── Observers: MembershipActivityLogObserver, UserSyncObserver
│
├── organization/          ← LAYER 3: Office Provisioning & Lifecycle
│   ├── Models: IctJurisdiction, LocationProfile, LocationRole, OrganizationActivityLog
│   ├── Services: OfficeConfigurationService, OfficeProvisioningService, OfficeReadinessService
│   ├── Controllers: ConfigurationController, OfficeHubController,
│   │                  OnboardLocationController, ProvisioningController
│   ├── Middleware: EnsureOfficeIsOperational, EnsureUserIsIctOfficer, InjectOrganizationUi
│   └── Observers: IctJurisdictionObserver
│
├── geo-areas/             ← LAYER 3: Geographic Reference Data
│   ├── Models: GeoArea
│   ├── Services: GeoAreaService
│   ├── Controllers: GeoAreaController
│   └── (No observers, middleware, events)
│
└── custom-requests/       ← LAYER 5-7: E-Commerce & Fulfillment
    ├── Models: Request, RequestItem, ApprovalPolicy, ItemRequest, RequestEvent
    │           (LocationRole — commented out / inactive)
    ├── Services: RequestService, ApprovalService, BasketService,
    │             CatalogService, FulfillmentService, PolicyService
    ├── Adapters: AssetAdapter, AccessoryAdapter, ConsumableAdapter
    ├── Factories: RequestableFactory
    ├── DTOs/Contracts: CatalogItem (DTO), RequestableInterface (contract)
    ├── Events/Listeners: ItemApproved → ProcessItemCheckout
    ├── Controllers: GovRequestController, GovApprovalController,
    │                  BasketController, GovFulfillmentController
    └── Middleware: InjectGovStoreUi (referenced but MISSING on disk)
```

---

## 3. Package 1: Tenant-Scope

**Path:** `packages/gov-store/tenant-scope/src/`  
**Namespace:** `GovStore\TenantScope`  
**Description:** The foundational multi-tenant query isolation and permission resolution engine. Evaluates authority at runtime during every HTTP request lifecycle.

### 3.1 Models (2)

| Model | Table | Purpose |
|-------|-------|---------|
| `TenantScopeConfig` | `gov_tenant_scopes` | Configures scope strategy per reference type (global/company/office) |
| `TenantScopeMapping` | `gov_tenant_scope_mappings` | Polymorphic mappings linking reference items to company/location scopes |

### 3.2 Services (7)

| Service | Responsibility |
|---------|----------------|
| `AssignmentResolver` | Resolves active role slug from OfficeResponsibility pivot or LocationProfile office_admin_id |
| `BoundaryResolver` | Determines scope strategy (global/company/location/jurisdiction) based on user status and context |
| `CapabilityProfileResolver` | Maps role slug → EffectivePermissionSet by merging base + profile permissions from config |
| `EffectivePermissionSet` (DTO) | Holds resolved permissions, role slug, profile slug; provides `has()` check |
| `ReferenceOwnershipService` | Queries scope mappings to determine ownership state of any model |
| `SnipePermissionAdapter` | Translates EffectivePermissionSet → Snipe-IT native permission JSON; clears Sentinel cache |
| `TenantBoundaryService` | Entry point for boundary verification; orchestrates policy checks, relationship integrity, mutation validation |

### 3.3 Controllers (1)

| Controller | Endpoints | Access |
|------------|-----------|--------|
| `TenantScopeController` | Admin scope config UI, save strategy, reference search, tenant search, mapping CRUD | Superadmin only |

### 3.4 Contexts (1)

| Context | Purpose |
|---------|---------|
| `TenantContext` | Singleton hydrated per request; stores locationId, companyId, membershipId, allowedLocationIds hierarchy, effectivePermissions, scope configs |

### 3.5 Eloquent Scopes (3)

| Scope | Applied To | Behavior |
|-------|-----------|----------|
| `MinistryLocationScope` | Asset, Consumable, Accessory, Component, License | Filters by company_id + location_id from TenantContext |
| `TenantScope` | Category, AssetModel, Supplier, Manufacturer, Location | Uses allowedLocationIds or catalog mapping strategy |
| `UserScope` | User model | Filters by allowedLocationIds; includes self-query bypass to prevent login loops |

### 3.6 Policies (4)

| Policy | Purpose |
|--------|---------|
| `AssetBoundaryPolicy` | Checks company_id + location_id match context for asset mutations |
| `CategoryBoundaryPolicy` | Enforces ownership rules: GLOBAL→deny, COMPANY→check owner, LOCATION→check owner |
| `ReferenceBoundaryPolicy` | Same as Category but explicitly allows USE of global items without MUTATION |
| `TransactionalBoundaryPolicy` | Dynamic column check on any model's table for company_id/location_id |

### 3.7 Middleware (3)

| Middleware | Group | Responsibility |
|------------|-------|----------------|
| `InitializeTenantContext` | web + api | Core IAM layer: resolves context, computes hierarchy, injects permissions into User model |
| `InjectTenantScopeUi` | web | Injects "Tenant Scoping" sidebar link for superadmins |
| `HandleBoundaryExceptions` | (manual) | Catches TenantBoundaryException → JSON 403 or flash redirect |

### 3.8 Observers (1)

| Observer | Events | Applied To |
|----------|--------|------------|
| `TenantMutationObserver` | creating, updating, deleting | All operational + reference models |

### 3.9 Config

| File | Key Content |
|------|-------------|
| `config/permissions.php` | Responsibilities→profiles mapping; profile permission arrays (inventory_operator, workflow_operator, office_operations, ict_operations, employee) |

### 3.10 Routes (6)

All under `gov-store/admin/scope/` with `web + auth`:

| HTTP | Endpoint | Route Name |
|------|----------|------------|
| GET | `/` | `gov.scope.index` |
| POST | `/save-strategy` | `gov.scope.save-strategy` |
| GET | `/reference-search` | `gov.scope.reference-search` |
| GET | `/tenant-search` | `gov.scope.tenant-search` |
| POST | `/mappings/store` | `gov.scope.mappings.store` |
| POST | `/mappings/delete/{id}` | `gov.scope.mappings.destroy` |

### 3.11 Migrations (1)

| File | Tables Created |
|------|----------------|
| `2024_01_06_000000_create_gov_tenant_scope_tables.php` | `gov_tenant_scopes`, `gov_tenant_scope_mappings` + seed defaults |

---

## 4. Package 2: Office-Membership

**Path:** `packages/gov-store/office-membership/src/`  
**Namespace:** `GovStore\OfficeMembership`  
**Description:** Manages office memberships, role assignments, peer-to-peer handshakes, and verification code onboarding. Decouples membership from identity.

### 4.1 Models (6)

| Model | Table | Purpose |
|-------|-------|---------|
| `OfficeMembership` | `gov_office_memberships` | Declares which offices a user can access; is_home_office flag |
| `RoleAssignment` | `gov_role_assignments` | LocationProfile-based role transfer proposals |
| `OfficeResponsibility` | `gov_office_responsibilities` | Pivot matrix for local office duties (storekeeper, approver, etc.) |
| `RoleHandshake` | `gov_role_handshakes` | Peer-to-peer responsibility delegation with status tracking |
| `EmployeeVerificationToken` | `gov_employee_verification_tokens` | 6-char verification codes for self-join onboarding |
| `OverrideAuditLog` | `gov_override_audit_logs` | Immutable audit trail for administrative overrides |

### 4.2 Services (5)

| Service | Responsibility |
|---------|----------------|
| `ClearanceEngine` | Orchestrates clearance validation rules stack; registers IClearanceRule checks |
| `ClearanceResult` (DTO) | Result payload: isPassed + reason |
| `OfficeMembershipService` | CRUD for memberships: grant, revoke, get active users, get user memberships |
| `RoleAssignmentService` | LocationProfile-based role transfer: propose, accept, reject, cancel |
| `RoleHandshakeService` | OfficeResponsibility pivot handshakes: propose, accept, reject, cancel |
| `LegacyUserSynchronizationService` | Bridges Snipe-IT User model changes to membership engine on create/update |

### 4.3 Controllers (4)

| Controller | Endpoints | Access |
|------------|-----------|--------|
| `MembershipController` | My memberships dashboard, request release, switch context, generate token, join by code | Self-service (authenticated user) |
| `RoleHandshakeController` | Propose/accept/reject/cancel peer delegation | Office staff |
| `MembershipAdminController` | Staff hub, add employee by token, invite codes, approve/reject memberships, claim transfer, override console | Office admin + superadmin |
| `RoleAssignmentController` | Propose/accept/reject/cancel role transfer (LocationProfile-based) | **ORPHANED — no routes registered** |

### 4.4 Middleware (2)

| Middleware | Group | Responsibility |
|------------|-------|----------------|
| `InjectMembershipUi` | web | Injects "My Office Memberships" link + context switcher in navbar via JS injection |
| `SetWorkingContext` | web | Initializes session context; resolves home membership; self-heals location_id drift |

### 4.5 Observers (2)

| Observer | Model | Events | Behavior |
|----------|-------|--------|----------|
| `MembershipActivityLogObserver` | OfficeMembership | created, updated, deleted | Logs to OrganizationActivityLog |
| `UserSyncObserver` | User (core Snipe-IT) | created, updated | Auto-creates home membership; evaluates transfer clearance on location change |

### 4.6 Contracts/Interfaces (1)

| Contract | Methods |
|----------|---------|
| `IClearanceRule` | getName(), check(User, locationId): ClearanceResult |

### 4.7 Console Commands (1)

| Command | Description |
|---------|-------------|
| (exists in directory) | Details not audited — file structure confirmed present |

### 4.8 Routes (partial — see audit report for full list)

All under `gov-store/` prefix with `web + auth`:

| HTTP | Endpoint | Controller |
|------|----------|------------|
| GET | `/my-memberships/` | MembershipController@index |
| POST | `/my-memberships/{id}/request-release` | MembershipController@requestRelease |
| POST | `/my-memberships/switch` | MembershipController@switchContext |
| POST | `/my-memberships/token/generate` | MembershipController@generateVerificationToken |
| POST | `/my-memberships/join` | MembershipController@joinByCode |
| POST | `/my-memberships/handshake/propose` | RoleHandshakeController@propose |
| POST | `/my-memberships/handshake/{id}/accept` | RoleHandshakeController@accept |
| POST | `/my-memberships/handshake/{id}/reject` | RoleHandshakeController@reject |
| POST | `/my-memberships/handshake/{id}/cancel` | RoleHandshakeController@cancel |
| GET | `/office/staff/` | MembershipAdminController@index |
| POST | `/office/staff/add-employee` | MembershipAdminController@addEmployeeByToken |
| POST | `/office/staff/generate-invite-code` | MembershipAdminController@generateInviteCode |
| POST | `/office/staff/approve/{id}` | MembershipAdminController@approveMembership |
| POST | `/office/staff/reject/{id}` | MembershipAdminController@rejectMembership |
| POST | `/office/staff/claim` | MembershipAdminController@claimEmployee |
| GET | `/admin/memberships/override/console` | MembershipAdminController@overrideConsole |
| POST | `/admin/memberships/override/force` | MembershipAdminController@forceOverride |

### 4.9 Migrations (2)

| File | Tables Created |
|------|----------------|
| Migration 1 | `gov_office_memberships`, `gov_employee_verification_tokens` |
| Migration 2 | `gov_office_responsibilities`, `gov_role_assignments`, `gov_role_handshakes`, `gov_override_audit_logs` |

### 4.10 Seeds (1)

| Seeder | Purpose |
|---------|---------|
| OfficeMembershipSeeder | Seeds initial office membership data |

---

## 5. Package 3: Organization

**Path:** `packages/gov-store/organization/src/`  
**Namespace:** `GovStore\Organization`  
**Description:** Office provisioning, geo-verification, ICT jurisdiction management, and office lifecycle tracking.

### 5.1 Models (4)

| Model | Table | Purpose |
|-------|-------|---------|
| `IctJurisdiction` | `gov_ict_jurisdictions` | Maps user to geographic boundary as ICT Officer |
| `LocationProfile` | `gov_location_profiles` | Bridges Snipe-IT Location with geo-area metadata + lifecycle state |
| `LocationRole` | `gov_location_roles` | Legacy role assignment table (delegates with expiry) — deprecated per architecture spec |
| `OrganizationActivityLog` | `gov_organization_activity_logs` | Immutable audit trail for office operations |

### 5.2 Services (3)

| Service | Responsibility |
|---------|----------------|
| `OfficeConfigurationService` | Atomically saves roles to OfficeResponsibility pivot; logs changes; triggers readiness re-evaluation |
| `OfficeProvisioningService` | Creates Snipe-IT Location + LocationProfile + LocationRole; enforces ICT Officer geo-jurisdiction; checks duplicates |
| `OfficeReadinessService` | Evaluates 4-item checklist (admin, approver, storekeeper, users); transitions to operational when all true |

### 5.3 Controllers (4)

| Controller | Endpoints | Access |
|------------|-----------|--------|
| `ConfigurationController` | Office config page, save roles | Office Admin (via LocationProfile office_admin_id) |
| `OfficeHubController` | Office hub dashboard, update metadata, verify geo, save roles | ICT Officer + Office Admin |
| `OnboardLocationController` | Onboard existing unprovisioned locations | ICT Officer only |
| `ProvisioningController` | Master office registry, provision new office, duplicate detection, ICT jurisdiction management | Superadmin + ICT Officer |

### 5.4 Middleware (3)

| Middleware | Group | Responsibility |
|------------|-------|----------------|
| `EnsureOfficeIsOperational` | web | Blocks catalog/basket access for non-operational offices; shows waiting page |
| `EnsureUserIsIctOfficer` | (manual) | Verifies active IctJurisdiction record; aborts 403 if not |
| `InjectOrganizationUi` | web | Injects "Office Setup" sidebar link via JS injection |

### 5.5 Observers (1)

| Observer | Model | Events | Behavior |
|----------|-------|--------|----------|
| `IctJurisdictionObserver` | IctJurisdiction | created, deleted | Auto-attaches/detaches user to Snipe-IT "ICT Operations" group |

### 5.6 ViewModels (1)

| ViewModel | Purpose |
|-----------|---------|
| `OfficeRegistryViewModel` | DTO-like view model for office registry listing |

### 5.7 Routes (partial — see audit report for full list)

All under `gov-store/admin/organization/` or `gov-store/office`:

| HTTP | Endpoint | Controller |
|------|----------|------------|
| GET | `/office` | ConfigurationController@index |
| POST | `/office/save` | ConfigurationController@save |
| GET | `/admin/organization/{id}/hub` | OfficeHubController@show |
| POST | `/admin/organization/{id}/update` | OfficeHubController@update |
| POST | `/admin/organization/{id}/save-roles` | OfficeHubController@saveRoles |
| POST | `/admin/organization/{id}/verify-geo` | OfficeHubController@verifyGeo |
| GET | `/admin/organization/onboard` | OnboardLocationController@create |
| POST | `/admin/organization/onboard/store` | OnboardLocationController@store |
| GET | `/admin/organization/` | ProvisioningController@index |
| GET | `/admin/organization/create` | ProvisioningController@create |
| POST | `/admin/organization/store` | ProvisioningController@provision |
| GET | `/admin/organization/geo-search` | ProvisioningController@geoSearch |
| GET | `/admin/organization/check-duplicate` | ProvisioningController@checkDuplicate |
| GET | `/admin/organization/jurisdictions` | ProvisioningController@jurisdictionsIndex |
| POST | `/admin/organization/jurisdictions/store` | ProvisioningController@jurisdictionsStore |
| POST | `/admin/organization/jurisdictions/delete/{id}` | ProvisioningController@jurisdictionsDestroy |
| POST | `/admin/organization/assign-admin` | ProvisioningController@assignAdmin |

### 5.8 Migrations (2)

| File | Tables Created |
|------|----------------|
| Migration 1 | `gov_location_profiles`, `gov_ict_jurisdictions` |
| Migration 2 | `gov_location_roles` (legacy), `gov_organization_activity_logs` |

---

## 6. Package 4: Geo-Areas

**Path:** `packages/gov-store/geo-areas/src/`  
**Namespace:** `GovStore\GeoAreas`  
**Description:** Bangladesh geographic reference library — Division → District → Upazilla → Union hierarchy.

### 6.1 Models (1)

| Model | Table | Purpose |
|-------|-------|---------|
| `GeoArea` | `gov_geo_areas` | Flat geographic reference data; hierarchy resolved via `hid` string prefix matching |

**Key attributes:** GeoAreaId (PK), hid (hierarchical path), geo_type, geo_code, parent_geo_code, bn_name, en_name, GeoLevel, domain

### 6.2 Services (1)

| Service | Responsibility |
|---------|----------------|
| `GeoAreaService` | CRUD proxy, hierarchical path resolution, geospatial boundary checking, full-text search |

### 6.3 Controllers (1)

| Controller | Endpoint | Purpose |
|------------|----------|---------|
| `GeoAreaController` | `GET gov-store/api/geo/search` | Select2-compatible geographic search API |

### 6.4 Middleware / Observers / Events — NONE

This is a pure data/reference package with no lifecycle hooks.

### 6.5 Routes (1)

| HTTP | Endpoint | Route Name |
|------|----------|------------|
| GET | `gov-store/api/geo/search` | `gov.geo.search` |

### 6.6 Migrations (1)

| File | Tables Created | Data |
|------|----------------|------|
| `2024_01_05_000000_create_gov_geo_areas_table.php` | `gov_geo_areas` | Auto-imports Bangladesh geo CSV (9 columns, batches of 250) |

### 6.7 Data File

| File | Content |
|------|---------|
| `database/data/geo_areas.csv` | Full Bangladesh geographic hierarchy dataset |

---

## 7. Package 5: Custom-Requests

**Path:** `packages/gov-store/custom-requests/src/`  
**Namespace:** `GovStore\CustomRequests`  
**Description:** Government e-commerce extension — item requests, approval workflows, shopping basket, fulfillment, and checkout.

### 7.1 Models (6; 1 inactive)

| Model | Table | Purpose |
|-------|-------|---------|
| `Request` | `custom_service_requests` | Parent service request with auto-generated sequential numbers (SR-YYYY-000001) |
| `RequestItem` | `custom_service_request_items` | Line items with polymorphic catalog references + fulfillment tracking |
| `ApprovalPolicy` | `gov_approval_policies` | Approval policy per category/model (PRIMARY_ONLY, PRIMARY_AND_FINAL, AUTO_APPROVE) |
| `ItemRequest` | `custom_item_requests` | Single-item request model with pending scope |
| `RequestEvent` | `custom_service_request_events` | Event log for request lifecycle changes |
| ~~`LocationRole`~~ | `gov_location_roles` | **COMMENTED OUT / INACTIVE** — deprecated per architecture spec |

### 7.2 Services (6)

| Service | Responsibility |
|---------|----------------|
| `RequestService` | Submit single-item request; prevents duplicate pending requests |
| `ApprovalService` | Process approval/rejection; handles policy types; self-approval conflict detection |
| `BasketService` | Draft basket CRUD; submit basket → one or more Service Requests grouped by approval policy |
| `CatalogService` | Aggregates all requestable items across Asset, Accessory, Consumable, Component, License |
| `FulfillmentService` | Issue items + substitutions; trigger Snipe-IT checkout; close/force-close requests |
| `PolicyService` | Resolve approval policy: Direct Override → Category Inheritance → Global Default |

### 7.3 Adapters (3) — implement `RequestableInterface`

| Adapter | Wraps | Checkout Logic |
|---------|-------|----------------|
| `AssetAdapter` | Asset | Sets assigned_to, logged checkout |
| `AccessoryAdapter` | Accessory | Attach user via pivot table + log checkout |
| `ConsumableAdapter` | Consumable | Attach user via pivot table + log checkout |

### 7.4 Factories (1)

| Factory | Method | Purpose |
|---------|--------|---------|
| `RequestableFactory` | `make(type, id)` | Returns correct adapter based on item type; throws for unsupported types |

### 7.5 DTOs / Contracts (2)

| Type | Name | Purpose |
|------|------|---------|
| DTO | `CatalogItem` | Item display data: type, id, name, category, available_qty, image_url, details |
| Contract | `RequestableInterface` | getModel(), getDisplayName(), getType(), getAvailableQuantity(), checkout() |

### 7.6 Events / Listeners (1 pair)

| Event | Listener | Behavior |
|-------|----------|----------|
| `ItemApproved` | `ProcessItemCheckout` | Uses RequestableFactory to get adapter, triggers Snipe-IT checkout automatically |

### 7.7 Controllers (4)

| Controller | Endpoints | Access |
|------------|-----------|--------|
| `GovRequestController` | My requests, catalog browse, submit request, catalog search | Authenticated user |
| `GovApprovalController` | Pending requests list, show details, process decisions, policy management | Approver + admin |
| `BasketController` | View/edit basket, add/update/remove items, submit basket | Authenticated user |
| `GovFulfillmentController` | Fulfillment queue, show details, issue items, close request | Storekeeper |

### 7.8 Middleware (1 referenced, 0 on disk)

| Middleware | Status | Responsibility |
|------------|--------|----------------|
| `InjectGovStoreUi` | **MISSING FILE** | Would inject "My Gov-Store" UI hooks into navbar/sidebar |

### 7.9 Routes (18)

All under `/gov-requests/` prefix with `web + auth`:

| HTTP | Endpoint | Controller |
|------|----------|------------|
| POST | `/submit` | GovRequestController@store |
| GET | `/catalog` | GovRequestController@catalog |
| GET | `/my-requests` | GovRequestController@index |
| GET | `/catalog/search` | GovRequestController@search |
| GET | `/admin` | GovApprovalController@index |
| GET | `/admin/{id}` | GovApprovalController@show |
| POST | `/admin/{id}/process` | GovApprovalController@process |
| GET | `/admin/settings/policies` | GovApprovalController@policiesIndex |
| POST | `/admin/settings/policies/store` | GovApprovalController@policiesStore |
| GET | `/basket` | BasketController@index |
| POST | `/basket/add` | BasketController@add |
| POST | `/basket/update` | BasketController@updateQty |
| POST | `/basket/remove/{id}` | BasketController@remove |
| POST | `/basket/submit` | BasketController@submit |
| GET | `/fulfillment` | GovFulfillmentController@index |
| GET | `/fulfillment/{id}` | GovFulfillmentController@show |
| POST | `/fulfillment/{id}/issue` | GovFulfillmentController@process |
| POST | `/fulfillment/{id}/close` | GovFulfillmentController@close |

### 7.10 Migrations (3)

| File | Tables Created |
|------|----------------|
| `2024_01_01_000000_create_custom_item_requests_table.php` | `custom_item_requests` |
| `2024_01_02_000000_create_service_requests_tables.php` | `custom_service_requests`, `custom_service_request_items`, `custom_service_request_events` |
| `2024_01_03_000000_create_gov_approval_policy_tables.php` | `gov_location_roles`, `gov_approval_policies`; alters `custom_service_requests` |

### 7.11 Views (9 Blade templates)

| Directory | Files |
|-----------|-------|
| `admin/` | index.blade.php, show.blade.php, policies.blade.php, locations.blade.php |
| `basket/` | index.blade.php |
| `catalog/` | index.blade.php |
| `fulfillment/` | index.blade.php, show.blade.php |
| `user/` | index.blade.php |
| `hooks/` | menu-injection.blade.php |
| `components/` | request-button.blade.php |

---

## 8. Cross-Package Dependencies

### Dependency Graph

```
                    ┌─────────────────┐
                    │   Geo-Areas     │  ← LEAF: No dependencies on other packages
                    └────────┬────────┘
                             │ (used by)
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
    ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
    │ Organization │ │Office-Member-│ │Custom-       │
    │              │ │ship          │ │Requests      │
    └──────┬───────┘ └──────┬───────┘ └──────┬───────┘
           │                │                │
           └────────────────┼────────────────┘
                            │
                            ▼
                  ┌─────────────────┐
                  │  Tenant-Scope   │  ← FOUNDATION: Used by all packages
                  │                 │  Implements Layer 1-7 IAM engine
                  └─────────────────┘
```

### Package Dependency Matrix

| Package | Depends On | Used By |
|---------|-----------|---------|
| `geo-areas` | None (leaf) | organization, tenant-scope |
| `office-membership` | geo-areas (indirect via LocationProfile) | tenant-scope, organization |
| `organization` | geo-areas, office-membership | tenant-scope |
| `tenant-scope` | office-membership, organization, geo-areas | **ALL packages** (foundation) |
| `custom-requests` | office-membership (PolicyService) | None (top-level) |

### Shared Models Across Packages

| Model | Package | Referenced By |
|-------|---------|---------------|
| `User` (core Snipe-IT) | Core | All packages |
| `Location` (core Snipe-IT) | Core | All packages |
| `Asset`, `Consumable`, `Accessory`, `License` (core) | Core | tenant-scope, custom-requests |
| `OfficeMembership` | office-membership | tenant-scope, organization |
| `OfficeResponsibility` | office-membership | tenant-scope, organization |
| `LocationProfile` | organization | tenant-scope, office-membership |
| `GeoArea` | geo-areas | organization, tenant-scope |
| `IctJurisdiction` | organization | tenant-scope |

---

## 9. Architecture Patterns & Conventions

### 9.1 The 7-Layer IAM Engine (per Architecture Specification)

| Layer | Component | Package |
|-------|-----------|---------|
| 1-2 | Authenticated Identity | Core Snipe-IT (User model) |
| 3 | SetWorkingContext Middleware | office-membership |
| 3 | InitializeTenantContext Middleware | tenant-scope |
| 4 | AssignmentResolver | tenant-scope |
| 5 | CapabilityProfileResolver | tenant-scope |
| 6 | SnipePermissionAdapter | tenant-scope |
| 7 | Laravel Policies / Gates | tenant-scope (BoundaryPolicies) |

### 9.2 Request Lifecycle Flow

```
HTTP REQUEST
    │
    ▼
SetWorkingContext (office-membership)
    → Resolves active membership_id from session
    │
    ▼
InitializeTenantContext (tenant-scope)
    → Hydrates TenantContext singleton
    → Computes allowedLocationIds hierarchy
    → AssignmentResolver → CapabilityProfileResolver
    → SnipePermissionAdapter injects permissions into User model
    │
    ▼
Middleware Chain (EnsureOfficeIsOperational, EnsureUserIsIctOfficer)
    │
    ▼
Controller Action
    → Laravel Policies validate write mutations
    → TenantMutationObserver intercepts DB writes
    │
    ▼
Response
    → InjectTenantScopeUi / InjectMembershipUi / InjectOrganizationUi
      append UI hooks to HTML
```

### 9.3 Membership Acquisition Workflows (5 Types)

| # | Workflow | users.location_id | is_home_office | Package |
|---|----------|-------------------|----------------|---------|
| 1 | Initial Employment | Yes (Home) | true | office-membership |
| 2 | Additional Membership (Token) | No | false | office-membership |
| 3 | Self-Join (Invite Code) | No | Pending→Active | office-membership |
| 4 | Permanent Transfer (Claim) | Yes (New) | Old→Released, New→Home | office-membership |
| 5 | ICT Administrative Override | Optional | Manual | office-membership |

### 9.4 Query Isolation Strategy

| Scope | Applied To | Source of Truth |
|-------|-----------|-----------------|
| `MinistryLocationScope` | Operational models (Asset, Consumable, etc.) | TenantContext.locationId |
| `TenantScope` | Reference catalogs (Category, Model, etc.) | TenantContext.allowedLocationIds + scope configs |
| `UserScope` | User model | TenantContext.allowedLocationIds + self-bypass |

### 9.5 Design Patterns Used

| Pattern | Where Applied |
|---------|---------------|
| **Singleton** | TenantContext (tenant-scope) |
| **Adapter** | Asset/Accessory/Consumable adapters (custom-requests) |
| **Factory** | RequestableFactory (custom-requests) |
| **Strategy** | ClearanceEngine with IClearanceRule (office-membership) |
| **Observer** | TenantMutationObserver, MembershipActivityLogObserver, UserSyncObserver, IctJurisdictionObserver |
| **DTO** | CatalogItem, EffectivePermissionSet, ClearanceResult, OfficeRegistryViewModel |
| **Policy** | Laravel Policies + custom BoundaryPolicies |
| **Middleware Pipeline** | 6 middleware classes across packages |

---

## 10. Security & IAM Flow

### 10.1 Permission Resolution Chain

```
User has OfficeResponsibility(role_slug='storekeeper')
    → AssignmentResolver resolves role slug
    → CapabilityProfileResolver reads config/permissions.php
    → Merges base 'employee' + 'inventory_operator' profiles
    → EffectivePermissionSet = {assets.view, assets.edit, ...}
    → SnipePermissionAdapter:
        1. Strips user's groups
        2. Overwrites $user->permissions JSON
        3. Clears cached_permissions
        4. Calls Sentinel::setPermissions()
    → Laravel Policies check EffectivePermissionSet on writes
```

### 10.2 Boundary Enforcement

| Layer | Mechanism | Package |
|-------|-----------|---------|
| Query-time | Global Eloquent scopes | tenant-scope |
| Write-time | TenantMutationObserver + BoundaryPolicies | tenant-scope |
| Session-time | SetWorkingContext middleware | office-membership |
| Office-state | EnsureOfficeIsOperational middleware | organization |

### 10.3 Superadmin Bypass

Superadmin users bypass all tenant scoping: `isActive=true, isGlobal=true` in TenantContext. All BoundaryPolicies check superadmin status before enforcing restrictions.

---

## 11. Gaps, Risks & Observations

### 11.1 Missing Files / Orphaned Components

| Item | Package | Severity | Description |
|------|---------|----------|-------------|
| `InjectGovStoreUi` middleware | custom-requests | **HIGH** | Referenced in Service Provider but file does not exist on disk. UI hooks for custom-requests will not render. |
| `RoleAssignmentController` routes | office-membership | **MEDIUM** | Controller exists with methods but NO routes registered in web.php — completely unreachable. |
| `LocationRole` model | custom-requests | **LOW** | Model is commented out / inactive per architecture spec, but migration still creates the table. |

### 11.2 Deprecated Components

| Component | Package | Status |
|-----------|---------|--------|
| `LocationRole` (gov_location_roles table) | office-membership | Deprecated per architecture spec; active roles use OfficeResponsibility pivot instead |
| `LocationRole` model | organization | Deprecated; still exists in migration but superseded by OfficeResponsibility |

### 11.3 Architecture Compliance Observations

| Observation | Package | Recommendation |
|-------------|---------|----------------|
| GeoArea migration drops & recreates table | geo-areas | Not idempotent — risky for production deployments |
| `dd()` in GeoAreaController::search() | geo-areas | Diagnostic debug call should be removed before production |
| No Events/Listeners in 4 of 5 packages | Most | Heavy reliance on Observers — consider if any should use Laravel events for decoupling |
| No config files in custom-requests | custom-requests | Configuration is embedded inline (morphMap, middleware) — consider extracting to config file |
| OfficeResponsibility model exists in both office-membership and organization packages | Both | Potential duplication — verify which is the source of truth |

### 11.4 Security Considerations

| Concern | Details |
|---------|---------|
| Self-query bypass in UserScope | Present and correctly implemented — prevents login redirect loops |
| Superadmin bypass scope | Present in all BoundaryPolicies — required but should be audited regularly |
| No CSRF protection mentioned | Relies on Laravel's built-in CSRF via `web` middleware group |
| Sentinel cache clearing | SnipePermissionAdapter uses native methods — no Reflection usage (compliant with architecture spec) |

### 11.5 Data Flow Summary

```
┌─────────────┐     ┌──────────────┐     ┌───────────────┐
│ Geo-Areas   │────→│ Organization │────→│ Tenant-Scope  │
│ (Reference  │     │ (Provisioning│     │ (IAM Engine   │
│  Data)      │     │  + Lifecycle)│     │  Foundation)  │
└─────────────┘     └──────────────┘     └───────┬───────┘
                                                   │
                    ┌──────────────┐               │
                    │Office-       │←──────────────┘
                    │Membership    │
                    │(Roles +      │
                    │ Memberships) │
                    └──────┬───────┘
                           │
                    ┌──────▼───────┐
                    │Custom-       │
                    │Requests      │
                    │(E-Commerce)  │
                    └──────────────┘
```

---

## Appendix A: Complete Route Inventory

| Package | Routes | Prefix | Middleware |
|---------|--------|--------|------------|
| tenant-scope | 6 | `gov-store/admin/scope/` | web + auth |
| office-membership | ~17 | `gov-store/my-memberships/`, `gov-store/office/staff/`, `gov-store/admin/memberships/` | web + auth |
| organization | ~17 | `gov-store/office/`, `gov-store/admin/organization/` | web + auth |
| geo-areas | 1 | `gov-store/api/geo/` | web + auth |
| custom-requests | 18 | `/gov-requests/` | web + auth |
| **Total** | **~59 endpoints** | | |

## Appendix B: Database Table Inventory

| Table | Package | Purpose |
|-------|---------|---------|
| `gov_geo_areas` | geo-areas | Bangladesh geographic hierarchy |
| `gov_office_memberships` | office-membership | User-office membership records |
| `gov_employee_verification_tokens` | office-membership | Self-join verification codes |
| `gov_office_responsibilities` | office-membership | Role pivot matrix |
| `gov_role_assignments` | office-membership | LocationProfile role transfers |
| `gov_role_handshakes` | office-membership | Peer delegation records |
| `gov_override_audit_logs` | office-membership | Override audit trail |
| `gov_location_profiles` | organization | Office lifecycle state |
| `gov_ict_jurisdictions` | organization | ICT Officer geo boundaries |
| `gov_location_roles` | organization (legacy) | Deprecated role assignments |
| `gov_organization_activity_logs` | organization | Office operation audit trail |
| `gov_tenant_scopes` | tenant-scope | Scope strategy config |
| `gov_tenant_scope_mappings` | tenant-scope | Reference ownership mappings |
| `custom_item_requests` | custom-requests | Single-item requests |
| `custom_service_requests` | custom-requests | Parent service requests |
| `custom_service_request_items` | custom-requests | Request line items |
| `custom_service_request_events` | custom-requests | Request event log |
| `gov_approval_policies` | custom-requests | Approval policy per item |

---

**End of Audit Report**
