# AGAPAY Phase 5 (Inventory) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** OMAG staff can see each stocked item's Stock-In / Stock-Out / Balance, record deliveries and manual corrections through the Record Stock Movement modal, and every claimed distribution deducts its stock automatically (returned on unclaim, archive or a smaller quantity; blocked when stock is short) — on screens matching Figma 470:785, 470:986, 430:1745 and 446:3.

**Architecture:** New `inventory_items` and `inventory_movements` tables plus `interventions.inventory_item_id`. Balances are always computed from movements; movements are append-only (corrections are counter-movements). One `InventoryService` owns stock: `record()` for manual movements and `syncRecord()`, which makes a record's automatic movements match what it should have deducted (its quantity while claimed and active, else 0). `ClaimService` and `InterventionAssignment` call `syncRecord()` inside their existing transactions, so a stock shortage rolls the whole claim back with a message.

**Tech Stack:** Laravel 13 / PHP 8.5, MariaDB 10.4 (dev) / SQLite in-memory (tests), Pest 4, Blade + Tailwind 4 + Alpine 3. Laravel Boost guidelines in `CLAUDE.md` apply.

**Spec:** `docs/superpowers/specs/2026-09-28-agapay-design.md` (§3 `/inventory` row; §4 `inventory_items`, `inventory_movements`, `interventions.inventory_item_id`; §5 rules 7 and 13; §6a steps 31–32). Previous plan: `docs/superpowers/plans/2026-10-03-agapay-phase-4-interventions.md` (`ClaimService`, `InterventionAssignment`, `InterventionRuleViolation`, `<x-intervention.flash>`, `<x-ui.modal>`, `<x-ui.inline-field>`, `<x-ui.pill-input>`, `program()` / `record()` / `sameHousehold()` test helpers).

## Global Constraints

- Work on branch `feature/phase-5-inventory` from `master`. Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Tests: Pest on SQLite in-memory (`lockForUpdate` is a no-op there — row-lock ordering is checked by the final reviewer). Query-string text via `$request->queryText('key')`.
- Permissions already exist: `inventory.view` (Admin, Data Encoder) and `inventory.manage` (Admin, Data Encoder). Agri Techs have neither.
- Page titles: Administrator "INVENTORY MANAGEMENT", everyone else "INVENTORY MONITORING" (Figma 470:785 vs 430:1745).
- Seeded items (name · unit · unit label · low-stock threshold · in / out → balance): Certified Rice Seeds · sack · "sacks (20kg)" · 40 · 120 / 86 → 34; Organic Liquid Fertilizer · liter · "liters" · 50 · 250 / 210 → 40; Complete Fertilizer · sack · "sacks (50kg)" · 25 · 180 / 150 → 30; HDPE Pipes · meter · "meters" · 100 · 500 / 320 → 180. Seeded Low Stock Items = 2.
- Intervention links: DA Certified Rice Seeds → Certified Rice Seeds; DA Organic Liquid Fertilizer → Organic Liquid Fertilizer; DA and LGU Complete Fertilizer → Complete Fertilizer; DA HDPE Pipes → HDPE Pipes (its intervention unit changes from `pc` to `meter`). Cash aid (PAFF, RFFA, Municipal Cash Subsidy), Molasses, Agri Machinery and Emergency Seedlings stay unlinked (claims deduct nothing). Record quantity is in the item's unit (1:1).
- A low item: `low_stock_threshold > 0` and balance ≤ threshold.
- Movement line (Figma 470:785 Recent Movements): `"{−|+}{qty} {unit plural} · {Item} · {notes} · {M j, Y}"` with an "AUTO" or "MANUAL" pill, e.g. `−2 sacks · Certified Rice Seeds · Auto-deducted: distribution to Juan Dela Cruz (RSBSA-0231) · Jul 18, 2026`. Quantities drop trailing zeros ("2", "2.5").
- Auto notes: out `"Auto-deducted: distribution to {Full Name} ({RSBSA No.})"`; in `"Returned: {unclaimed|archived|quantity reduced} distribution to {Full Name} ({RSBSA No.})"`.
- Shortage message (shown on the claim, edit or archive-restore that triggered it): `"Not enough stock: {Item} has {balance} {unit plural} left, {needed} needed."`
- Audit labels: "Recorded Stock In" / "Recorded Stock Out" (manual movements), "Added Inventory Item" / "Updated Inventory Item" (from `Auditable`, subject "Inventory Item"). Automatic movements are covered by the claim/unclaim/archive/restore rows already written by Phase 4 services.
- Figma 470:785 table (card 1488 wide): search pill 230×39 @23; header labels muted 14px: Unit @420, Stock-In @580, Stock-Out @780, Balance @980; "+ Record Movement" button 230×39 top-right of the filter row, fill `#7e80ff`, white bold 14px, radius 8 (same as "+ Add Record"); divider @114; rows 45px, bold 14px: name @38. Recent Movements card below (title bold 16px, no icon; pill "AUTO" 12px bold `#5a5de3` on `#efeaff`, radius 6; line medium 14px; muted 12px footnote "Stock-out from confirmed distributions is recorded automatically. Use + Record Movement for adjustments, deliveries, and manual corrections.").
- Figma 470:986 modal (≈700 wide): title "Record Stock Movement"; `<x-ui.inline-field>`-style rows: "Item: Select" | "Movement Type: Stock In / Stock Out", "Quantity" | "Date: MM/DD/YYYY", full-width "Notes (optional)"; gradient "Save Stock Movement".

