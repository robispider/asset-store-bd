
# Architectural Specification: Composable Product Profile & Document Processing Engine

This document provides a comprehensive technical specification of the **Store Operations Package**, detailing the underlying **Plugin-Based Capability Architecture**, the **Layered Product Profile Compiler**, and the **Unified Document Processing (GRN) Pipeline** built to integrate seamlessly with the Snipe-IT database.

---

## 1. System Architecture Overview

The system is designed around the principles of **Domain-Driven Design (DDD)** and **Metadata-Driven Execution**. Instead of hardcoding distinct business logic for different categories of items (e.g., Laptops vs. Stationery), the package treats document lifecycles and product behaviors as dynamic assemblies of atomic, reusable plugins.

```
+-----------------------------------------------------------------------------------+
|                              PRESENTATION LAYER                                   |
|   - Unified Document Workspace (Blade Shell + AJAX Grid)                          |
|   - Dynamically injects UI Partials rendered by Composed Presentation Plugins    |
+-----------------------------------------------------------------------------------+
                                         │
                                         ▼ (Save/Evaluate/Post)
+-----------------------------------------------------------------------------------+
|                            DOCUMENT WORKFLOW LAYER                                |
|   - Manages Document Lifecycle States (Draft -> Posted -> Locked)                |
|   - Orchestrates State Transitions, Timelines, & Categorized File Uploads        |
+-----------------------------------------------------------------------------------+
                                         │
                                         ▼ (Materialization Trigger)
+-----------------------------------------------------------------------------------+
|                             MATERIALIZATION LAYER                                 |
|   - Reads the Immutable Snapshot (JSON) stored on the Document                    |
|   - Compiles and executes sequential, transactional step capabilities             |
+-----------------------------------------------------------------------------------+
                     ┌───────────────────┴───────────────────┐
                     ▼ (Bulk Items)                          ▼ (Serialized Assets)
+--------------------------------─────────+ +---------------------------------------+
|          Ledger Posting Step            | |         Asset Creation Step           |
|  - Symmetrical Kardex Ledger post       | |  - Instantiates native Eloquent Model  |
|  - Triggers Snipe-IT Qty Increments     | |  - Binds Serials, Tags, & Action Logs |
+-----------------------------------------+ +---------------------------------------+
```

---

## 2. The Database Schema & Meta-Engine Design

To keep the database lean and highly structured, the system completely avoids adding dynamic columns to the product tables. Instead, it uses a generic, polymorphic database design consisting of **Profiles**, **Capabilities**, and **Row-Indexed Metadata**.

### 2.1 The Core Database Tables
*   **`gov_profiles`:** Represents the recursive organizational and material hierarchy. Each row represents a business tier (Global, Major Type, Category, or Model) and has a self-referencing `parent_id` foreign key.
*   **`gov_profile_capabilities`:** Links a profile directly to a string capability code (e.g., `require_serial` or `post_inventory`) and stores an optional JSON configuration payload (such as custom Snipe-IT fieldset IDs).
*   **`gov_documents`:** The generic transactional header storing document number, operation type (`receipt` or `issue`), reference (Challan) numbers, dates, and the serialized, frozen JSON compiled snapshot.
*   **`gov_document_items`:** The grid lines storing polymorphic associations (e.g., `'consumable'`, `'asset_model'`), original product IDs, quantities, and unit costs.
*   **`gov_document_item_meta`:** The unified Entity-Attribute-Value (EAV) storage. It maps dynamic custom inputs entered by the storekeeper (e.g., serial numbers, warranties, location IDs) directly to their parent line item, grouped by `row_index` to support multi-quantity serialized rows.

---

## 3. The 4-Layer Recursive Compilation Engine (The Composer)

When a product is loaded into the workspace, the `ProfileCompilerService` dynamically builds its **Onboarding Profile** by traversing the `parent_id` hierarchy from bottom to top, compiling a unified behavioral specification.

```
[ Global Base Profile ]  (Root: parent_id = NULL)
          ▲
          │ (parent_id)
[ Major Type Profile ]   (e.g., Hardware Asset)
          ▲
          │ (parent_id)
[ Category Profile ]     (e.g., Notebook)
          ▲
          │ (parent_id)
[ Model Override ]       (Optional Leaf, e.g., Cisco Switch)
```

### 3.1 The Compilation Priority Cascade
The compiler resolves the inheritance tree using a strict top-down merge sequence, ensuring children cleanly overwrite parent configurations:
1.  **Global Base (Priority 1):** Applies universally (enforces Challan reference, date, and receiving source inputs).
2.  **Major Type (Priority 2):** Applies to Snipe-IT core types (e.g., `asset_model` always merges `create_assets`; `consumable` always merges `post_inventory`).
3.  **Category (Priority 3):** Applies to specific families (e.g., `Notebook` category merges `require_serial` and `require_warranty`; `Motor Bike` category merges `require_engine` and `require_chassis`).
4.  **Model Override (Priority 4 - Highest):** Applies to exceptional model-level requirements (e.g., a specific Cisco switch merging `require_mac_address`).

