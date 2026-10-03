# AGAPAY Phase 4 (Interventions & Distribution) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** OMAG staff can assign DA and LGU interventions to existing beneficiaries per distribution cycle, have Agri Techs validate eligibility, process claims under the one-claim-per-household rule (admin override with reason), archive/restore records with a reason, and see real intervention data on dashboards, lists and profiles — on screens matching Figma frames 329:2475, 407:2, 329:1250, 400:4, 407:323, 407:463, 344:236, 329:1423, 329:3407, 407:603, 407:743, 344:531, 344:817, 430:1603, 440:166, 446:198, plus the hidden validation modal 337:18.

**Architecture:** New `interventions`, `distribution_cycles`, `intervention_records` tables. Two services own every state change: `InterventionAssignment` creates/edits records (uniqueness, LGU duplicate flag) and `ClaimService` owns validation, claim and unclaim (eligibility, deceased proxy, household block + override). Both throw one `InterventionRuleViolation` (message shown to the user) and write explicit audit rows; controllers stay thin. One list controller serves DA and LGU in two role variants (Admin chips + Archive; others dropdowns), selected the same way as Phase 3 profile variants (by role slug, gated by permission).

**Tech Stack:** Laravel 13 / PHP 8.5, MariaDB 10.4 (dev) / SQLite in-memory (tests), Pest 4, Blade + Tailwind 4 + Alpine 3. Laravel Boost guidelines in `CLAUDE.md` apply (`vendor/bin/pint --dirty --format agent` after PHP changes; `php artisan make:*` with `--no-interaction`).

**Spec:** `docs/superpowers/specs/2026-09-28-agapay-design.md` (§3 routes for `/interventions*`, `/validation`, `/intervention-records`; §4 `interventions`, `distribution_cycles`, `intervention_records`; §5 rules 3, 4, 5, 6, 13; §6a steps 24–30). Previous plan: `docs/superpowers/plans/2026-10-02-agapay-phase-3-beneficiaries.md` (`Beneficiary`, `HouseholdService`, `<x-beneficiary.table>`, `<x-beneficiary.action-bar>`, `<x-ui.modal>`, `<x-ui.inline-field>`, `<x-ui.status-chip>`, `<x-ui.pill-input>`, `<x-ui.pill-select>`, `DashboardStats`, `brgy()` test helper).

## Global Constraints