## Review Focus

1. **Two claims racing for the last stock** — two staff claim different beneficiaries' Certified Rice Seeds when only enough is left for one; exactly one succeeds, the other gets the shortage message (the item row is locked before the balance check) (Task 2).
2. **Quantity edits on claimed records** — raising a claimed record's quantity deducts the difference (blocked when short); lowering it returns the difference; the balance always equals stock-in minus net deductions (Tasks 2, 3).
3. **Archive / restore of claimed records** — archiving returns the stock; restoring re-deducts it and is refused when stock is short (Task 3).
4. **Bad manual movements** — stock-out above the balance, zero/negative/huge quantities, future dates, an unknown item id or `item[]=` must give field errors, never a 500 or a negative balance (Tasks 2, 4).
5. **Hostile or empty inventory input** — `?q[]=`, `?q=%25`, an item with no movements (0 / 0 / 0) and an empty Recent Movements list must render normally (Task 4).

---

## File Structure

```
app/Models/InventoryItem.php, InventoryMovement.php
app/Models/Intervention.php                    (modify: inventoryItem())
app/Exceptions/InsufficientStock.php           extends InterventionRuleViolation
app/Services/InventoryService.php              balance(), record(), syncRecord()
app/Services/ClaimService.php, InterventionAssignment.php   (modify: call syncRecord)
app/Http/Controllers/InventoryController.php   index, storeMovement, storeItem, updateItem
app/Support/DashboardStats.php                 (modify: low_stock_items)
database/migrations/2026_10_04_00000{1,2,3}_*.php
database/seeders/InventorySeeder.php, InterventionSeeder.php (HDPE unit), DatabaseSeeder.php
resources/views/inventory/index.blade.php
tests/Feature/{InventoryModel,InventoryService,InventoryClaims,InventoryPage}Test.php
tests/visual/screens.spec.js, tests/visual/figma/{470-785,470-986,430-1745}.png
```

---

### Task 1: Inventory data model and seeded stock

**Files:**
- Create: migrations `2026_10_04_000001_create_inventory_items_table`, `..._000002_create_inventory_movements_table`, `..._000003_add_inventory_item_id_to_interventions_table`; models `InventoryItem`, `InventoryMovement`; `InventorySeeder`
- Modify: `Intervention` (`inventoryItem(): BelongsTo`, fillable), `InterventionSeeder` (HDPE unit `meter`), `DatabaseSeeder` (call `InventorySeeder` after `InterventionRecordSeeder`)
- Test: `tests/Feature/InventoryModelTest.php`