### 3.2 In-Memory Static Memoization (High-Speed Execution)
To achieve sub-millisecond execution speeds and prevent N+1 database queries on large documents, the compiler implements **static request-cycle memoization**. When compiling a document containing 100 items of the same category, the compiler queries the database on the first item, caches the resolved objects in a static PHP array, and instantly reuses them from memory for the remaining 99 items.

---

## 4. The Immutable Snapshot Engine (Historical Integrity)

A major risk in metadata-driven ERP systems is **schema drift**: if an administrator changes the capabilities assigned to the "Notebook" category next year, older historical documents must not suddenly fail validation or display differently.

The architecture solves this via **Deferred Snapshot Freezing**:
1.  **Dynamic Drafting:** While a document is in the `DRAFT` state, saving the document dynamically re-compiles the snapshot to match the currently entered line items.
2.  **Permanent Lock:** The exact millisecond the document is submitted for posting, the merged capabilities, validation parameters, and custom field structures are compiled into a flat JSON payload and written permanently to `gov_documents.compiled_profile_snapshot`.
3.  **Immunity to Change:** Once the document status moves past `DRAFT`, compilation is blocked. All subsequent operations (validations, post-creation tasks, audits, and printing) read strictly from this immutable JSON snapshot, shielding historical records from future profile configuration changes.

---

## 5. The Composable Materialization Pipeline

When a document transitions to the `POSTED` state, the `PostingPipelineManager` instantiates the materialization process. The service runs entirely within a **single, secure database transaction** to guarantee absolute data consistency.

```
                       [ Post Triggered ]
                               │
                               ▼
               [ Open Database Transaction ]
                               │
               [ Lock Document Status -> POSTED ]
                               │
                               ▼
            [ Loop Through Document Line Items ]
                               │
             ┌─────────────────┴─────────────────┐
             ▼ (Post Inventory Capability)       ▼ (Create Assets Capability)
┌────────────────────────────────────────┐ ┌────────────────────────────────────────┐
│ - Read previous balance from Kardex    │ │ - Read serials/tags from EAV metadata  │
│   using pessimistic lock (lockForUpdate)│ │ - Instantiate native Eloquent Asset    │
│ - Write symmetric Kardex ledger record │ │ - Trigger core Snipe-IT Observers &    │
│ - Dispatch Snipe-IT Qty Increments     │ │   Action logs                          │
└────────────────────────────────────────┘ └────────────────────────────────────────┘
                               │
                               ▼
                [ Commit Database Transaction ]
```

1.  **Pessimistic Balance Locking:** For counter-based inventory (`PostInventoryCapability`), the engine queries the latest balance in `gov_inventory_movements` using a pessimistic database lock (`lockForUpdate()`). This prevents concurrency collisions (double-counting) in high-traffic offices.
2.  **Eloquent Observer Synchronization:** Forserialized hardware (`CreateAssetsCapability`), the engine avoids raw SQL inserts. Instead, it instantiates native Snipe-IT Eloquent models (`App\Models\Asset::create()`) inside the loop, ensuring that Snipe-IT’s internal Model Observers, automatic audit logging, and asset events fire correctly.
3.  **Atomic Rollback:** If any step fails (e.g., a duplicate serial number validation fails, or database connection drops midway), the entire transaction rolls back, restoring the document to its `DRAFT` state and preventing partial, orphaned data writes.

---

## 6. End-to-End Operational Workflow (The Lifecycle)

The lifecycle of an inventory transaction follows an explicit, robust pipeline:

### Phase A: Capture & Drafting (The Receiving Workspace)
1.  The storekeeper clicks **"New Goods Receipt"** on the Hub, initializing a blank `DRAFT` document.
2.  The storekeeper adds an item (e.g., "HP Laptop") to the grid. 
3.  The frontend makes an AJAX request to `/products/{type}/{id}/profile`. The compiler walks the recursive tree and returns the capability requirements.
4.  The JavaScript grid reads the return and dynamically renders sub-input fields (e.g., Serials, Warranties) matching the quantity.
5.  The storekeeper scans physical serials into the dynamically rendered rows and clicks **[Save Draft]**. The controller writes standard quantities to `gov_document_items` and serial variables to `gov_document_item_meta`.

### Phase B: Server-Side Validation
1.  The right-sidebar checklist triggers a background AJAX call to `DocumentValidationService::evaluateDocument()`.
2.  The server-side service loops through the lines, resolves the frozen snapshot, and executes the validation methods on each capability.
3.  If any required serial is missing or duplicate, the validation fails. If 100% complete, the progress bar turns green, and the **[Post to Ledger]** button is unlocked.

### Phase C: Posting & Archiving
1.  The storekeeper clicks **[Post to Ledger]**. The Posting Preview Modal displays the quantitative and financial sum of the delivery.
2.  Upon confirmation, the `PostingPipelineManager` locks the document, executes the sequential materialization steps (Kardex writes, Asset creations), and archives the workspace.
3.  The workspace is reloaded as a read-only **Document Viewer** with print capabilities linked to the official PDF print engine.