- Work on branch `feature/phase-4-interventions` from `master`. Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Tests: Pest on SQLite in-memory. Query-string text via `$request->queryText('key')`. Numeric ids from the query string are used only when `ctype_digit`.
- **Inventory is Phase 5.** No stock columns or deductions here; `ClaimService::claim()` gets a single `// Phase 5: inventory stock-out` comment where the movement will be written.
- Sources: `da` ("Department of Agriculture [DA]", "National"), `lgu` ("Local Government Unit [LGU]", "Municipal"). Unknown source in a URL → 404 (route `whereIn`).
- Seeded interventions (source · name · unit · one_per_household · allow_repeat): DA · Certified Rice Seeds · sack · no · no; DA · Organic Liquid Fertilizer · L · no · no; DA · Complete Fertilizer · sack · no · no; DA · PAFF · null · yes · no; DA · RFFA · null · yes · no; DA · HDPE Pipes · pc · no · no; DA · Molasses · L · no · no; DA · Agri Machinery · unit · yes · no; LGU · Complete Fertilizer · sack · no · no; LGU · Emergency Seedlings · bundle · no · yes; LGU · Municipal Cash Subsidy · null · yes · no. Unique on (source, name).
- Seeded cycles: `2026-Q1` "2026-Q1 Wet Season" completed; `2026-Q2` "2026-Q2 Planting Season" completed; `2026-Q3` "2026-Q3 Dry Season" **ongoing**. "Current cycle" = the ongoing one, else the latest scheduled, else the latest by code.
- Validation statuses: `pending` (chip "Validate", tone bad), `eligible` "Eligible", `ofw` "OFW", `bedridden` "Bedridden", `deceased` "Deceased", `inactive` "Inactive", `relocated` "Relocated", `duplicate` "Duplicate" — all non-pending tone ok (Figma shows validated chips green). Claimable: `eligible`, `ofw`, `bedridden`, `deceased` (proxy + proof required).
- Claim statuses: `unclaimed` "Unclaimed" (bad), `claimed` "Claimed" (ok). DE form wording: "Distributed" = claimed, "Not Yet Distributed" = unclaimed.
- LGU "Registration" label from the beneficiary's RSBSA status: `registered` → "Registered"; `pending_validation`/`validated`/`endorsed` → "Registered (New)"; `returned`/`rejected` → "Unregistered (Eligible)". Filter values `registered` / `new` / `unregistered`.
- Quantity display: `"{qty} {unit}"` with the unit pluralised by `Str::plural` except `L` (e.g. "2 sacks", "1 sack", "5 L"); null qty → "-".
- DE intervention label: `"{DA|LGU} - {name} (Batch {cycle code})"`, e.g. `DA - Certified Rice Seeds (Batch 2026-Q3)`.
- Audit labels: "Added Intervention Record", "Updated Intervention Record", "Archived Intervention Record", "Restored Intervention Record" (from `Auditable`, subject "Intervention Record"); explicit: "Validated Intervention Record", "Claimed Intervention", "Claimed Intervention (Household Override)", "Unclaimed Intervention". Record label: `"{Full Name} - {Intervention} ({cycle code})"`, e.g. `Juan Dela Cruz - Certified Rice Seeds (2026-Q3)`.
- Figma list table (344:76, card 1488 wide): filters 230×39 at 23 / 278 / 533 / 788 (LGU 329:1423: five filters ≈199 wide at 23 / 248 / 472 / 697 / 922); header labels muted 14px at y 72; divider at 114; rows 45px pitch, text bold 14px; columns DA: name 38, RSBSA 289, barangay 549, intervention 804, Qty / Unit 1046; LGU: name 38, RSBSA 258, barangay 489, intervention 714, Registration 937; Validation chip 155×39 @1146, Status chip 155×39 @1308. Segmented toggle (344:230) 327×39 at top-right (8 from top): gradient track, selected segment white with dark text, other white text. Admin "Archive" pill 174×39, bold 20px, 12px below the card.
- AT dropdown chips (407:323): the 155×39 chip as a `<select>`, 4px border by tone, bold 14px centered, 18px arrow at right 12px; auto-submits on change.
- Archived table (344:236): search 168×30 pill; columns Name 38, RSBSA No. 258, Reason Deleted 430 (300 wide, wraps), Deleted On 750 (`M j, Y`), Deleted By 970 (username); Restore chip green, right-aligned.
- Types page (329:2475): "Select Intervention Type" card (61 tall); two `bg-brand-card` cards side by side, 246 tall, 23px gap; title bold 16px white at (13,13), subtitle 12px white; white pill button 31 tall inset 13px at the bottom, bold 16px ("View DA Beneficiary List" / "View LGU Beneficiary List").
- DE records list (430:1603): filters Name 230 @23, RSBSA 230 @278, Intervention ≈377 @533; columns name 38, RSBSA 289, intervention 549, Qty / Unit 941, Date Distributed 1045 (`M j, Y` or "-"), Action = claim dropdown chip ≈152 @1179, "Distribution Status" header over the Edit chip 98×39 @1366; "+ Add Record" button 230×39 top-right, fill `#7e80ff`, white bold 14px, radius 8.
- DE form (446:198): title bold 16px (no icon) + divider; `<x-ui.inline-field>` rows 44 tall, radius 10: full-width "Beneficiary", then 2 columns (23 gap): "Program" | "Intervention Type", "Quantity" | "Batch / Cycle", "Date Distributed" | "Distribution Status"; full-width `bg-brand-bar` button 47 tall "Save Intervention Record".
- Validation modal (337:18, 560 wide): title "Validate Beneficiary: {Full Name}", subtitle "{RSBSA No.} - Check all that apply, per DA/barangay cross-check" muted; option rows 22px boxes 40px pitch; gradient "Confirm Validation" bar; "Cancel" text link.

## Review Focus

1. **Two household members claimed at the same moment** — two admins press Process Claim for husband and wife on a one-per-household program; exactly one claim may succeed (row locks inside one transaction), the other gets the household message (Task 2).
2. **Archived records are invisible to every rule** — an archived claimed record must not block another member's claim, must not trigger the LGU duplicate flag, and must not count in stats or show in profile history; restoring a record whose slot is now taken by an active record is refused (Tasks 2, 4).
3. **Validation changed on a claimed record** — setting a claimed record to `pending`, `inactive`, `relocated` or `duplicate` is refused ("Unclaim it first."), leaving the record unchanged (Task 2).
4. **Same intervention twice** — assigning an intervention the beneficiary already has (active) in the same cycle, or assigning to an archived/missing beneficiary id, is refused with a field error, not a 500 (Tasks 2, 5).
5. **Hostile list input** — `?intervention[]=`, `?barangay=abc`, `?registration=bogus`, `/interventions/xyz` must give a normal page or 404, never a 500; text filters treat `%`/`_` literally (Tasks 3, 5).

---

## File Structure

