(This is an in progress  r & d work by department of ICT in Bangladesh government )

# GovStore: Enterprise Government Procurement & Logistics Engine

GovStore is a highly modular, multi-tenant enterprise resource planning (ERP) extension built natively on top of the [Snipe-IT](https://snipeitapp.com/) asset management framework. It is specifically designed to meet the rigorous hierarchical, governance, and audit requirements of the Bangladesh Government (and similar large-scale public sector architectures).

GovStore transforms Snipe-IT from a simple IT asset tracker into a fully decentralized, geographically aware, multi-tier e-commerce and logistics platform.

---

## 🎯 Core Philosophy

1. **Zero Core Modifications:** GovStore runs entirely within isolated Laravel packages (`/packages/gov-store/*`). It extends Snipe-IT through Service Providers, Middleware, Observers, and Event Listeners without altering a single line of core vendor code, preserving seamless upgrade paths.
2. **Strict Multi-Tenancy (Data Isolation):** A storekeeper in a rural Upazila office can never see, modify, or interact with assets or catalogs belonging to another Upazila or Ministry.
3. **Immutable Auditing (Ledger Integrity):** State-based inventory counters (e.g., `qty = 100`) are replaced by an immutable double-entry accounting ledger. Stock levels are projected natively back into Snipe-IT for compatibility.

---

## 📦 Modular Package Architecture

GovStore is divided into six strictly bounded contexts (Laravel Packages):

### 1. 🛡️ `tenant-scope` (The IAM Foundation)
The foundational Layer 1-7 Identity and Access Management (IAM) engine. Evaluates user context (Identity, Membership, Responsibility) dynamically on every HTTP request and applies strict Eloquent Global Scopes to physically isolate databases at the driver level.

### 2. 🏢 `organization` (Office Provisioning)
Manages the lifecycle of government offices. Features geographical territory tagging (Division ➔ District ➔ Upazila), ICT Officer jurisdiction bounds, and native hierarchical catalog bridging.

### 3. 🆔 `office-membership` (Custody & Roles)
Decouples identity from physical location. Supports dynamic onboarding, permanent staff transfers, peer-to-peer delegation handshakes, and multi-office session context switching.

### 4. 🛒 `custom-requests` (Approval Workflows)
An internal e-commerce storefront for employees. Features a dynamic shopping basket, multi-tier approval routing (e.g., `PRIMARY_AND_FINAL`), product substitutions, and automated fulfillment pipelines.

### 5. 📦 `store-operations` (Warehouse Accounting)
Replaces manual quantity adjustments with an immutable inventory ledger. Features Document-Driven Operations (Goods Receipts, Ad-Hoc Issues, Adjustments), generating native `IN`/`OUT` movements and a printable Stock Card (Kardex) that complies with government audit standards.

### 6. 🌐 `classification` (Master Data Management)
Integrates official, external classification schemes (e.g., UNSPSC). Features a 3-Tier Governance model (Global Standards, Ministry Standards, Local Standards), decoupling master reference data from operational categories to prevent taxonomy bloat.

---

## 🚀 Key Enterprise Features

*   **Dynamic UI Injection:** Integrates modular dashboards and workspaces cleanly into Snipe-IT’s AdminLTE UI via a centralized, role-aware Menu Registry and DOM hooks.
*   **Self-Healing Ledger:** Ensures legacy or orphaned data is automatically repaired and synchronized through dedicated offline Artisan commands.
*   **Bilingual Localization:** Fully localized into formal, government-standard Bengali (`bn-BD`) and English (`en-US`), supporting seamless user-level locale switching.
*   **CQRS Principles:** (Command Query Responsibility Segregation) Physical stock changes are handled through transactional commands, while native Snipe-IT fields serve only as projected read-models.

---

## 🛠️ System Requirements

*   PHP 8.2+
*   Laravel 10.x / 11.x
*   Snipe-IT v7.x / v8.x
*   MySQL 8.0+ or MariaDB 10.5+

---
# We thank snipeit https://github.com/grokability/snipe-it
