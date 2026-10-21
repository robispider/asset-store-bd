# Asset tagging — UI/UX specification (revision 7)

These are the screens that the [architecture](../ARCHITECTURE.md) requires. The interactive designs are on a
[design canvas](https://claude.ai/artifact/1xfRGHHkFngBXp5YMQafur), as artboards linked into a clickable prototype.

- Each artboard has *Theme* and *mode* tweaks covering five themes in light and dark.
- The canvas loads the real built kit and theme CSS from `public/vendor/gs-theme`, plus
  [asset-tagging.prototype.css](asset-tagging.prototype.css). That stylesheet holds the proposed `code-segments`
  component and token-only stand-ins for the Bootstrap/AdminLTE shell.
- The artboard sources are versioned in [canvas/](canvas/).
- Parts that are **new on an existing screen** carry a small dashed "NEW" marker in the designs. The marker is a design
  annotation only and does not ship.

## 1. Principles

| Principle | What it means here |
| --- | --- |
| **Add, don't change** | Existing screens (GRN workspace, posting modal, product-type form) keep every element, label and position. Tagging only adds to them. |
| **Plain words first** | The screens say "Govt. Tag" and "Standard tag". Codes always sit beside names. |
| **Optional means optional** | A missing Finance Code gives a **warning with a fix**, never a block. |
| **Needs attention is specific** | Only a real blocker is flagged, and only on the field that fixes it. The flag repeats in the existing validation checklist. |
| **Pick, don't type** | Office codes are chosen from company lists. Codes already taken are shown but can't be picked, with the reason. |
| **Who owns this?** | Read-only values name their owner. |
| **Show the least data needed** | Lookup answers "missing" and "forbidden" the same way. Evidence is shown only as a reference, never as the file. |
| **Themed, bilingual** | Uses `--gs-*` tokens and `<x-gs::*>` components only, and translation keys under `asset-tagging::tagging.*`. Every screen is checked in light and dark. |

## 2. Shared patterns

### `<x-gs::code-segments>` (new kit component)

Shows a tag as labelled segments: group, subgroup and item; month; location and division; sequence. The sequence is
unallocated until posting and is drawn with a dashed border, with the screen-reader text "allocated when posted".
`size="sm"` gives the compact form for tables. It is used in the GRN line panel, the posted panel, lookup and office
settings.

### Tag-kind chip

A `gs-tag` reading **Govt. Tag** (primary) or **Standard tag** (secondary).

### Finance Code select

Uses the existing select2 pattern: `class="js-data-ajax" data-endpoint="finance-items"`. The endpoint is classification
`Api/FinanceItemsController@selectlist` with `FinanceItemsTransformer`.

- Each option reads `090809 · Laptop`, with the group path underneath.
- Items whose UNSPSC matches the product type are tagged **Matches UNSPSC**.
- Items linked to a different UNSPSC are excluded on the server.

## 3. Screens

### S1 · GRN workspace (storekeeper) — additions only

The existing workspace is reproduced exactly:

- **Administrative details:** receiving source, supplier, Supplier Challan, Purchase Order, Nothi and allocation.
- **Received items:** the grid of item, stock, quantity, cost and balance, with its serial and warranty sub-grids.
- **Supporting documents.**
- **Right panel:** draft workspace, totals, validation checklist, Save, Post and Void.
- **Activity timeline.**

Tagging adds only the following.

1. **Tag panel under each asset line.** This is a capability `renderUI` block, appended after the existing serial and
   warranty sub-grids. It contains:
   - **Finance Code:** the code and name, the "From product type" tag and the owner line, all read-only.
   - **Division:** a select, shown only if the office uses more than one division.
   - **Tag preview:** `code-segments`, plus a count such as "3 Govt. Tags".
2. **Warning when there is no Finance Code.** The text is: "⚠ No Finance Code for this product type. These 6 units will
   get **standard tags**." It offers two fixes in place:
   - **Choose a Finance Code for this line:** the select above, applying to this line only. The preview switches to a
     Govt. Tag.
   - **Keep standard tags:** dismisses the warning for this line, and the choice is recorded.

   The warning also appears in the existing checklist as a non-blocking row with a warning icon.
3. **Needs attention.** This applies to a line that qualifies for a Govt. Tag when the document has no Supplier Challan
   or Purchase Order date. It is shown in three places:
   - in the line panel, in red: "Govt. Tag needs the Supplier Challan or Purchase Order date";
   - in the existing checklist, as a blocking row that keeps the Post button disabled, as checklist rows already do;
   - as a highlight ring on the existing Supplier Challan date input.

   There are two fixes: **Go to the challan date** (focuses the existing date field), or **Use standard tags for this
   line**. As soon as the date is filled, the flag clears and the preview month updates.
4. **Receiving source *Donation*, *Transfer* or *Confiscated*.** An info note in each panel: "This receiving source
   issues standard tags."
5. **Paused office** (gate G1 or G4 failing). A warning note in each panel says tag issuance is paused. Posting stays
   blocked through the checklist, and drafting still works.

### S2–S3 · Posting modal and posted GRN — additions only

- **Existing posting modal:** title, lead text, the well with document, office, items, quantity, value and reference,
  then the bilingual warning. It gains **one line** inside the well: "Tags: 9 Govt. Tags · 2 standard tags —
  permanent".
- **Posted (read-only) workspace:** the existing success flash and read-only view stay. One **Issued tags** box is added
  to the right column. It contains a compact list (tag, kind, product, serial), **Print labels** (the existing native
  label route, with the assets preselected) and **Download CSV**. Reprints reuse the same tag.

### S4 · Product type — one added field

**Finance Code (optional)** goes directly under the existing single UNSPSC field.

- Help text: "New receipts of this type get Govt. Tags. Without it, standard tags."
- **Read-only state:** shows the owner.
- **Needs-review state:** shown after a change of commodity.
- **Several UNSPSC codes found:** a reconciliation block (publisher only).

### S5 · Office tag settings and company code lists

Located at Administration → Rules & settings → **Asset tags**.

**Office tab** (`asset-tags.office.configure`):

- **Registration location:** a dropdown of the company's location list, shown as `01 · Head Office`. Codes used by
  another office of the same company appear but are disabled ("used by Example Office, Khulna").
- **Divisions this office uses:** checkboxes from the company's division list.
- **Default division:** a dropdown limited to the checked divisions.
- **Preview** of the office part of a Govt. Tag.
- Footer shows the revision and last change. Concurrent saves get a compare-and-choose conflict banner.
- There is **no authority reference** and nothing is typed.

**Company code lists tab** (`asset-tags.company.codelists`, company admin only):

- Two editable lists of code and name, for locations and divisions.
- An entry in use shows "used by N offices". It can be renamed, but its code is locked and it can't be removed.

**Readiness tab:** gates G1–G6 in plain words.

### S6 · Finance catalog (national publisher)

- KPIs for held, active, retired and linked to UNSPSC.
- Status tabs, a filter bar, the item table and a bulk bar.
- **Review and activate** shows the exact payload and requires typed confirmation. It runs once.
- **Group capacity strip:** each group shows how many of its 99 item numbers are used. It turns warning-toned from 90,
  e.g. "12 Tools 87/99".
- **New Finance item:** previews the next item number for the group, e.g. group 09 → `12`. When a group is full, the
  step shows: "Group 12 is full (99 of 99). New items can't be added until the Finance Division approves the overflow
  rule (D-08). Receiving continues: units get standard tags."

### S7 · Tag lookup

- Shows the decoded Govt. Tag, or "Standard tag".
- Registration facts and current asset facts.
- The same neutral message for "missing" and "forbidden".

### S8 · Enablement console (operator)

- Installation gates and the offices table.
- Enable requires a reason, and every action is audited.
- Recent activity is listed, including setting changes the guard refused.

## 4. Copy deck (`asset-tagging::tagging`)

| Key | en-US | bn-BD (to be reviewed) |
| --- | --- | --- |
| `kind.govt` | Govt. Tag | সরকারি ট্যাগ |
| `kind.standard` | Standard tag | সাধারণ ট্যাগ |
| `finance.optional` | Finance Code (optional) | অর্থ বিভাগ কোড (ঐচ্ছিক) |
| `warn.no_finance` | No Finance Code for this product type. These :count units will get standard tags. | এই পণ্যের ধরনের অর্থ বিভাগ কোড নেই। এই :count টি ইউনিট সাধারণ ট্যাগ পাবে। |
| `fix.choose_line` | Choose a Finance Code for this line | এই লাইনের জন্য অর্থ বিভাগ কোড বাছুন |
| `fix.keep_standard` | Keep standard tags | সাধারণ ট্যাগ রাখুন |
| `attention.date` | Govt. Tag needs the Supplier Challan or Purchase Order date | সরকারি ট্যাগের জন্য চালান বা ক্রয়াদেশের তারিখ প্রয়োজন |
| `modal.tags` | Tags: :govt Govt. Tags · :standard standard tags — permanent | ট্যাগ: :govt সরকারি · :standard সাধারণ — স্থায়ী |
| `office.code_taken` | Used by :office | :office ব্যবহার করছে |
| `capacity.full` | Group :group is full (99 of 99) | গ্রুপ :group পূর্ণ (৯৯/৯৯) |
| `lookup.none` | No asset you can view has this tag. | আপনার দেখার অনুমতি আছে এমন কোনো সম্পদে এই ট্যাগ নেই। |

## 5. Accessibility and security checklist

- Warning and needs-attention rows use an icon and text, never colour alone.
- Fix buttons are real buttons.
- Focus moves to the existing date input when the date fix is used.
- Disabled dropdown options carry their reason in the option text.
- The server repeats every check. The client never sends a tag, a counter value or a scope.
- Revision fields guard every settings save.
- Every new route uses `gov.can`.

## 6. Build notes

- Additions to existing screens go through extension points: capability `renderUI`, `ContributesChecklist` rows,
  `preview()` JSON and `postedPanel()`. `workspace.blade.php` itself gains only the one `postedPanel` output.
- Package CSS goes in `@layer gs-packages`. Unlayered Snipe-IT `label`, `legend` and `h2` rules win over it, so
  scope `!important` to exactly those properties.
- Build arrays for `code-segments` in `@php … @endphp` blocks.
- Add Lab demos, keep compliance at zero colour literals, and walk every screen in light and dark.