```
app/Models/Intervention.php, DistributionCycle.php, InterventionRecord.php
app/Models/Beneficiary.php                      (modify: interventionRecords(), latestRecord(), registrationLabel())
app/Services/InterventionAssignment.php         assign() / reassign(): uniqueness + LGU duplicate flag
app/Services/ClaimService.php                   validate() / claim() / unclaim() / archive() / restore()
app/Exceptions/InterventionRuleViolation.php    DomainException; message shown to user
app/Http/Controllers/InterventionController.php        index (types), list (da|lgu), archived
app/Http/Controllers/InterventionRecordActionController.php  validate, claim, unclaim, archive, restore
app/Http/Controllers/ValidationQueueController.php     AT /validation
app/Http/Controllers/InterventionRecordController.php  DE index, create, store, edit, update
app/Http/Controllers/BeneficiaryLookupController.php   JSON autocomplete
app/Http/Requests/InterventionRecordRequest.php
app/Support/DashboardStats.php                  (modify: active_interventions, pending_validation)
database/migrations/2026_10_03_00000{1,2,3}_create_{interventions,distribution_cycles,intervention_records}_table.php
database/factories/InterventionRecordFactory.php
database/seeders/InterventionSeeder.php (programs + cycles), InterventionRecordSeeder.php (Figma samples)
database/seeders/BeneficiarySeeder.php, DatabaseSeeder.php (modify)
resources/views/components/intervention/{chip-select,record-table,segmented-toggle}.blade.php
resources/views/interventions/{index,list,archived}.blade.php
resources/views/validation/index.blade.php
resources/views/intervention-records/{index,form}.blade.php
resources/views/beneficiaries/show.blade.php, index.blade.php (modify)
resources/views/components/beneficiary/table.blade.php (modify)
routes/web.php (modify)
tests/Feature/{InterventionModel,ClaimService,InterventionList,InterventionArchive,InterventionRecords,BeneficiaryInterventions}Test.php
tests/visual/screens.spec.js, tests/visual/figma/*.png
```

---

### Task 1: Data model, programs, cycles and sample records

**Files:**
- Create: the three migrations, the three models, `InterventionRecordFactory`, `InterventionSeeder`, `InterventionRecordSeeder`
- Modify: `Beneficiary.php`, `BeneficiarySeeder.php` (Ana Gomez → `rejected`, reason "Not a landowner - farmworker under the municipal program"), `DatabaseSeeder.php` (call both new seeders after `BeneficiarySeeder`)
- Test: `tests/Feature/InterventionModelTest.php`

**Interfaces:**
- Produces:
  - `interventions`: id, `source` (string 3), name, unit nullable, `one_per_household` bool, `allow_repeat` bool, `is_active` bool default true, timestamps; unique (source, name).
  - `distribution_cycles`: id, `code` unique, label, `schedule_date` date nullable, venue nullable, `status` (scheduled/ongoing/completed), timestamps.
  - `intervention_records`: id, beneficiary_id, intervention_id, distribution_cycle_id (FKs, restrict on delete), `quantity` decimal(10,2) nullable, `validation_status` default `pending`, `claim_status` default `unclaimed`, `date_distributed` date nullable, `proxy_claimant` nullable, `proof_note` text nullable, `override_reason` text nullable, `validated_by`, `claimed_by`, `created_by` (nullable FKs to users), `delete_reason` nullable, `deleted_by` nullable, soft deletes, timestamps; index (beneficiary_id, intervention_id, distribution_cycle_id).
  - `Intervention`: consts `SOURCE_DA='da'`, `SOURCE_LGU='lgu'`, `SOURCES`; `records(): HasMany`; `scopeActive`; `sourceLabel(): string` ("DA"/"LGU").
  - `DistributionCycle::current(): ?self` (rule in Global Constraints).
  - `InterventionRecord` (Auditable subject "Intervention Record", SoftDeletes, HasFactory): consts `VALIDATION_*`, `VALIDATIONS` (ordered list of the 8), `CLAIMABLE`, `CLAIM_UNCLAIMED/CLAIMED`; relations `beneficiary()`, `intervention()`, `cycle()` (FK distribution_cycle_id), `claimer()`, `deleter()`; `static validationLabel(string): string`, `static validationTone(string): string`, `claimLabel(): string`, `claimTone(): string`, `quantityDisplay(): string`, `deLabel(): string`, `auditRecordLabel(): string`; `scopeOfSource(Builder, string)`; `auditIgnore = ['validated_by','claimed_by']`.
  - `Beneficiary::interventionRecords(): HasMany`, `latestRecord(): HasOne` (`latestOfMany()`), `registrationLabel(): string`, `const REGISTRATION_FILTERS = ['registered' => [...], 'new' => [...], 'unregistered' => [...]]` (status lists).
  - `InterventionRecordSeeder` creates, in `2026-Q3` unless noted (validation / claim / date / qty): DA — Juan Dela Cruz · Certified Rice Seeds · eligible / claimed / 2026-07-18 / 2; Rosa Mendez · Organic Liquid Fertilizer · `2026-Q2` · ofw / claimed / 2026-07-15 / 5; Carlos Ibanez · Complete Fertilizer · pending / unclaimed / – / 1; Liza Domingo · PAFF · `2026-Q1` · bedridden / unclaimed / – / null. LGU — Maria Santos · Complete Fertilizer · bedridden / unclaimed / – / 1; Pedro Reyes · Emergency Seedlings · pending / unclaimed / – / 10; Ana Gomez · Municipal Cash Subsidy · duplicate / unclaimed / – / null. Archived (reason · deleted by · deleted on): DA Federico Wasing · Certified Rice Seeds · "Deceased - confirmed by barangay" · Agritech_02 · 2026-07-08; DA Estrella Domogen · Complete Fertilizer · "Data correction - re-entered under new RSBSA no." · Encoder_03 · 2026-07-03; LGU Lorna Reyes · Emergency Seedlings · "Household already claimed via another member" · Admin_01 · 2026-07-12; LGU Teresa Ibanez · Municipal Cash Subsidy · "Withdrew registration voluntarily" · Agritech_02 · 2026-07-11. Idempotent (`updateOrCreate` on beneficiary + intervention + cycle, `withTrashed`). Writes bypass events like `BeneficiarySeeder` (seeded via `DatabaseSeeder`, which mutes events).

