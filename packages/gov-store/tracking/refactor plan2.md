This refinement hits two of the most complex realities in government resource planning: **Multi-Economic Code Procurements** (where one government order buys both laptops and furniture under different ledger codes) and **Cross-Ministry Distribution** (where one agency buys assets but issues them to offices belonging to a completely different ministry).

By shifting the Economic Code to the *Target* level, and expanding the Scope overrides to include external organizations, the architecture handles these seamlessly. Furthermore, trimming the API payload ensures we respect the "need-to-know" security principle for storekeepers.

Here is the **Refined Enterprise-Grade Architectural and UI/UX Blueprint**.

---

# 1. The Core Architecture (Refined Data Model)

The database schema is adjusted to support granular economic codes and cross-tenant (cross-ministry) scopes.

### A. The Initiative (The Umbrella Container)
*   **Table:** `gov_initiatives`
*   **Attributes:** Title, Purpose, Lifecycle Status (`Planning`, `Active`, `Closed`, `Archived`).
*   **Governance Rules:** `require_documents`, `allow_overshoot`, `require_metadata`.
*   **Default Scopes (Fallback):**
    *   *Ownership:* Company ID (e.g., ICT Division).
    *   *Management:* Location ID (e.g., Project HQ).
    *   *Participants:* Default is limited to offices *within* the Owning Company/Organization.

### B. The Tracking Code (The Executable Task)
*   **Table:** `gov_tracking_codes`
*   **Attributes:** `initiative_id`, `tracking_code` (e.g., `ICT-2027-01`), `task_title`, `order_pdf`.
*   **The Fiscal Profile (Parent Level):**
    *   `fiscal_year` (e.g., "2026-2027")
    *   `fund_main` (`REVENUE`, `ADP`, `SELF_FINANCED`)
    *   `fund_sub` (`GOB`, `GRANT`, `PA_LOAN`, `CAPITAL`)
    *   *Note: Parent `economic_code` becomes `null` if the child targets define their own varying codes.*

### C. The Quantitative Goal (The Line Items)
*   **Table:** `gov_tracking_targets`
*   **Attributes:** 
    *   `tracking_code_id`
    *   `category_id` (e.g., Laptops)
    *   `planned_qty` (e.g., 500)
    *   **`economic_code` (e.g., 4112202 - Machinery & Equipment)** 
    *   *(This allows one Tracking Code to authorize Laptops [4112202] and Desks [4112314] simultaneously under their correct financial ledgers).*

---

# 2. Decoupled Handshake API (Security-Trimmed)

Per your refinement, we do not expose global authorization or receipt numbers to the local storekeeper. The GRN interface only needs to know if the transaction is valid, and if not, why.

**Endpoint:** `GET /gov-store/api/tracking/evaluate?code=ICT-2027-01&location_id=45&category_id=3`

**Response Payload:**
```json
{
    "can_proceed": false,
    "override_required": true,
    "context": {
        "initiative": "School ICT Modernization",
        "task": "Procurement of Laptops for Khulna",
        "fiscal_year": "2026-2027"
    },
    "messages": [
        "This Tracking Code is strictly scoped to Khulna Division. Your office is in Dhaka."
    ],
    "target_status": {
        "category": "Laptops",
        "is_exceeded": true
    }
}
```
*(Notice how lightweight this is. The storekeeper is told they are blocked and why, but they are not given sensitive global project totals, and unnecessary "suggested actions" are removed to keep the frontend simple).*

---

# 3. The UI/UX Blueprint (Human-Centered Workspace)

### A. The Initiative Workspace (Action-First Dashboard)

```text
[ 📁 Active Initiatives / School ICT Modernization ]

================================================================================
 🏫 SCHOOL ICT MODERNIZATION (UMBRELLA)
 
 Status: [ 🟢 ACTIVE ]  |  Overall Health: 68%  |  Owner: Ministry of ICT
 Funding Sources Found: ADP, Foreign Loan, Revenue (GoB)
================================================================================

 ⚡ WHAT WOULD YOU LIKE TO DO?
 
  [ ➕ Add New Tracking Code / Task ]   [ 🏷️ Assign Legacy Assets ]   
  [ 📊 View Progress Report ]           [ ⚙️ Edit Umbrella Rules ]
 
────────────────────────────────────────────────────────────────────────────────
 📋 ACTIVE TRACKING CODES (Tasks & Components)

  Code: ICT-2027-01 | Hardware for Khulna Schools
  ↳ FY: 26-27 | ADP (Project Aid) | Scope: Khulna (Cross-Ministry Enabled)
  ↳ Targets:
     • Laptops  (Econ: 4112202) ➔ [████████░░] (Target Health: Healthy)
     • Printers (Econ: 4112314) ➔ [██████████] (Target Health: Exceeded)
  [ View PDF ] [ Edit Task ]
```

### B. The "Add/Edit Tracking Code" Wizard (Refined)

When creating the execution task, the form perfectly handles the Economic Code shifts and Cross-Ministry distribution logic.

**Section 1: The Task Definition & Goals**
*   *Tracking Code (For GRN):* `[ Input: e.g., ICT-2027-03 ]`
*   *Task/Component Title:* `[ Input: e.g., Supply of Equipment to Sylhet ]`
*   *Official Document:* `[ Upload PDF Govt. Order ]`
*   *Quantitative Goals:* 
    * `[ Category: Laptops  ▼ ]` `[ Qty: 150 ]` `[ Econ Code: 4112202 ]` `[ - ]`
    * `[ Category: Desks    ▼ ]` `[ Qty: 300 ]` `[ Econ Code: 4112314 ]` `[ - ]`
    * `[ + Add another category ]`

**Section 2: The Fiscal Profile (Umbrella Funding)**
*   *Fiscal Year:* `[ Dropdown: 2026-2027 ]`
*   *Main Fund Source:* 
    * (•) Annual Development Programme (ADP)
    * ( ) Revenue Budget (Non-Development)
    * ( ) Self-Financed
*   *Sub Fund Source (Optional):* `[ Dropdown: GoB/Taka, Project Aid (Loan), Grant ]`

**Section 3: Execution Scope & Participating Offices**
*   *Geographical Coverage:* 
    * ( ) Inherit Umbrella Scope (Entire Bangladesh)
    * (•) Override for this Task ➔ `[ Dropdown: Sylhet Division ]`
*   *Participating Offices (Who can receive this?)*
    * (•) Inherit Umbrella Scope (All offices within our Ministry/Organization).
    * ( ) Override: Select specific warehouses only ➔ `[ Multi-select list ]`.
    * ( ) Override: Allow ALL offices in the coverage area (Includes other Ministries/Departments). 
          *↳ (Helper text: Useful for Central Procurement distributing to local government or other agency offices).*

---

# 4. Why This Refinement is Enterprise-Grade

1.  **Cross-Tenant Allocation Solved:** The third option in *Participating Offices* allows the Ministry of ICT to buy laptops, but legally transfer and tag them to schools owned by the Ministry of Education. The validation engine will pass the GRN because the scope explicitly allows "other organizations in the area."
2.  **Economic Code Precision:** By moving the Economic Code to the Target line-item level, a single Tracking Code (which maps to one real-world Government Order PDF) can procure multiple asset types spanning different financial ledger codes, maintaining perfect harmony with the Ministry of Finance (iBAS++) reporting.
3.  **Need-to-Know Privacy:** Trimming the API payload ensures that a local storekeeper in a remote office cannot reverse-engineer the entire multi-million-dollar project budget or progress status. They only receive the operational constraints they need to complete their local GRN.