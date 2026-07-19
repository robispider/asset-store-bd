# Gov-Store Store Operations Package — Capability & Information Report

**Package Path:** `packages/gov-store/store-operations/`  
**Report Date:** 2026-07-19  
**Scope:** Investigation of all capabilities, data models, services, and interactions for government receipt operations. No code changes.

---

## 1. Executive Summary

The **Gov-Store Store Operations** package is a multi-company, location-scoped inventory management subsystem built on top of Snipe-IT. It provides an immutable audit ledger (Kardex) system with three core document types: **Goods Receipts** (inbound), **Goods Issues** (outbound), and **Stock Adjustments** (corrections). All stock changes flow through a unified `InventoryMovement` ledger, which triggers event-driven projections back to Snipe-IT's native tables.

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Gov-Store Store Ops                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐   ┌──────────────┐   ┌───────────────┐  │
│  │ Goods Receipt│   │ Goods Issue  │   │ Stock Adjust. │  │
│  │ (Inbound)    │   │ (Outbound)   │   │ (Correction)  │  │
│  └──────┬───────┘   └──────┬───────┘   └───────┬───────┘  │
│         │                   │                    │          │
│         └───────────────────┼────────────────────┘          │
│                             ▼                               │
│              ┌────────────────────────┐                     │
│              │ InventoryMovement      │                     │
│              │ (Immutable Audit Ledger)│                    │
│              └────────────┬───────────┘                     │
│                           │                                 │
│               ┌───────────┼───────────┐                    │
│               ▼           ▼           ▼                    │
│   UpdateSnipeQty  WriteAuditLogs  (Future Listeners)       │
│               │           │                               │
│               ▼           ▼                                │
│         consumables.qty  Actionlog table                   │
└─────────────────────────────────────────────────────────────┘
```

### Key Design Patterns
- **Event-Driven Projection:** `InventoryMovementCreated` event triggers quantity updates and audit log writes.
- **Polymorphic Stockables:** All stockable entities (Consumable, Accessory, Component) are handled via a unified factory pattern.
- **Tenant Scoping:** Every model uses `MinistryLocationScope` to enforce physical boundary isolation.
- **Document Lifecycle:** DRAFT -> SUBMITTED (irreversible — no cancel flow exists).

---

## 3. Database Schema

### 3.1 Goods Receipts (`gov_goods_receipts`)

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID (PK) | Primary key |
| `receipt_no` | string (unique) | Auto-generated: `GR-2026-000001` format |
| `supplier_id` | unsigned int (nullable) | Supplier reference |
| `purchase_type` | string | **CASH** / **TENDER** / **DONATION** / **TRANSFER** |
| `reference_no` | string (nullable) | Invoice/Memo number |
| `reference_date` | date (nullable) | Reference document date |
| `received_by_type` | string | **SELF** / **EMPLOYEE** / **COMMITTEE** |
| `committee_ref` | string (nullable) | Committee reference for committee receipts |
| `status` | string | **DRAFT** / **SUBMITTED** / **CANCELLED** |
| `company_id` | unsigned int | Tenant company scope |
| `location_id` | unsigned int | Tenant location scope |
| `created_by` | unsigned int | User who created the document |

### 3.2 Goods Receipt Items (`gov_goods_receipt_items`)

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Primary key |
| `goods_receipt_id` | UUID (FK) | Reference to receipt header |
| `stockable_type` | string | Polymorphic type (`App\Models\Consumable`, etc.) |
| `stockable_id` | unsigned int | Polymorphic ID |
| `quantity` | integer | Quantity received |
| `unit_cost` | decimal(15,2) (nullable) | Cost per unit (for accounting) |

### 3.3 Goods Issues (`gov_goods_issues`)

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID (PK) | Primary key |
| `issue_no` | string (unique) | Auto-generated: `GI-2026-000001` format |
| `issue_type` | string | **TO_USER** / **TO_DEPARTMENT** / **SYSTEM_FULFILLMENT** |
| `issued_to_id` | unsigned int (nullable) | Target employee/department ID |
| `reference_type` | string (nullable) | Polymorphic reference type |
| `reference_id` | unsigned int (nullable) | Polymorphic reference ID |
| `status` | string | **DRAFT** / **SUBMITTED** / **CANCELLED** |
| `company_id` | unsigned int | Tenant company scope |
| `location_id` | unsigned int | Tenant location scope |
| `created_by` | unsigned int | User who created the document |

### 3.4 Goods Issue Items (`gov_goods_issue_items`)

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Primary key |
| `goods_issue_id` | UUID (FK) | Reference to issue header |
| `stockable_type` | string | Polymorphic type |
| `stockable_id` | unsigned int | Polymorphic ID |
| `quantity` | integer | Quantity issued |

### 3.5 Stock Adjustments (`gov_stock_adjustments`)

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID (PK) | Primary key |
| `adjustment_no` | string (unique) | Auto-generated: `ADJ-2026-000001` format |
| `adjustment_type` | string | **PHYSICAL_COUNT** / **DAMAGE** / **LOSS** / **EXPIRED** / **CORRECTION** |
| `remarks` | text (nullable) | Reason for adjustment |
| `status` | string | **DRAFT** / **SUBMITTED** / **CANCELLED** |
| `company_id` | unsigned int | Tenant company scope |
| `location_id` | unsigned int | Tenant location scope |
| `created_by` | unsigned int | User who created the document |

### 3.6 Stock Adjustment Items (`gov_stock_adjustment_items`)

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Primary key |
| `stock_adjustment_id` | UUID (FK) | Reference to adjustment header |
| `stockable_type` | string | Polymorphic type |
| `stockable_id` | unsigned int | Polymorphic ID |
| `direction` | string | **IN** / **OUT** |
| `quantity` | integer | Absolute quantity |

### 3.7 Inventory Movements (`gov_inventory_movements`) — THE IMMUTABLE LEDGER

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID (PK) | Primary key |
| `stockable_type` | string | Polymorphic type of the stockable item |
| `stockable_id` | unsigned int | Polymorphic ID |
| `movement_type` | string | **IN** / **OUT** |
| `quantity` | integer | Absolute quantity (never negative) |
| `balance_after` | integer (nullable) | Running balance after this movement |
| `document_type` | string | Polymorphic document class |
| `document_id` | UUID | Polymorphic document ID |
| `company_id` | unsigned int (nullable) | Tenant company scope |
| `location_id` | unsigned int (nullable) | Tenant location scope |
| `created_by` | unsigned int | User who triggered the movement |
| `created_at` | timestamp | Immutable — no `updated_at` |

---

## 4. Stockable Types Supported

The package supports exactly **three** stockable types via the `StockableType` enum:

| Enum Value | PHP Class | Database Table | Adapter Class |
|------------|-----------|----------------|---------------|
| `CONSUMABLE` | `App\Models\Consumable` | `consumables` | `ConsumableAdapter` |
| `ACCESSORY` | `App\Models\Accessory` | `accessories` | `AccessoryAdapter` |
| `COMPONENT` | `App\Models\Component` | `components` | `ComponentAdapter` |

**Not supported:** Assets, Licenses, Kits — these are explicitly ignored by the ledger system.

---

## 5. Receipt-Specific Capabilities (Inbound)

### 5.1 What a Government Can Produce via Goods Receipt

A government storekeeper can create and submit a **Goods Receipt Note (GRN)** that captures:

#### Header-Level Information
- **Receipt Number:** Auto-generated sequential number (`GR-YYYY-NNNNNN`)
- **Purchase Type:** One of four types:
  - `CASH` — Cash Purchase / Direct
  - `TENDER` — Tender / RFQ
  - `TRANSFER` — Office Transfer
  - (Schema supports `DONATION` but no UI option)
- **Reference Number:** Invoice or memo number (user-provided)
- **Received By Type:** One of:
  - `SELF` — Self-received
  - `EMPLOYEE` — Received by specific employee
  - `COMMITTEE` — Received by committee
- **Committee Reference:** For committee receipts
- **Tenant Context:** Company ID and Location ID (auto-injected)

#### Line-Level Information
- **Stockable Item:** Select from available Consumables (via dropdown/select2)
- **Quantity:** Integer quantity received
- **Unit Cost:** Available in schema (nullable decimal) — **not captured in current UI**

### 5.2 Current Implementation Status

| Capability | Implemented | Notes |
|------------|:-----------:|-------|
| Create receipt form (UI) | Yes | Blade view with static dropdowns |
| Purchase type selection | Yes | CASH, TENDER, TRANSFER options |
| Reference number input | Yes | Text field |
| Multi-line items | Yes | Dynamic row add/remove via JS |
| Stockable selection | Partial | Hardcoded to `Consumable` only; static dropdown (no AJAX/select2) |
| Unit cost capture | No | Schema supports it, but UI omits it |
| Supplier ID | No | Schema column exists but not used in controller |
| Received by type | No | Hardcoded to `SELF` in controller |
| Committee reference | No | Schema column exists but not used |
| Draft save (no submit) | No | Always auto-submitted on store |
| Receipt listing/viewing | No | Only create flow exists |
| Receipt editing | No | Not implemented |
| Receipt cancellation | No | Status CANCELLED exists in schema but no code path |
| Auto-submit on creation | Yes | Controller calls `submit()` immediately after creating DRAFT |
| Ledger movement generation | Yes | IN movements with balance_after computed |
| Snipe-IT qty projection | Yes | Via event listener (atomic increment) |
| Native audit log writing | Yes | Actionlog entries created |

### 5.3 Receipt Submission Flow

```
1. User fills form → POST /gov-store/operations/receipts/store
2. Controller validates input
3. GoodsReceipt created with status=DRAFT + receipt_no generated
4. GoodsReceiptItem lines created (hardcoded to Consumable::class)
5. GoodsReceiptService::submit() called immediately
6. Inside submit():
   a. Status changed to SUBMITTED
   b. For each item:
      - Latest balance read from InventoryMovement ledger
      - New balance = latest + item.quantity
      - InventoryMovement record created (movement_type=IN)
      - InventoryMovementCreated event fired