- [ ] **Step 1: Write the failing tests** — `InterventionModelTest.php`: `it('seeds the DA and LGU programs and the 2026 cycles')` (11 interventions, 3 cycles, `DistributionCycle::current()->code === '2026-Q3'`); `it('labels validation and claim states as in Figma')` (dataset of the 8 statuses → label + tone; claimed → "Claimed"/ok); `it('formats quantities and DE labels')` (2 sack → "2 sacks", 1 → "1 sack", 5 L → "5 L", null → "-"; `deLabel()` → `DA - Certified Rice Seeds (Batch 2026-Q3)`); `it('derives the LGU registration label')` (registered / endorsed / rejected → three labels); `it('seeds the Figma sample records idempotently')` (run `DatabaseSeeder` twice → 7 active + 4 archived records; Juan's record claimed; `Federico`'s archived with its reason).
- [ ] **Step 2: Run** `./vendor/bin/pest tests/Feature/InterventionModelTest.php` — Expected: FAIL (tables missing).
- [ ] **Step 3: Implement** migrations (`php artisan make:migration`), models, factory (defaults: random existing beneficiary via `Beneficiary::factory()`, first DA intervention, current cycle, pending/unclaimed, qty 1), seeders; update the Phase 3 seeder test only for Ana's new status if it asserts it.
- [ ] **Step 4: Run** the file then the full suite; `php artisan migrate --no-interaction` and `php artisan db:seed --class=InterventionSeeder --no-interaction` then `--class=InterventionRecordSeeder` on dev — Expected: PASS / all green / seeded.
- [ ] **Step 5: Commit** `feat: add interventions, distribution cycles and intervention records`.

---

### Task 2: Assignment and claim rules

**Files:**
- Create: `app/Exceptions/InterventionRuleViolation.php`, `app/Services/InterventionAssignment.php`, `app/Services/ClaimService.php`
- Test: `tests/Feature/ClaimServiceTest.php`

**Interfaces:**
- Consumes: Task 1 models; `AuditLogger::record()`; `Beneficiary::household_id`.
- Produces:
  - `InterventionAssignment::assign(Beneficiary $b, Intervention $i, DistributionCycle $c, array $attrs, User $actor): InterventionRecord` — refuses an archived beneficiary ("This beneficiary is archived.") and an existing **active** record for the same b/i/c ("{Full Name} already has {Intervention} in {code}."); sets `validation_status = duplicate` when `$i->source === lgu && ! $i->allow_repeat` and the beneficiary has an active **claimed** record of the same intervention in another cycle; else `pending`. `$attrs`: quantity, date_distributed, created_by.
  - `InterventionAssignment::reassign(InterventionRecord $r, array $attrs, User $actor): InterventionRecord` — quantity/date always editable; intervention or cycle change only while unclaimed ("Unclaim it first."), re-running the uniqueness + duplicate rules.
  - `ClaimService::validate(InterventionRecord $r, string $status, User $actor): InterventionRecord` — `$status` must be one of `VALIDATIONS`; a claimed record may only move between claimable statuses; sets `validated_by`; saves quietly; audit "Validated Intervention Record" with old/new status.
  - `ClaimService::claim(InterventionRecord $r, User $actor, array $input = [], bool $historical = false): InterventionRecord` — `$input`: `date_distributed` (default today, not in the future), `quantity`, `proxy_claimant`, `proof_note`, `override_reason`. Rules in order: archived → "This record is archived."; already claimed → "Already claimed."; `pending` → "Validate eligibility first." (skipped when `$historical`, which also sets `eligible`); not claimable → "Not eligible: {Label}."; `deceased` without both proxy fields → "A deceased beneficiary can only be claimed by a proxy with a proof note."; household block (below). Runs in one `DB::transaction` that `lockForUpdate()`s every active record of the same intervention + cycle within the household before checking. Audit "Claimed Intervention" or, when overridden, "Claimed Intervention (Household Override)" with `override_reason` in new values.
  - Household block: intervention `one_per_household`, beneficiary has a household, and another member's active record of the same intervention + cycle is claimed → "{Other Full Name} already claimed {Intervention} for this household in {code}." unless `override_reason` is non-empty **and** `$actor->role?->slug === Role::ADMIN`. A non-admin passing an override still gets the block.
  - `ClaimService::unclaim(InterventionRecord $r, User $actor): InterventionRecord` — claimed → unclaimed, clears date/proxy/proof/override; audit "Unclaimed Intervention".
  - `ClaimService::archive(InterventionRecord $r, string $reason, User $actor): void` — reason required (≤255, "Enter a reason."), sets `delete_reason`, `deleted_by`, then `delete()` (Auditable writes "Archived Intervention Record").
  - `ClaimService::restore(InterventionRecord $r, User $actor): InterventionRecord` — refuses when an active record now holds the same b/i/c ("An active record already exists for this intervention and cycle."); clears reason/by; `restore()`.
  - Input validation inside services throws `ValidationException` (field-keyed); rule breaks throw `InterventionRuleViolation`.