**Interfaces:**
- Produces:
  - `inventory_items`: id, `name` unique, `unit` (singular, e.g. `sack`), `unit_label` (e.g. `sacks (20kg)`), `low_stock_threshold` decimal(10,2) default 0, timestamps.
  - `inventory_movements`: id, inventory_item_id (FK restrict), `direction` (`in`/`out`), `quantity` decimal(10,2), `movement_date` date, `notes` nullable string(255), `source` (`manual`/`auto`), intervention_record_id nullable FK (null on delete), user_id nullable FK, timestamps; index (inventory_item_id, movement_date). No soft deletes, no updates.
  - `interventions.inventory_item_id` nullable FK (null on delete).
  - `InventoryItem` (Auditable subject "Inventory Item", HasFactory): `movements(): HasMany`; `scopeWithStock(Builder)` adding `stock_in` and `stock_out` sums (`withSum` on filtered movements); `stockIn(): float`, `stockOut(): float`, `balance(): float` (use the sums when loaded, else query); `isLow(): bool`; `static quantity(float): string` (trailing zeros dropped); `pluralUnit(float $qty): string` (`Str::plural(unit, qty)`); `auditRecordLabel()` = name.
  - `InventoryMovement`: consts `IN`, `OUT`, `MANUAL`, `AUTO`; relations `item()`, `record()`, `user()`; `signedLabel(): string` ("−2 sacks", "+120 sacks" — U+2212 minus); `line(): string` (the Global Constraints movement line).
  - `InventorySeeder`: the four items, intervention links, and movements giving the Global Constraints totals: per item one manual `in` "Delivery from DA-RFO" on 2026-07-01; auto `out` 2 for Juan's claimed Certified Rice Seeds record (2026-07-18, auto note) and 5 for Rosa's Organic Liquid Fertilizer record (2026-07-15); manual `out` for the rest, note "Distributed before AGAPAY (logbook)", dated 2026-06-30. Idempotent (re-running leaves the same totals).