7. Event listeners execute:
   a. UpdateSnipeQuantity → atomic increment on consumables.qty
   b. WriteNativeAuditLogs → Actionlog entry created
8. Redirect back with success message
```

---

## 6. Issue-Specific Capabilities (Outbound)

### 6.1 What a Government Can Produce via Goods Issue

| Capability | Implemented | Notes |
|------------|:-----------:|-------|
| Create issue form (UI) | Yes | Blade view with user/item dropdowns |
| Issue type selection | Yes | TO_USER / TO_DEPARTMENT |
| Issued to employee | Yes | User dropdown filtered by location |
| Multi-line items | Yes | Dynamic row add/remove via JS |
| Stockable selection | Partial | Hardcoded to Consumable only |
| Draft save (no submit) | No | Always auto-submitted on store |
| Issue listing/viewing | No | Only create flow exists |
| Issue editing | No | Not implemented |
| Pre-submission stock check | Yes | Prevents negative stock |

### 6.2 Issue Submission Flow

```
1. User fills form → POST /gov-store/operations/issues/store
2. Controller validates input
3. GoodsIssue created with status=DRAFT + issue_no generated
4. GoodsIssueItem lines created (hardcoded to Consumable::class)
5. GoodsIssueService::submit() called immediately
6. Inside submit():
   a. Pre-validation: checks current qty >= requested qty for each item
   b. Status changed to SUBMITTED
   c. For each item:
      - InventoryMovement record created (movement_type=OUT, no balance_after)
      - InventoryMovementCreated event fired