- [ ] **Step 1: Write the failing tests** — one per rule, e.g.:

```php
it('blocks a second claim in the same household and cycle for one-per-household programs', function () {
    [$juan, $maria] = sameHousehold(2);                        // helper: two beneficiaries, same address/barangay
    $paff = Intervention::where(['source' => 'da', 'name' => 'PAFF'])->first();
    $a = record($juan, $paff, ['validation_status' => 'eligible']);
    $b = record($maria, $paff, ['validation_status' => 'eligible']);
    app(ClaimService::class)->claim($a, $this->admin);

    expect(fn () => app(ClaimService::class)->claim($b, $this->agritech))
        ->toThrow(InterventionRuleViolation::class, 'Juan Dela Cruz already claimed PAFF for this household in 2026-Q3.');
    expect(fn () => app(ClaimService::class)->claim($b, $this->agritech, ['override_reason' => 'Separate farm']))
        ->toThrow(InterventionRuleViolation::class);           // only an administrator may override
    app(ClaimService::class)->claim($b, $this->admin, ['override_reason' => 'Separate farm, separate RSBSA']);
    expect($b->fresh()->claim_status)->toBe('claimed')
        ->and(AuditLog::where('action', 'Claimed Intervention (Household Override)')->exists())->toBeTrue();
});
```

Also: `it('ignores archived records for the household rule and the LGU duplicate flag')`; `it('lets non-household programs be claimed by every member')`; `it('requires validation before a claim, except for historical encoding')`; `it('refuses ineligible statuses')` (dataset inactive/relocated/duplicate); `it('requires a proxy and proof note for deceased beneficiaries')`; `it('refuses to move a claimed record to a non-claimable status')`; `it('flags a repeated LGU assistance as Duplicate unless repeats are allowed')` (Complete Fertilizer LGU claimed in Q2 → Q3 assignment `duplicate`; Emergency Seedlings stays `pending`); `it('refuses the same intervention twice in one cycle and archived beneficiaries')`; `it('archives with a reason and restores unless the slot is taken')`; `it('unclaims and writes audit rows')`; `it('refuses a future distribution date')`. Add `sameHousehold(int $n)` and `record(Beneficiary, Intervention, array)` helpers to `tests/Pest.php`.
- [ ] **Step 2: Run** `./vendor/bin/pest tests/Feature/ClaimServiceTest.php` — Expected: FAIL (classes missing).
- [ ] **Step 3: Implement** the exception and the two services (constructor-injectable, no static state).
- [ ] **Step 4: Run** the file then the full suite — Expected: PASS / all green.
- [ ] **Step 5: Commit** `feat: add intervention assignment and claim rules with household block`.

---

### Task 3: Intervention types page, DA/LGU lists and AT validation queue

**Files:**
- Create: `InterventionController` (`index`, `list`), `InterventionRecordActionController` (`validate`, `claim`, `unclaim`), `ValidationQueueController`, `components/intervention/{chip-select,record-table,segmented-toggle}.blade.php`, `interventions/index.blade.php`, `interventions/list.blade.php`, `validation/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InterventionListTest.php`

**Interfaces:**
- Consumes: Task 2 `ClaimService`.
- Produces:
  - Routes: `GET /interventions` → `interventions.index` (`can:interventions.view`), page title "INTERVENTION TYPES"; `GET /interventions/da` → `interventions.da`, `GET /interventions/lgu` → `interventions.lgu` (both `InterventionController@list`, source from the route default, `can:interventions.view`), titles "DA INTERVENTION LIST" / "LGU INTERVENTION LIST"; `POST /intervention-records/{record}/validation` → `intervention-records.validate` (`can:interventions.validate`, field `validation_status`); `POST /intervention-records/{record}/claim` → `intervention-records.claim` and `/unclaim` → `intervention-records.unclaim` (`can:interventions.claim`, claim input fields of Task 2); `GET /validation` → `validation.index` (`can:interventions.validate`), title "BENEFICIARY VALIDATION". Action routes redirect back with status "{record label}: {new state label}." or errors in bag `intervention` (+ flash `intervention_failed` = record id).
  - List query: active records of the source, filters `name` (scopeSearch on beneficiary), `rsbsa` (literal LIKE, `!` escape), `barangay` (id), `intervention` (id of that source), LGU only `registration` (`registered|new|unregistered`); newest cycle first then last name; paginate 15 with query string. Intervention filter options = that source's interventions.
  - Variant: role `administrator` → `<x-ui.status-chip>` read-only chips, card toggle (when `can('interventions.archive')`), Archive pill (Task 4); otherwise validation chip is `<x-intervention.chip-select>` when `can('interventions.validate')` and status chip is one when `can('interventions.claim')`, else read-only chips; no toggle. Choosing "Claimed" in a status dropdown posts to claim (no extra input; deceased/override need the profile modal, Task 6 — the service error says so); "Unclaimed" posts to unclaim.
  - `<x-intervention.chip-select :name :options :selected :tone :action>` — a one-field form; `<x-intervention.record-table :records :source :variant>` — the 344:76 table; `<x-intervention.segmented-toggle :active :source>` — the 344:230 toggle linking `interventions.{source}` and `interventions.archived`.
  - Beneficiary names in the table link to `beneficiaries.show`.
  - Validation queue: pending active records of both sources, columns Name / RSBSA / Barangay / Intervention (`deLabel()`) / Validation chip-select; empty "No records are waiting for validation."