- [ ] **Step 1: Write the failing tests** — `it('seeds the Figma stock levels')` (DatabaseSeeder twice → four rows with in/out/balance 120/86/34, 250/210/40, 180/150/30, 500/320/180; Juan's record has one auto `out` of 2); `it('links stocked interventions to their items')` (DA + LGU Complete Fertilizer → same item; PAFF → null; HDPE unit `meter`); `it('flags low items')` (34 ≤ 40 low; 30 > 25 not low; threshold 0 never low); `it('formats movement lines as in Figma')` (`signedLabel()` "−2 sacks", "+2.5 liters"; `line()` equals the Global Constraints example); `it('computes zero stock for an item without movements')`.
- [ ] **Step 2: Run** `./vendor/bin/pest tests/Feature/InventoryModelTest.php` — Expected: FAIL (tables missing).
- [ ] **Step 3: Implement** migrations, models, seeders; migrate and seed dev (`php artisan migrate --no-interaction`; `db:seed --class=InterventionSeeder` then `--class=InventorySeeder`).
- [ ] **Step 4: Run** file then full suite — Expected: PASS / all green.
- [ ] **Step 5: Commit** `feat: add inventory items, stock movements and seeded stock levels`.

---

### Task 2: InventoryService — manual movements and record syncing

**Files:**
- Create: `app/Exceptions/InsufficientStock.php`, `app/Services/InventoryService.php`
- Test: `tests/Feature/InventoryServiceTest.php`

**Interfaces:**
- Consumes: Task 1 models; `AuditLogger::record()`.
- Produces:
  - `InsufficientStock extends InterventionRuleViolation` (so Phase 4 controllers show it unchanged).
  - `InventoryService::record(InventoryItem $item, string $direction, mixed $quantity, ?string $date, ?string $notes, User $actor): InventoryMovement` — validates (`direction` in in/out; `quantity` required numeric >0 ≤99999, max 2 decimals; `date` required date ≤ today; `notes` ≤255) throwing `ValidationException`; in one transaction locks the item row (`lockForUpdate`), refuses an `out` above the balance with `InsufficientStock` (Global Constraints message); writes a `manual` movement with `user_id`; audit "Recorded Stock In"/"Recorded Stock Out" with `{quantity, date, notes}`.
  - `InventoryService::syncRecord(InterventionRecord $record, User $actor, string $reason = 'unclaimed'): void` — must run inside the caller's transaction. Target deduction = record's quantity when the record is claimed, not archived, its intervention has an item and quantity > 0; else 0. Current = sum of its auto `out` minus auto `in`. Difference > 0 → lock item, check balance, write auto `out` (auto note, date = `date_distributed` or today); difference < 0 → auto `in` with the `Returned: {reason} …` note, dated today. `$reason` ∈ `unclaimed|archived|quantity reduced`.
  - `InventoryService::lowStockCount(): int`.
- [ ] **Step 1: Write the failing tests** — `it('records deliveries and manual stock-out')` (+50 → balance up, audit "Recorded Stock In"); `it('refuses a stock-out above the balance')` (`InsufficientStock`, message "Not enough stock: Complete Fertilizer has 30 sacks left, 31 needed.", nothing written); `it('validates manual movements')` (dataset: 0, −1, 100000, 1.234, "abc", future date, direction `sideways` → `ValidationException`); `it('deducts a claimed record and returns it when unclaimed')` (`syncRecord` twice is idempotent; after unclaim + sync a `Returned: unclaimed …` `in` restores the balance); `it('adjusts by the difference when the quantity changes')` (2 → 5 deducts 3 more; 5 → 1 returns 4 with "quantity reduced"); `it('blocks a deduction larger than the balance')`; `it('ignores unlinked programs and records without quantity')` (PAFF, null qty → no movements); `it('counts low stock items')`.
- [ ] **Step 2: Run** — Expected: FAIL (classes missing).
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** file then full suite — Expected: PASS.
- [ ] **Step 5: Commit** `feat: add inventory service for stock movements and claim syncing`.

---

### Task 3: Stock follows claims, edits, archive and restore

**Files:**
- Modify: `app/Services/ClaimService.php` (claim, unclaim, archive, restore), `app/Services/InterventionAssignment.php` (reassign), `app/Http/Controllers/InterventionRecordController.php` (shortage → `quantity` error)
- Test: `tests/Feature/InventoryClaimsTest.php`

**Interfaces:**
- Consumes: Task 2 `syncRecord()`, `InsufficientStock`.
- Produces (behaviour; signatures unchanged):
  - `claim()` calls `syncRecord($record, $actor)` after saving the claim, replacing the `// Phase 5` comment — a shortage throws inside the transaction, so the claim rolls back and the controller shows the message.
  - `unclaim()` → `syncRecord(..., 'unclaimed')`; `archive()` → `syncRecord(..., 'archived')` after the soft delete; `restore()` → `syncRecord()` after the restore (shortage refuses the restore); `reassign()` → `syncRecord(..., 'quantity reduced')` after saving.
  - `InterventionRecordController` store/update catch `InsufficientStock` before `InterventionRuleViolation` and report it on `quantity`; the whole save rolls back (a distributed new record is not created; an edited quantity stays as it was).
- [ ] **Step 1: Write the failing tests** — `it('deducts stock when a claim is processed')` (Carlos validated → claim 1 sack of Complete Fertilizer → balance 29, Recent Movements line "−1 sack · Complete Fertilizer · Auto-deducted: distribution to Carlos Ibanez (RSBSA-0099)"); `it('blocks a claim when stock is short and leaves the record unclaimed')` (quantity 31 → `assertSessionHasErrorsIn('intervention', ['intervention' => 'Not enough stock: Complete Fertilizer has 30 sacks left, 31 needed.'])`, claim_status unclaimed, balance 30); `it('returns stock on unclaim')`; `it('returns stock on archive and re-deducts on restore')` (and restore refused when the stock was used up meanwhile); `it('adjusts stock when a claimed quantity is edited')` (Juan's claimed Certified Rice Seeds edited 2 → 4: balance 32; then to 40 → `assertSessionHasErrors(['quantity' => 'Not enough stock: Certified Rice Seeds has 32 sacks left, 36 needed.'])`, quantity stays 4); `it('rolls back a distributed DE record when stock is short')` (error on `quantity`, no record created); `it('does not touch stock for cash aid')`.
- [ ] **Step 2: Run** — Expected: FAIL (no movements written).
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** file then full suite — Expected: PASS.
- [ ] **Step 5: Commit** `feat: deduct and return stock automatically with claims`.

---

### Task 4: Inventory page and Record Stock Movement modal

**Files:**
- Create: `InventoryController` (`index`, `storeMovement`), `resources/views/inventory/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InventoryPageTest.php`

**Interfaces:**
- Consumes: Task 1 `withStock`, `line()`; Task 2 `record()`.
- Produces:
  - Routes: `GET /inventory` → `inventory.index` (`can:inventory.view`); `POST /inventory/movements` → `inventory.movements.store` (`can:inventory.manage`; fields `inventory_item_id`, `direction`, `quantity`, `movement_date`, `notes`). Success: back with status "{+|−}{qty} {unit} · {Item} recorded." (e.g. "+50 sacks · Certified Rice Seeds recorded."); errors (validation + `InsufficientStock` on `quantity`) in bag `inventory`, re-opening the modal.
  - Table: items `withStock()`, search `q` on name (literal LIKE, `!` escape), ordered by name; Unit column shows `unit_label`; low items show the balance in `text-danger` with `title="Low stock (threshold {n})"`; empty search → "No items match your search."
  - Recent Movements: the 10 newest movements (`movement_date` desc, id desc) with `line()` and the AUTO/MANUAL pill; empty → "No stock movements yet."; the footnote always shows.
  - "+ Record Movement" only for `inventory.manage`; the modal (Global Constraints layout) has the item select (all items, "Name (unit label)"), Stock In / Stock Out select, number Quantity (step 0.01, min 0.01), date (max today, default today), Notes.
- [ ] **Step 1: Write the failing tests** — `it('shows Figma stock columns for the administrator')` (title "INVENTORY MANAGEMENT"; `assertSeeInOrder(['Certified Rice Seeds','sacks (20kg)','120','86','34'])`; "+ Record Movement"; Recent Movements shows the Juan AUTO line and the footnote); `it('titles the page for the encoder')` ("INVENTORY MONITORING"); `it('marks low stock items')` (title "Low stock (threshold 40)" on Certified Rice Seeds, not on HDPE); `it('searches items as plain text')` (`q=fert` → two items; dataset `q[]=x`, `q=%25` → 200); `it('records a movement from the modal')` (post +50 → balance 84, status message, audit); `it('refuses a stock-out above the balance with a field error')`; `it('validates the modal fields')` (dataset incl. unknown item id, `inventory_item_id[]=1`); `it('keeps inventory away from agri techs')` (403 on both routes); `it('renders without movements')`.
- [ ] **Step 2: Run** — Expected: FAIL (route missing).
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** file then full suite; `npm run build` — Expected: PASS.
- [ ] **Step 5: Commit** `feat: add inventory monitoring page and record stock movement modal`.

---

### Task 5: Items, thresholds and intervention links

**Files:**
- Modify: `InventoryController` (`storeItem`, `updateItem`), `inventory/index.blade.php`, `routes/web.php`
- Test: extend `tests/Feature/InventoryPageTest.php`

**Interfaces:**
- Produces (no Figma frame — built as a modal below the mirrored region, per spec §2):
  - Routes (`can:inventory.manage`): `POST /inventory/items` → `inventory.items.store`; `PUT /inventory/items/{item}` → `inventory.items.update`. Fields: `name` (required, ≤100, unique ignoring case), `unit` (required, ≤20, singular), `unit_label` (required, ≤40), `low_stock_threshold` (numeric 0–99999), `interventions[]` (ids of interventions to link; an intervention already linked to another item moves to this one).
  - A "Manage Items" pill under the table opens a modal listing items with an Edit action and an "Add Item" form; edits re-use the same fields. Changing an item never rewrites past movements.
  - Audit "Added Inventory Item" / "Updated Inventory Item" (Auditable), plus the intervention link change recorded in the item's audit values as `interventions`.
- [ ] **Step 1: Write the failing tests** — `it('adds an item and links interventions')` (new "Molasses" item linked to DA Molasses → a later claim deducts); `it('updates a threshold and the low marker follows')`; `it('refuses duplicate item names ignoring case')`; `it('moves an intervention link to the new item')`.
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** file then full suite; `npm run build` — Expected: PASS.
- [ ] **Step 5: Commit** `feat: manage inventory items, thresholds and intervention links`.

---

### Task 6: Low Stock Items counter and Figma fidelity

**Files:**
- Modify: `app/Support/DashboardStats.php`, `tests/visual/screens.spec.js`; add `tests/visual/figma/{470-785,470-986,430-1745}.png` (Figma MCP `get_screenshot`, fileKey `ZYDqjMYN1h4OBNbQUpadrk`, `maxDimension: 1820`)
- Test: extend `tests/Feature/DashboardBeneficiariesTest.php`

- [ ] **Step 1: Write the failing test** — `it('counts low stock items live')` (seeded → 2; a +20 delivery of Certified Rice Seeds → 1).
- [ ] **Step 2: Run** — Expected: FAIL (counter is 0).
- [ ] **Step 3: Implement** the `low_stock_items` arm via `InventoryService::lowStockCount()`.
- [ ] **Step 4:** Add SCREENS `/inventory` (Admin_01 → 470-785, Encoder_03 → 430-1745) and the open modal (Admin_01, `before`: click "+ Record Movement" → 470-986); `npm run visual`; review side-by-side crops and fix structural mismatches. Expected: harness passes; remaining diff = real logo, font anti-aliasing, extra Recent Movements lines.
- [ ] **Step 5:** Full suite green; `vendor/bin/pint --dirty --format agent`; commit `feat: show live low stock count and add inventory screens to the visual harness`.

---

## Self-Review Notes

- **Spec coverage:** §4 inventory tables + intervention link → T1; §5.7 auto stock-out, reversal on unclaim, insufficient-stock block, manual In/Out modal → T2, T3, T4; §5.13 Low Stock Items → T6; §3 `/inventory` 470:785 / 470:986 / 430:1745 / 446:3 → T4, T6; §6a 31 → T1/T4, 32 → T2–T4/T6. Item management has no Figma frame; T5 adds the minimum to run the system for real (new items, thresholds, links).
- **Rulings taken while planning:**
  - Unclaim, archive and smaller quantities **return** stock with a new AUTO "in" movement instead of deleting the deduction, so the movement history stays complete.
  - Archiving a claimed record returns its stock and restoring re-deducts it (archived records are invisible to every rule, as in Phase 4).
  - Historical (logbook) claims deduct stock too, the same as live claims.
  - A record's quantity is in the item's unit (1:1); HDPE Pipes' intervention unit becomes `meter` to match the item.
  - The table's Unit column shows the item's label ("sacks (20kg)"); movement lines use the plain plural unit ("2 sacks"), as in Figma.
  - Movements can't be edited or deleted; mistakes are fixed with an opposite manual movement.