7. Event listeners execute:
   a. UpdateSnipeQuantity → atomic decrement on consumables.qty
   b. WriteNativeAuditLogs → Actionlog entry created
8. Redirect back with success/error message
```

---

## 7. Stock Adjustment Capability (Schema-Defined, Not Implemented)

| Aspect | Status |
|--------|:------:|
| Database schema | Defined |
| Model classes | `StockAdjustment`, `StockAdjustmentItem` |
| Controller | **Not implemented** |
| Service layer | **Not implemented** |
| Views | **Not implemented** |
| Routes | **Not registered** |

The schema and models exist but there is no operational code path for stock adjustments.

---

## 8. Services & Business Logic

### 8.1 GoodsReceiptService
- **Method:** `submit(GoodsReceipt $receipt): void`
- **Validations:** Checks document not already submitted, checks items not empty
- **Transaction scope:** DB transaction wraps all operations
- **Balance computation:** Reads latest `balance_after` from ledger, adds quantity
- **Post-submission:** Fires `InventoryMovementCreated` event for each line

### 8.2 GoodsIssueService
- **Method:** `submit(GoodsIssue $issue): void`
- **Validations:** Checks document not already submitted, checks items not empty, **pre-validates stock availability** (prevents negative stock)
- **Transaction scope:** DB transaction wraps all operations
- **Balance computation:** Does NOT compute `balance_after` for OUT movements (gap)
- **Post-submission:** Fires `InventoryMovementCreated` event for each line

### 8.3 InventoryLedgerService
- **Method:** `getKardexFor(string $modelClass, int $id, int $limit = 100): Collection`
- **Purpose:** Retrieves chronological movements for a specific stockable entity
- **Eager loads:** `document` (morphTo), `creator` (belongsTo User)

### 8.4 DocumentNumberService
- **Method:** `generate(string $prefix, string $table, string $column): string`
- **Format:** `{PREFIX}-{YYYY}-{NNNNNN}` (e.g., `GR-2026-000001`)
- **Logic:** Queries max existing number for current year, increments by 1

### 8.5 SystemGoodsIssueService
- **Interface:** `StockIssuingServiceInterface`
- **Method:** `issueSystemStock(array $items, int $issuedToUserId, $referenceDocument): array`
- **Purpose:** External package integration — accepts raw polymorphic types, classifies them, and processes SYSTEM_FULFILLMENT Goods Issues
- **Classification:** Uses `StockableType::fromString()` to validate stockable types; non-stockable types (Assets, Licenses) are silently ignored
- **Auto-submitted:** Creates GoodsIssue with status=SUBMITTED directly

---

## 9. Adapters & Stockable Abstraction

### 9.1 StockableInterface
```php
interface StockableInterface {
    getCurrentQuantity(): int;
    incrementQuantity(int $qty): void;
    decrementQuantity(int $qty): void;
    getDisplayName(): string;
}
```

### 9.2 Adapter Implementations

| Adapter | Table | Increment Method | Decrement Guard |
|---------|-------|-----------------|-----------------|
| `ConsumableAdapter` | `consumables` | `DB::table('consumables')->increment('qty', $qty)` | Checks current qty >= qty |
| `AccessoryAdapter` | `accessories` | `DB::table('accessories')->increment('qty', $qty)` | Checks current qty >= qty |
| `ComponentAdapter` | `components` | `DB::table('components')->increment('qty', $qty)` | Checks current qty >= qty |

All adapters use **atomic DB queries** (`increment`/`decrement`) to bypass Snipe-IT observers and avoid double-firing events.

### 9.3 StockableFactory
Resolves the correct adapter from either a `StockableType` enum or a raw string (via `StockableType::fromString()` which handles FQCN, basename, and lowercase morph variations).

---

## 10. Event System

### 10.1 InventoryMovementCreated Event
- **Payload:** `InventoryMovement` model instance
- **Dispatched:** After each movement record is persisted in a DB transaction

### 10.2 Listeners

| Listener | Responsibility | Error Handling |
|----------|---------------|----------------|
| `UpdateSnipeQuantity` | Atomic increment/decrement on Snipe-IT tables based on movement_type | Logs critical error, re-throws (fails the transaction) |
| `WriteNativeAuditLogs` | Creates `Actionlog` entry with readable note referencing document number and balance | Catches and logs errors silently (non-fatal) |

---

## 11. Routes & Controllers

### 11.1 Registered Routes (prefix: `gov-store/operations`)

| Method | Route | Controller@Method | Name |
|--------|-------|-------------------|------|
| GET | `/receipts/create` | GoodsReceiptController@create | `storeops.receipts.create` |
| POST | `/receipts/store` | GoodsReceiptController@store | `storeops.receipts.store` |
| POST | `/receipts/{id}/submit` | GoodsReceiptController@submit | `storeops.receipts.submit` |
| GET | `/register` | StockRegisterController@index | `storeops.register.index` |
| GET | `/kardex/{type}/{id}` | StockRegisterController@kardex | `storeops.register.kardex` |
| GET | `/issues/create` | GoodsIssueController@create | `storeops.issues.create` |
| POST | `/issues/store` | GoodsIssueController@store | `storeops.issues.store` |

### 11.2 Middleware Stack
- `web`, `auth`
- `InitializeTenantContext` (from GovStore TenantScope)

### 11.3 Controller Implementation Gaps

**GoodsReceiptController:**
- `submit()` method: **Declared in routes but NOT implemented** in the controller class
- `store()`: Hardcodes `stockable_type` to `Consumable::class`; hardcodes `received_by_type` to `SELF`
- `create()`: Loads all Consumables (no pagination, no location filtering)

**GoodsIssueController:**
- `store()`: Hardcodes `stockable_type` to `Consumable::class`
- `create()`: Filters users by location; filters consumables by qty > 0

**StockRegisterController:**
- `index()`: Loads all Consumables/Accessories/Components (no pagination)
- `kardex()`: Supports both full page and AJAX responses via content negotiation

---

## 12. UI & Views

### 12.1 Available Views

| View Path | Purpose |
|-----------|---------|
| `receipts/create.blade.php` | Goods Receipt creation form |
| `issues/create.blade.php` | Goods Issue creation form |
| `register/index.blade.php` | Stock Register Dashboard (tabs for Consumables/Accessories/Components) |
| `register/kardex.blade.php` | Full-page Stock Card (Kardex) view |
| `register/kardex-table.blade.php` | AJAX table partial for Kardex |
| `hooks/menu-injection.blade.php` | Dynamic sidebar menu injection |

### 12.2 Navigation Menus Registered

| Menu ID | Title | Parent | Route | Permission |
|---------|-------|--------|-------|------------|
| `storeops-register` | Stock Register Dashboard | gov-store | `storeops.register.index` | storekeeper |
| `storeops-receipts` | Receive Goods (GRN) | gov-store | `storeops.receipts.create` | storekeeper |

### 12.3 UI Injection Mechanism
- `InjectStoreOperationsUi` middleware intercepts responses on entity detail pages
- Dynamically injects Kardex workspace tabs via AJAX content loading
- Handles session expiry detection on tab fetch

---

## 13. Translations (Bilingual)

Translations exist in both **en-US** and **bn-BD** under `resources/lang/`. Keys cover:
- Receipt form labels and placeholders
- Issue form labels and placeholders
- Stock Register dashboard labels
- Kardex (Stock Card) column headers
- Menu items (Gov-Store Portal, Stores & Accounting sections)
- Service layer error/success messages
- Console command output messages

---

## 14. CLI Commands

| Command | Description |
|---------|-------------|
| `php artisan govstore:repair-ledger` | Scans for NULL `balance_after` values and recalculates running balances sequentially |

---

## 15. Policy & Authorization

Policies exist in the package (`Policies/` directory) but specific policy methods are not detailed in this investigation. The navigation menus reference a `storekeeper` permission requirement.

---

## 16. Identified Gaps & Limitations

### 16.1 Functional Gaps

| Gap | Severity | Description |
|-----|----------|-------------|
| Receipt submit endpoint missing | **High** | Route `storeops.receipts.submit` registered but controller method not implemented |
| Unit cost not captured in UI | Medium | Schema supports `unit_cost` but receipt form has no input field |
| Supplier ID not captured | Medium | Schema column exists but no UI or controller logic |
| Received by type hardcoded | Low | Always `SELF`, schema supports EMPLOYEE/COMMITTEE |
| Committee reference not captured | Low | Schema column exists but no UI |
| Stock Adjustments not operational | High | Schema/models exist but no controllers/services/routes |
| Only Consumables supported in UI | Medium | Adapters for Accessory/Component exist but controllers hardcode to Consumable |
| No receipt listing/detail views | Medium | Cannot view, filter, or search submitted receipts |
| No issue listing/detail views | Medium | Cannot view, filter, or search submitted issues |
| No document editing | Medium | All documents auto-submit; no draft persistence workflow |
| No cancellation flow | Medium | CANCELLED status exists in schema but no code path |

### 16.2 Technical Gaps

| Gap | Severity | Description |
|-----|----------|-------------|
| OUT movements lack balance_after | **High** | GoodsIssueService does not compute `balance_after` for outbound movements (inconsistency with receipt service) |
| No pagination on stockable lists | Medium | `Consumable::all()` loads all records into memory |
| No AJAX select2 for item selection | Low | Static dropdowns in forms; select2 infrastructure exists but unused |
| SystemGoodsIssueService truncated | **High** | Service file appears truncated — incomplete implementation visible |
| No validation on stockable_id uniqueness | Low | Same item can be added multiple times in a single receipt/issue |

### 16.3 Architecture Notes

- **Immutability:** InventoryMovement records are designed as immutable audit entries (no `updated_at`, no update logic).
- **Atomic Operations:** All quantity changes use atomic DB `increment`/`decrement` to avoid observer conflicts with Snipe-IT.
- **Tenant Isolation:** Every model has `MinistryLocationScope` for multi-company/location data isolation.
- **No Soft Deletes:** None of the Gov-Store models use soft deletes — once submitted, documents are permanent.

---

## 17. File Inventory

### Models (6)
- `GoodsReceipt`, `GoodsReceiptItem`
- `GoodsIssue`, `GoodsIssueItem`
- `StockAdjustment`, `StockAdjustmentItem`
- `InventoryMovement`

### Services (5)
- `GoodsReceiptService` — Receipt submission + ledger generation
- `GoodsIssueService` — Issue submission + stock validation
- `InventoryLedgerService` — Kardex queries
- `DocumentNumberService` — Sequential document numbering
- `SystemGoodsIssueService` — External package integration

### Adapters (3)
- `ConsumableAdapter`, `AccessoryAdapter`, `ComponentAdapter`

### Contracts (2)
- `StockableInterface`, `StockIssuingServiceInterface`

### Factories (1)
- `StockableFactory`

### Events & Listeners (1 + 2)
- Event: `InventoryMovementCreated`
- Listeners: `UpdateSnipeQuantity`, `WriteNativeAuditLogs`

### Controllers (3)
- `GoodsReceiptController`, `GoodsIssueController`, `StockRegisterController`

### UI Components
- Middleware: `InjectStoreOperationsUi`
- Tab Registry: `TabRegistry`, `Tab`
- Console Command: `RepairLedgerBalances`

---

## 18. Summary

The Gov-Store Store Operations package provides a **solid foundation** for government inventory management with:
- A well-designed immutable audit ledger (Kardex) system
- Clean separation of concerns via services, adapters, and event listeners
- Multi-company/location tenant isolation
- Bilingual support (en-US / bn-BD)
- Pre-computed running balances for inbound movements

However, the **current implementation is incomplete** — several critical gaps exist including a missing submit endpoint, hardcoded stockable types, no listing/detail views, no stock adjustment operations, and truncated service code. The package is in a **partial MVP state** suitable for vertical slice testing but not yet production-ready for full government store operations.