- [ ] **Step 1: Write the failing tests** — `it('shows the two intervention types')` (both card titles, both "View … Beneficiary List" links); `it('lists DA records with Figma columns for the administrator')` (seeded data: `assertSeeInOrder(['Juan Dela Cruz','RSBSA-0231','Poblacion','Certified Rice Seeds','2 sacks','Eligible','Claimed'])`, sees "Archive", no `<select name="validation_status"`); `it('gives agri techs validation and status dropdowns')` (sees `name="validation_status"` and `name="claim_status"`, no "Archive"); `it('adds the registration column and filter on LGU')` (three registration labels; `?registration=new` shows only Pedro Reyes); `it('filters by name, RSBSA, barangay and intervention')` (dataset); `it('treats hostile filters as plain text')` (dataset `intervention[]=1`, `barangay=abc`, `registration=bogus`, `name=%25` → 200); `it('404s an unknown source')`; `it('validates and claims through the dropdown routes')` (AT posts `eligible` on Carlos → chip "Eligible"; posts claim → Claimed; posting claim on Pedro (pending) → error "Validate eligibility first."); `it('lists pending records in the validation queue')`; `it('forbids encoders from the DA and LGU lists')`.
- [ ] **Step 2: Run** — Expected: FAIL (routes undefined).
- [ ] **Step 3: Implement** controllers, components, views, routes.
- [ ] **Step 4: Run** file then full suite; `npm run build` — Expected: PASS / all green.
- [ ] **Step 5: Commit** `feat: add DA and LGU intervention lists with validation and claim dropdowns`.

---

### Task 4: Archive and Archived/Restore tabs

**Files:**
- Create: `interventions/archived.blade.php`
- Modify: `InterventionController` (`archived`), `InterventionRecordActionController` (`archive`, `restore`), `interventions/list.blade.php` (Archive modal), `routes/web.php`
- Test: `tests/Feature/InterventionArchiveTest.php`

**Interfaces:**
- Consumes: Task 2 `archive()` / `restore()`; Task 3 toggle and table.
- Produces:
  - Routes (all `can:interventions.archive`): `GET /interventions/{source}/archived` → `interventions.archived` (`whereIn source da|lgu`), card title "DA Intervention - Recently Deleted" / "LGU Intervention - Recently Deleted", search `q` on beneficiary name, newest deletion first, columns per Global Constraints, Restore chip posts; `POST /intervention-records/{record}/archive` → `intervention-records.archive` (field `reason`); `POST /intervention-records/{record}/restore` → `intervention-records.restore` (route binding `->withTrashed()`).
  - Archive pill opens `<x-ui.modal name="archive-record" title="Archive Intervention Record">` with a `<select name="record">` of the records on the current page ("{Full Name} - {Intervention} ({code})") and an inline "Reason" field; the form posts to the chosen record's archive URL (Alpine binds the action). Empty page → the pill is disabled.
  - Empty archived list: "No archived records."
- [ ] **Step 1: Write the failing tests** — `it('archives a record with a reason and lists it under Archived/Restore')` (post reason → record soft-deleted, `delete_reason` stored, archived page shows name, reason, `M j, Y`, deleter username; DA list no longer shows it; audit "Archived Intervention Record"); `it('requires a reason')`; `it('restores an archived record')` (audit "Restored Intervention Record"; back in the list); `it('refuses to restore into a taken slot')`; `it('keeps archive and restore to holders of interventions.archive')` (AT gets 403 on both posts and the archived page).
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** file then full suite — Expected: PASS.
- [ ] **Step 5: Commit** `feat: add intervention record archive and restore`.

---

### Task 5: Data Encoder intervention records (list, add, edit)

**Files:**
- Create: `InterventionRecordController`, `InterventionRecordRequest`, `BeneficiaryLookupController`, `intervention-records/index.blade.php`, `intervention-records/form.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InterventionRecordsTest.php`

**Interfaces:**
- Consumes: Task 2 `InterventionAssignment`, `ClaimService::claim(..., historical: true)` / `unclaim()`; Task 3 `<x-intervention.chip-select>`.
- Produces:
  - Routes (all `can:intervention_records.manage`): `GET /intervention-records` → `intervention-records.index` (title "INTERVENTION RECORDS"); `GET /intervention-records/create` → `.create`; `POST /intervention-records` → `.store`; `GET /intervention-records/{record}/edit` → `.edit`; `PUT /intervention-records/{record}` → `.update`; `GET /beneficiary-lookup?q=` → `beneficiaries.lookup` returning `[{id, name, rsbsa, barangay}]` (max 10, `scopeSearch`, non-archived only).
  - List: both sources, filters `name`, `rsbsa`, `intervention` (all interventions, option text "DA - Name"/"LGU - Name"); Action column = claim chip-select (posts Task 3 claim/unclaim routes; DE holds `interventions.claim`); Edit chip → edit form; paginate 15.
  - `InterventionRecordRequest` fields: `beneficiary_id` (required, exists among non-archived beneficiaries; message "Choose a beneficiary from the search results."), `source` (`da|lgu`), `intervention_id` (required, exists, must belong to `source`: "Choose an intervention from the selected program."), `quantity` (nullable numeric ≥0 ≤99999), `distribution_cycle_id` (required, exists), `date_distributed` (required_if distributed, date, ≤ today), `distribution_status` (`distributed|not_distributed`).
  - Store: `assign()`, then when `distributed` → `claim($r, $user, [...], historical: true)` in the same transaction (a household block or other rule rolls the whole save back and shows the message on `distribution_status`). Update: `reassign()`, then claim/unclaim to match `distribution_status`.
  - Form: Alpine beneficiary autocomplete over `/beneficiary-lookup` (hidden `beneficiary_id`, visible text input; typed text without picking a result leaves the id empty → the request error); Program select filters Intervention Type options client-side; Batch / Cycle select shows codes; success → `intervention-records.index` with status "Intervention record saved."
- [ ] **Step 1: Write the failing tests** — `it('lists records with Figma columns for the encoder')` (`assertSeeInOrder(['Juan Dela Cruz','RSBSA-0231','DA - Certified Rice Seeds (Batch 2026-Q3)','2 sacks','Jul 18, 2026','Claimed','Edit'])`); `it('adds a not-yet-distributed record')` (→ pending/unclaimed, audit "Added Intervention Record"); `it('adds a distributed record as a historical claim')` (→ eligible/claimed, date stored); `it('rolls back a distributed record that breaks the household rule')` (no record created; error on `distribution_status`); `it('refuses unknown or archived beneficiaries and mismatched programs')` (dataset → field errors, no 500); `it('refuses the same intervention twice in a cycle')`; `it('edits quantity and blocks intervention changes on claimed records')`; `it('returns beneficiary lookup results')` (JSON shape, hostile `q[]=` → `[]`); `it('forbids agri techs')`.
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** file then full suite; `npm run build` — Expected: PASS.
- [ ] **Step 5: Commit** `feat: add Data Encoder intervention records with add and edit forms`.

---

### Task 6: Beneficiary profile — history, household claims, Process Claim and Verify Eligibility

**Files:**
- Modify: `BeneficiaryController@show`, `beneficiaries/show.blade.php`
- Test: `tests/Feature/BeneficiaryInterventionsTest.php`

**Interfaces:**
- Consumes: Task 2 services (via Task 3 action routes), Task 3 chip-select.
- Produces:
  - Intervention History rows: the beneficiary's active records, newest cycle first: Date (`date_distributed` or the cycle's `schedule_date`, formatted `M Y`, else the cycle code), Source (DA/LGU), Intervention, Barangay, Status chip (DE edit variant: claim chip-select). Empty text unchanged.
  - Banner suffix: if another household member has a claimed active record in the current cycle → `- {Name} already claimed {Intervention} this cycle.` (first such claim, newest first); else `- no other claims made yet.` (unchanged wording).
  - Admin "Process Claim": enabled when the beneficiary has an unclaimed record whose status is claimable; opens `<x-ui.modal name="process-claim" title="Process Claim - {Full Name}">` with a record select, Date Distributed (default today), and — shown when the chosen record is `deceased` — Proxy Claimant + Proof Note, plus an "Override reason (household already claimed)" field; posts to `intervention-records.claim` for the chosen record. Otherwise disabled with title "No claimable interventions."
  - AT "Verify Eligibility": enabled when the beneficiary has any active record; opens the 337:18 modal (title, subtitle, a record select when more than one, option rows for the 8 statuses as radio inputs, "Confirm Validation", "Cancel") posting to `intervention-records.validate`. Otherwise disabled with title "No intervention records to verify."
  - Errors from the bag `intervention` re-open the modal (flash `intervention_failed`), as the RSBSA card does.
- [ ] **Step 1: Write the failing tests** — `it('shows intervention history newest first')` (`assertSeeInOrder` of Juan's rows); `it('reports a household member\'s claim in the banner')` (Maria claims PAFF in Q3 → Juan's banner "Maria Dela Cruz already claimed PAFF this cycle."); `it('processes a claim from the admin profile')` (post through the modal fields → claimed); `it('requires proxy fields for a deceased claim from the profile')`; `it('lets the admin override the household block with a reason')`; `it('verifies eligibility from the agri tech profile')`; `it('disables the buttons when there is nothing to process')` (beneficiary without records → `disabled` + both titles); `it('gives the encoder claim dropdowns in history')`.
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** file then full suite; `npm run build` — Expected: PASS.
- [ ] **Step 5: Commit** `feat: enable Process Claim and Verify Eligibility with intervention history`.

---

### Task 7: Live Intervention/Status columns and counters

**Files:**
- Modify: `components/beneficiary/table.blade.php`, `Beneficiary::scopeForTable` (eager `latestRecord.intervention`), `beneficiaries/index.blade.php` + `BeneficiaryController@index` (Intervention filter options, Status column), `DashboardStats.php`
- Test: extend `tests/Feature/DashboardBeneficiariesTest.php`, `tests/Feature/BeneficiaryListTest.php`

**Interfaces:**
- Produces:
  - Shared table + DE list: Intervention = latest active record's intervention name ("—" if none); Status = its claim chip ("—" if none).
  - DE list Intervention filter: options = all interventions (`DA - Name` / `LGU - Name`); filter = has an active record of that intervention.
  - `DashboardStats`: `active_interventions` = number of distinct interventions with ≥1 active record in the current cycle; `pending_validation` = active records with `validation_status = pending`.
- [ ] **Step 1: Write the failing tests** — `it('shows each beneficiary\'s latest intervention and claim status')` (dashboard row `Juan Dela Cruz … Certified Rice Seeds … Claimed`); `it('filters the DE list by intervention')`; `it('counts active interventions and pending validations live')` (seeded Q3 active records cover 5 distinct interventions — DA Certified Rice Seeds, DA Complete Fertilizer, LGU Complete Fertilizer, LGU Emergency Seedlings, LGU Municipal Cash Subsidy → 5; pending → 2 (Carlos, Pedro); archiving Carlos's record → 4 and 1).
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** files then full suite — Expected: PASS.
- [ ] **Step 5: Commit** `feat: show live intervention columns and counters`.

---

### Task 8: Figma fidelity check

**Files:**
- Modify: `tests/visual/screens.spec.js`; add `tests/visual/figma/{329-2475,329-1250,344-236,329-1423,407-323,407-603,430-1603,446-198}.png` (export with Figma MCP `get_screenshot`, fileKey `ZYDqjMYN1h4OBNbQUpadrk`, `maxDimension: 1820`)
- Modify: any Phase 4 view whose layout misses the Global Constraints numbers

- [ ] **Step 1:** Add SCREENS: `/interventions` (Admin_01), `/interventions/da` (Admin_01, Agritech_02), `/interventions/da/archived` (Admin_01), `/interventions/lgu` (Admin_01, Agritech_02), `/intervention-records` and `/intervention-records/create` (Encoder_03).
- [ ] **Step 2: Run** `npm run visual` — Expected: harness passes; compare side-by-side crops of each card; fix structural mismatches (>2px offsets, wrong sizes/colors, wrapped text). Remaining diff only: logo (real logo vs placeholder square), sample-data differences ruled in Task 1, font anti-aliasing.
- [ ] **Step 3:** Full suite green; `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 4: Commit** `test: add Phase 4 screens to the visual harness`.

---

## Self-Review Notes

- **Spec coverage:** §4 interventions/cycles/records → T1; §5.3 archive/restore → T2, T4; §5.4 records only for existing profiles → T2 (`assign` refuses archived), T5 (lookup + request); §5.5 household one-per-cycle block + admin override + reason logged → T2, T6; §5.6 validation, deceased proxy, LGU duplicate flag → T2, T3, T6; §5.13 Active Interventions / Pending Validation → T7; §6a 24 → T1, 25 → T3, 26 → T3/T4, 27 → T4, 28 → T2/T3/T6, 29 → T5, 30 → T2–T5 tests. Inventory auto-deduct (§5.7, step 32) is deliberately Phase 5.
- **Rulings taken while planning:**
  - Admin sees read-only chips per Figma and acts through the profile's Process Claim; AT/DE get dropdowns. Variant by role slug, gated by permission — same as Phase 3.
  - DE "Distributed" records are historical encodings: they skip only the pending-validation check (and are marked `eligible`); every other rule still applies.
  - The household override is admin-only (no permission exists for it); the Figma validation modal's checkboxes become one radio choice because `validation_status` is a single value.
  - Juan's DA sample is seeded `eligible` (Figma shows "Validate" + "Claimed", which the rules forbid).
  - Archived samples reuse seeded people instead of adding 4 new beneficiaries.
  - Ana Gomez is seeded `rejected` so the LGU "Unregistered (Eligible)" sample is real.
- **Deferred to Phase 5:** `inventory_item_id` on interventions, stock-out on claim/reversal on unclaim, insufficient-stock block, Low Stock Items counter.
