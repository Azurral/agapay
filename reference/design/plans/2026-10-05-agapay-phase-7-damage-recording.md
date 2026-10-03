# AGAPAY Phase 7 (Agricultural Damage Recording) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** After a typhoon or other disaster, OMAG staff file a damage report per affected farmer, with crop, stage, damaged area, GPS position and photos. AGAPAY computes the production loss and cost from crop reference values. The Agricultural Technologist (or Admin) validates each report, adjusting the figures if needed. Everyone with access sees the per-disaster summary cards and table, and can export it to Excel (Admin) or a PDF report. The screens match Figma 329:2978, 407:1554, 470:1863, 423:786 and 423:306.

**Architecture:**
- **Data:** four new tables: `disasters`, `crops`, `damage_reports` and `damage_photos`.
- **Calculation:** a pure `DamageCalculator` computes loss and cost.
- **Saving:** `DamageReportService` files, updates, validates, archives and restores reports. It runs in transactions and writes audit rows.
- **Pages:** one controller per page (list, form, detail and actions, reference values, exports).
- **Crop values are snapshotted on each report.** Later edits to crop values never silently change filed or validated figures.

**Tech Stack:** Laravel 13 / PHP 8.5, MariaDB 10.4 (READ COMMITTED) / SQLite in-memory tests, Pest 4, Blade + Tailwind 4 + Alpine 3. Both packages below are already installed:
- `barryvdh/laravel-dompdf` 3.1 for the PDF;
- `maatwebsite/excel` 4 for the .xlsx.

**Spec:** `docs/superpowers/specs/2026-09-28-agapay-design.md`, sections:
- §3: the `/damage-reports` row;
- §4: `disasters`, `crops`, `damage_reports`, `damage_photos`;
- §5: rules 2, 3, 11 and 13;
- §6: Phase 7, steps 38–40.

Previous plans this builds on:
- Phase 3: `Beneficiary`, the `Auditable` trait, `BeneficiaryLookupController`.
- Phase 5: the modal and inline-field components, the pagination view.
- Phase 6: the drop-zone pattern and the text value binder for exports.

## Global Constraints

**Git**
- Branch `feature/phase-7-damage-recording` from `feature/phase-6-import-export` (Phase 6 is finished but not merged yet).
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

**Permissions**
- `damage.view` (Admin, AT, DE): the list page, the detail page and the PDF.
- `damage.create` (Admin, AT, DE): the form.
- `damage.validate` (Admin, AT): validate and adjust.
- `export.run` (Admin): Export to Excel. Figma shows that button only on the Admin screen.
- New `damage.configure` ("Manage disasters and crop reference values", group "Damage Recording"; Admin by default).
  - Add it to `PermissionCatalog::PERMISSIONS`.
  - Add a migration that inserts it and attaches it to the `admin` role in existing databases.
- Archive and restore use `damage.configure`.

**Pages**
- Page titles:
  - "AGRICULTURAL DAMAGE REPORT" (list and detail);
  - "NEW DAMAGE REPORT" (form);
  - "EDIT DAMAGE REPORT" (editing an unvalidated report).
- The quick action "File Disaster Report" (AT) → `damage.create`, and "Disaster Reports" (DE) → `damage.index`. These are already in config.
- The Data Encoder's list screen also shows "+ New Damage Report" (the paper requires it; Figma 470:1863 lacks it).

**Formula (spec rule 11)**
- `loss_mt = round((total_area_ha + partial_loss_factor × partial_area_ha) × yield_mt_per_ha, 2)`.
- `cost = round(loss_mt × price_per_mt, 2)`. Cost uses the rounded loss, so the table adds up.
- The default `partial_loss_factor` is 0.50.
- `yield_mt_per_ha`, `price_per_mt` and `partial_loss_factor` are copied onto the report when it is filed or edited. They are not re-read later.

**Seeded data**
- **Crops** (yield MT/ha · farmgate ₱/MT · factor):
  - Rice 4.00 · 20,000 · 0.50
  - Corn 3.50 · 15,000 · 0.50
  - Cabbage 20.00 · 15,000 · 0.50
  - Carrots 18.00 · 25,000 · 0.50
  - Potato 15.00 · 30,000 · 0.50
  - Beans 2.00 · 60,000 · 0.50
  - Sweet Potato 10.00 · 20,000 · 0.50
- **Disasters:** "Typhoon Cristina" (2026-07-20) and "Southwest Monsoon Flooding" (2026-06-12).

**Crop stages** (value → label): `newly_planted` Newly Planted, `vegetative` Vegetative, `reproductive` Reproductive, `maturing` Maturing, `harvested` Harvested.

**Statuses**
- `for_validation`, labelled "For Validation" (pink `border-bad` chip).
- `validated`, labelled "Validated" (green `border-ok` chip).
- Archived reports are soft-deleted, with `delete_reason` and `deleted_by`.

**Form rules** (field: rule → error message)
- `disaster_id`: required, must exist.
- `beneficiary_id`: required, an existing non-archived profile → "Choose a farmer from the list."
- `barangay_id`: required, must exist.
- `crop_id`: required, must exist.
- `farm_location`: nullable, at most 255 characters.
- `crop_stage`: one of the stages above.
- `total_area_ha` and `partial_area_ha`: numeric, 0–9999.99, default 0. Their sum must be > 0 → "Enter the damaged area."
- `latitude` and `longitude`: nullable. Latitude between −90 and 90, longitude between −180 and 180. Both or neither → "Enter both latitude and longitude."
- `photos[]`: up to 10 files, each `mimes:jpg,jpeg,png` and max 5,120 KB → "Photos must be JPG or PNG files up to 5 MB."
- Duplicate check → "{Full Name} already has a {Crop} damage report for {Disaster}."
  - Applies to the same beneficiary + disaster + crop among non-archived reports.
  - Checked under a lock on the beneficiary row.

**Photos**
- Stored on the `public` disk under `damage/{report_id}/`, with a random name.
- Served only through the authenticated route `damage-photos/{photo}`, so no `storage:link` is needed.

**Summary cards (Figma 423:133–148)**
- The cards use the filtered, non-archived reports.
- Farmers Affected is a distinct beneficiary count.
- Total Area Damaged (ha) is Σ(total + partial).
- Production Loss (MT) is Σ loss.
- Est. Cost of Damage (₱) is Σ cost.
- Number formats:
  - counts use thousands separators;
  - decimals drop trailing zeros ("842", "1.35");
  - cost is shown in full under 1,000,000 ("54,000"); from 1,000,000 up it is "x.xM" ("4.2M").

**Table columns** (Figma 423:151–158; the cell format follows each name)
- Name
- Barangay
- Crop / Farm Loc. (crop name; the farm location appears as a muted second line when present)
- Crop Stage
- Damaged Area (Total/Partial): "1.20 ha / 0.30 ha"
- Loss (MT): "5.4 MT", trailing zeros dropped
- Cost of Damage: "₱108,000" (no decimals unless there are centavos)
- Photos: "3 (view)", where "(view)" is muted; "0" with no link
- Status chip

**Filters (GET)**
- `disaster`: an id or `all`. The default is the disaster with the latest `occurred_on`.
- `barangay`: an id or `all`.
- `status`: `all`, `for_validation`, `validated` or `archived`. Only users with `damage.configure` see `archived`.

**Table and footer**
- The table paginates 10 per page with the existing `pagination.agapay` view, keeping the query string.
- The footer shows "Export to Excel" (with `export.run`) and "Generate PDF Report".

**Audit labels**
- "Filed Damage Report"
- "Updated Damage Report"
- "Validated Damage Report"
- "Archived Damage Report"
- "Restored Damage Report"
- "Exported Damage Reports"
- "Generated Damage Report PDF"
- "Updated Crop Reference Values"
- "Added Disaster"

Record labels are "{Full Name} · {Crop} · {Disaster}".

**AT sidebar stat:** "Reports Filed This Month" (currently a hard-coded 0) becomes the count of non-archived damage reports created this calendar month.

## Review Focus

1. **Photo uploads from phones and odd files.**
   - Examples: a renamed .exe as .jpg, a HEIC, an 8 MB photo, 11 photos, or a POST over `post_max_size`.
   - Expected: the form comes back with the photo error and the other fields kept. Never a 500, and nothing stored for a refused submission (Task 2).
2. **Area and GPS input as people type it.**
   - Examples: "1,5", negative, both areas 0, latitude without longitude, "17.0894° N".
   - Expected: field errors, not 500s. The formula's rounding is identical across the table, cards, Excel and PDF (Tasks 1–3, 6).
3. **Reference values edited after filing.**
   - Expected: changing Rice's price leaves filed and validated reports' loss and cost unchanged; only new and re-saved reports use the new values (Tasks 1, 5).
4. **Double submits and races.**
   - Examples: a double-clicked Submit, or Validate clicked twice.
   - Expected: exactly one report, and one "Validated" audit row. A second validation says "This report is already validated." (Tasks 2, 4).
5. **Empty and large result sets.**
   - Examples: a disaster with no reports, a barangay filter with nothing, or 500 reports.
   - Expected: the cards show 0, and Excel and PDF still download (empty table, or all rows) without timeouts or crashes (Tasks 3, 6).

---

## File Structure

```
database/migrations/2026_10_05_00000{1..5}_*.php   disasters, crops, damage_reports, damage_photos, damage.configure permission
app/Models/Disaster.php, Crop.php, DamageReport.php, DamagePhoto.php
database/factories/DamageReportFactory.php
database/seeders/DamageSeeder.php                   crops, disasters, 6 sample reports (Figma rows, no photos)
app/Services/DamageCalculator.php                   pure formula
app/Services/DamageReportService.php                file / update / validate / archive / restore
app/Http/Requests/DamageReportRequest.php
app/Http/Controllers/DamageReportController.php     index, create, store, show, edit, update
app/Http/Controllers/DamageReportActionController.php  validate, archive, restore
app/Http/Controllers/DamagePhotoController.php      show (stream)
app/Http/Controllers/DamageReferenceController.php  updateCrops, storeDisaster
app/Http/Controllers/DamageExportController.php     excel, pdf
app/Exports/DamageReportExport.php
resources/views/damage/{index,form,show,pdf}.blade.php
tests/Feature/{DamageCalculator,DamageForm,DamageList,DamageValidation,DamageReference,DamageExport}Test.php
```

---

### Task 1: Data model, calculator and seed data

**Files:**
- Migrations, models, factory and seeder as listed above.
- `PermissionCatalog`: add `damage.configure`.
- `DatabaseSeeder`: call `DamageSeeder` after `InventorySeeder`.
- `DamageCalculator`.
- `DashboardStats`: `reports_filed_this_month`.
- Test: `tests/Feature/DamageCalculatorTest.php`.

**Interfaces:**
- **`disasters` table:** id, name (unique, 100), occurred_on date, timestamps.
- **`crops` table:** id, name (unique, 50), yield_mt_per_ha decimal(8,2), price_per_mt decimal(12,2), partial_loss_factor decimal(4,2) default 0.50, timestamps.
- **`damage_reports` table:**
  - Links: disaster_id, beneficiary_id, barangay_id, crop_id (all FKs).
  - Field data: farm_location nullable, crop_stage string(20), total_area_ha decimal(8,2), partial_area_ha decimal(8,2).
  - Snapshot: yield_mt_per_ha, price_per_mt, partial_loss_factor.
  - Results: loss_mt decimal(10,2), cost decimal(14,2).
  - GPS: latitude decimal(9,6) nullable, longitude decimal(9,6) nullable.
  - Validation: status string(20) default `for_validation`, adjustment_note text nullable, reported_by FK users, validated_by FK users nullable, validated_at nullable.
  - Archiving: softDeletes, delete_reason nullable, deleted_by nullable FK.
  - timestamps; index (disaster_id, status).
- **`damage_photos` table:** id, damage_report_id FK cascade, path, original_name, size int, timestamps.
- **`DamageReport` model:**
  - Traits: `Auditable`, `SoftDeletes`, `HasFactory`.
  - Constants: `FOR_VALIDATION`, `VALIDATED`, `STAGES` (value => label), `STATUS_LABELS`.
  - Relations: `disaster`, `beneficiary` (withTrashed), `barangay`, `crop`, `photos`, `reporter`, `validator`.
  - Methods: `auditRecordLabel()` returns "{Full Name} · {Crop} · {Disaster}"; `statusLabel()`; `statusTone()` returns 'ok' or 'bad'; `areaLabel()` returns "1.20 ha / 0.30 ha".
  - Casts: decimals as `decimal:2`, dates.
- **`DamageCalculator::calculate(float $totalHa, float $partialHa, float $factor, float $yieldMtPerHa, float $pricePerMt): array{loss_mt: float, cost: float}`**: the Global Constraints formula.
- **`DamageCalculator::forCrop(Crop $crop, float $totalHa, float $partialHa): array{loss_mt, cost, yield_mt_per_ha, price_per_mt, partial_loss_factor}`**: returns the snapshot plus the results.
- **`DamageCalculator::compact(float $value): string`**: the card number format.

**Steps**
- [ ] **Step 1: Failing tests:**
  - `it('computes loss and cost from crop values')`: Rice, 1.20 total / 0.30 partial → `['loss_mt' => 5.4, 'cost' => 108000.0]`.
  - `it('weights partial damage by the crop factor')`: factor 0.25, 0 / 2.00 on Rice → loss 2.0.
  - `it('rounds cost from the rounded loss')`: yield 3.333, total 1 → loss 3.33, cost 3.33 × price.
  - `it('formats card numbers')`: dataset 842 → "842", 1.35 → "1.35", 54000 → "54,000", 4200000 → "4.2M", 1204 → "1,204".
  - `it('seeds crops, disasters and sample reports')`: 7 crops; Typhoon Cristina exists; 6 reports with snapshot values; loss equals the calculator's result.
  - `it('counts damage reports filed this month for the agri tech stat')`.
  - `it('gives damage.configure to the administrator only')`.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/DamageCalculatorTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement.** The seeder files the 6 Figma sample rows against existing seeded beneficiaries, with a mix of statuses (4 validated, 2 for validation) and no photos, using `DamageCalculator::forCrop`.
- [ ] **Step 4: Run** the file, then the full suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat: add disasters, crops and damage reports with the loss formula`.

---

### Task 2: New Damage Report form (file and edit)

**Files:**
- Create `DamageReportService` (`file`, `update`), `DamageReportRequest`, `DamageReportController` (`create`, `store`, `edit`, `update`), `DamagePhotoController`, and `resources/views/damage/form.blade.php`.
- Modify `routes/web.php`. The `beneficiaries.lookup` route moves to `can.any:intervention_records.manage,damage.create`.
- Test: `tests/Feature/DamageFormTest.php`.

**Interfaces:**
- **Routes:**

  | Method and path | Name | Permission |
  |---|---|---|
  | `GET /damage-reports/create` | `damage.create` | `damage.create` |
  | `POST /damage-reports` | `damage.store` | `damage.create` |
  | `GET /damage-reports/{report}/edit` | `damage.edit` | see below |
  | `PUT /damage-reports/{report}` | `damage.update` | see below |
  | `GET /damage-photos/{photo}` | `damage.photos.show` | `damage.view`; streams with the file's mime type |

  - Edit and update are allowed only while the report is `for_validation`, and only for its reporter or a holder of `damage.configure`; otherwise 403.
- **`DamageReportService::file(array $data, array $photos, User $actor): DamageReport`**
  - Runs one transaction with `attempts: 3`.
  - Locks the beneficiary row.
  - Throws a `ValidationException` on `beneficiary_id` for the duplicate rule.
  - Snapshots the crop values through `forCrop`.
  - Stores the photos after the row exists.
  - If the transaction fails, deletes the stored files.
- **`DamageReportService::update(DamageReport $report, array $data, array $newPhotos, array $removePhotoIds, User $actor): DamageReport`**
  - Re-snapshots the crop values and recalculates.
  - Refuses a validated report: "This report is already validated."
- **View (Figma 423:786 / 423:306):**
  - Card "New Damage Report", 899 px wide, with `x-ui.inline-field`-style boxes:
    - Disaster (select)
    - Barangay (select, prefilled from the chosen farmer)
    - Farmer / Beneficiary (Alpine autocomplete on `beneficiaries.lookup`, hidden `beneficiary_id`)
    - Crop / Farm Location (crop select + farm-location text input in one box)
    - Crop Stage
    - Total Area (ha)
    - Partial Area (ha)
    - Below them: "GPS Coordinates" with Latitude and Longitude, plus a small "Use my location" link (navigator.geolocation).
    - "Photo / Attachment" drop zone: "Drag & drop photos here (JPG/PNG)" / "or click to browse", and the "+ Choose Photo" pill. It shows thumbnails of the chosen files, which can be removed before submitting.
    - Full-width gradient "Submit Damage Report". It is disabled on submit.
  - Beside the card (not in Figma, right column): a live "Estimated Loss" box that shows loss and cost as the areas and crop change, using the same formula in JS from `data-` attributes on the crop options.

**Steps**
- [ ] **Step 1: Failing tests:**
  - `it('shows the Figma form')`: the fields' labels, "Drag &amp; drop photos here (JPG/PNG)", "Choose Photo" and "Submit Damage Report", for each of the 3 roles.
  - `it('files a report with photos and computes loss')`: `Storage::fake('public')`, 2 `UploadedFile::fake()->image('a.jpg')`.
    - Redirects to `damage.show`; status `for_validation`; loss 5.4 and cost 108000 for Rice 1.20/0.30.
    - 2 photos stored under `damage/{id}/`; audit "Filed Damage Report".
  - `it('rejects bad input with field errors')`: one dataset per Global Constraints form-rule message, including "1,5" area, area 0/0, latitude without longitude, an 8 MB photo, a `.pdf` named `.jpg`, and 11 photos.
    - Asserts `assertSessionHasErrors` on the field, `old()` kept, and `Storage::disk('public')->allFiles()` empty.
  - `it('refuses a second report for the same farmer, crop and disaster')`.
  - `it('lets the reporter edit an unvalidated report and recalculates')`.
  - `it('keeps validated reports read-only')`: 403 on edit.
  - `it('serves photos only to signed-in users with damage.view')`.
  - `it('lets the farmer search work for damage reporters')`: the AT gets 200 from `beneficiaries.lookup`.
  - `it('turns a request too large for PHP into the photo message')`: a POST with `CONTENT_LENGTH` 1 GB to `/damage-reports` redirects to `damage.create?too_large=1`, which shows "The photos are too large to upload at once — up to 10 photos of 5 MB each." Extend the `PostTooLargeException` handler in `bootstrap/app.php`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file, then the full suite, then `npm run build`. Expected: PASS.
- [ ] **Step 5: Commit** `feat: file and edit damage reports with photos and computed loss`.

---

### Task 3: Damage report list with filters, cards and photo viewer

**Files:**
- `DamageReportController@index`.
- `resources/views/damage/index.blade.php`.
- Test: `tests/Feature/DamageListTest.php`.

**Interfaces:**
- **Route:** `GET /damage-reports` → `damage.index` (`damage.view`).
- **Query:** the filters from Global Constraints, applied once, so the cards and table agree.
  - Cards are aggregated in SQL, not with a PHP loop over rows.
  - Table rows eager-load `beneficiary`, `barangay`, `crop` and `photos_count`.
- **View (Figma 329:2978):**
  - Card "Crisis / Crop Damage Report" with:
    - 3 filter selects (Disaster 280, Barangay 240, Status 220; auto-submit on change);
    - "+ New Damage Report" (`bg-brand-soft`, 260×44) when the user has `damage.create`;
    - 4 stat cards (`#f7f7f9`, 108 tall, dots in stat colors 1, 2, 4, 3 as in Figma);
    - "Reported Damage Records", the table, and the footer buttons.
  - Each row links to `damage.show`.
  - "(view)" opens an Alpine modal gallery (`x-ui.modal`) with the photos (served by `damage.photos.show`) and prev/next controls.

**Steps**
- [ ] **Step 1: Failing tests:**
  - `it('shows the Figma list for each role')`:
    - the title "AGRICULTURAL DAMAGE REPORT", "Crisis / Crop Damage Report";
    - "Disaster: Typhoon Cristina", "Barangay: All", "Status: All";
    - the 4 card labels in order, "Reported Damage Records" and the 9 column heads in order;
    - "+ New Damage Report" for all 3 roles;
    - "Export to Excel" only for Admin; "Generate PDF Report" for all.
  - `it('summarises the filtered reports')`: computed totals for the seeded Typhoon Cristina rows appear in the cards with the formats.
  - `it('filters by disaster, barangay and status')`.
  - `it('shows archived reports only to configurers')`: the `status=archived` option and its results are visible to Admin; for the AT the option is absent and the parameter is ignored.
  - `it('shows zeros for a disaster without reports')`: Southwest Monsoon Flooding → "0" ×4 and "No damage reports for this filter yet."
  - `it('formats a row like Figma')`: "1.20 ha / 0.30 ha", "5.4 MT", "₱108,000", "2 (view)", "For Validation".
  - `it('counts reports in the agri tech sidebar')`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file, the full suite and the build. Expected: PASS.
- [ ] **Step 5: Commit** `feat: list damage reports with filters, summary cards and photo viewer`.

---

### Task 4: Detail page, validation with adjustment, archive and restore

**Files:**
- `DamageReportController@show`.
- `DamageReportActionController` (`validate`, `archive`, `restore`).
- `DamageReportService` (`validate`, `archive`, `restore`).
- `resources/views/damage/show.blade.php`.
- Test: `tests/Feature/DamageValidationTest.php`.

**Interfaces:**
- **Routes:**

  | Method and path | Name | Permission |
  |---|---|---|
  | `GET /damage-reports/{report}` | `damage.show` | `damage.view`; archived reports only with `damage.configure` |
  | `POST /damage-reports/{report}/validate` | `damage.validate` | `damage.validate` |
  | `POST /damage-reports/{report}/archive` | `damage.archive` | `damage.configure` |
  | `POST /damage-reports/{report}/restore` | `damage.restore` | `damage.configure`, `withTrashed()` |

- **`validate(DamageReport $report, ?string $lossMt, ?string $cost, ?string $note, User $actor): DamageReport`**
  - Locks the row and refuses non-`for_validation` reports: "This report is already validated."
  - Blank loss or cost means the computed values are kept.
  - If either value differs from the computed one, the note is required: "Explain why the figures were adjusted."
  - Sets `validated_by` and `validated_at`.
  - Audit "Validated Damage Report", with the old and new loss/cost.
- **`archive(DamageReport $report, string $reason, User $actor): void`**: the reason is required (max 255). Audit "Archived Damage Report".
- **`restore(DamageReport $report, User $actor): DamageReport`**: refuses if the restore would break the duplicate rule. Audit "Restored Damage Report".
- **View** (no Figma frame; the card style of 423:786):
  - All fields, a GPS link (`https://www.openstreetmap.org/?mlat=..&mlon=..#map=17/..`, `target=_blank`, `rel=noopener`), the photo grid, reporter/validator and dates, and the snapshot values ("Rice · 4 MT/ha · ₱20,000/MT · partial ×0.5").
  - Buttons: Edit (when allowed), the validate form (loss, cost, note, "Validate Report"), and Archive with reason / Restore.

**Steps**
- [ ] **Step 1: Failing tests:**
  - `it('shows the report with photos and computed figures')`.
  - `it('validates a report as filed')`.
  - `it('validates with adjusted figures and a note')`, plus a missing-note error.
  - `it('validates only once')`: the second call errors, and there is exactly 1 "Validated Damage Report" audit row.
  - `it('forbids encoders from validating')`.
  - `it('archives with a reason and restores')`.
  - `it('keeps filed figures when crop values change')`: update Rice's price, then the report's loss/cost are unchanged and validation still shows the snapshot.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file, the full suite and the build. Expected: PASS.
- [ ] **Step 5: Commit** `feat: validate, adjust, archive and restore damage reports`.

---

### Task 5: Disasters and crop reference values (Admin)

**Files:**
- `DamageReferenceController` (`updateCrops`, `storeDisaster`).
- A modal in `damage/index.blade.php`.
- Test: `tests/Feature/DamageReferenceTest.php`.

**Interfaces:**
- **Routes** (`damage.configure`):
  - `PUT /damage-reference/crops` → `damage.crops.update`. The body is `crops[{id}][yield_mt_per_ha|price_per_mt|partial_loss_factor]` plus `new_crop[name|yield_mt_per_ha|price_per_mt|partial_loss_factor]`, all optional.
  - `POST /damage-reference/disasters` → `damage.disasters.store`, with name and occurred_on (not in the future).
- **Validation:**
  - yield: 0.01–999.99;
  - price: 0–9,999,999.99;
  - factor: 0–1;
  - names: unique and trimmed.
- **Audit:** "Updated Crop Reference Values" (only changed crops, old/new) and "Added Disaster".
- **View:**
  - On the list page, users with `damage.configure` see a "Disasters & Crop Values" outlined pill under the card.
  - The modal has two sections:
    - a table of crops with editable yield, price and factor, plus an add-crop row;
    - "Add Disaster" (name, date).
  - It shows the formula line "Loss = (Total + factor × Partial) × Yield · Cost = Loss × Price".

**Steps**
- [ ] **Step 1: Failing tests:**
  - `it('updates crop values and audits only the changes')`.
  - `it('applies new values to new reports only')`.
  - `it('adds a disaster that the form then offers')`.
  - `it('rejects invalid values')`: a dataset covering a factor of 1.5, a negative price and a duplicate name.
  - `it('is for administrators only')`: AT and DE get 403, and the pill is hidden.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file, the full suite and the build. Expected: PASS.
- [ ] **Step 5: Commit** `feat: let administrators manage disasters and crop reference values`.

---

### Task 6: Export to Excel and Generate PDF Report

**Files:**
- `DamageExportController` (`excel`, `pdf`).
- `DamageReportExport`.
- `resources/views/damage/pdf.blade.php`.
- Test: `tests/Feature/DamageExportTest.php`.

**Interfaces:**
- **Routes:**
  - `GET /damage-reports/export` → `damage.export` (`export.run`).
  - `GET /damage-reports/pdf` → `damage.pdf` (`damage.view`).
  - Both take the list filters and register before `/damage-reports/{report}`.
- **File names:**
  - `agapay-damage-{disaster-slug|all}-YYYY-MM-DD.xlsx`;
  - `agapay-damage-{disaster-slug|all}-YYYY-MM-DD.pdf`.
- **`DamageReportExport` (FromQuery, WithHeadings, WithMapping, WithCustomValueBinder, ShouldAutoSize):**
  - Headings, in order: Disaster, Farmer, RSBSA No., Barangay, Crop, Farm Location, Crop Stage, Total Area (ha), Partial Area (ha), Loss (MT), Cost (₱), Latitude, Longitude, Photos, Status, Reported By, Date Reported, Validated By, Validated On.
  - The binder writes numbers (areas, loss, cost, lat, long, photos) as numeric cells and everything else as explicit text, so there are no formulas.
- **PDF (DomPDF, A4 landscape):**
  - Header "Office of the Municipal Agriculturist — Bontoc" / "Agricultural Damage Report".
  - The filter line (disaster with date, barangay, status).
  - The 4 summary figures, then the table (the same columns as the screen minus Photos), and a footer "Generated by {name} on {M j, Y g:i A} · AGAPAY".
  - The logo is embedded from `public/images/logo.svg`, converted to a data URI PNG at build time if DomPDF can't render the SVG. Otherwise the header is text only.
- **Audit:** "Exported Damage Reports" / "Generated Damage Report PDF", with `{filters, rows}`.

**Steps**
- [ ] **Step 1: Failing tests:**
  - `it('downloads the filtered reports as xlsx')`: `Excel::fake()` and `assertDownloaded` with the headings and the first row mapped.
  - `it('writes text cells as text and figures as numbers')`: real `Excel::raw`, then a name "=HYPERLINK(...)" is type `s` and loss is type `n`.
  - `it('generates the pdf with the summary and rows')`: the response has `content-type` application/pdf and a body starting with `%PDF`. Rendering the HTML view directly shows the summary and farmer names.
  - `it('generates empty files for an empty filter')`.
  - `it('limits excel to exporters')`: AT and DE get 403; all 3 roles get 200 on the PDF.
  - `it('audits both downloads')`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file, then the full suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat: export damage reports to excel and pdf`.

---

### Task 7: Figma fidelity

- [ ] Export `329:2978`, `407:1554`, `470:1863`, `423:786` and `423:306` with the Figma MCP (`maxDimension: 1820`) to `tests/visual/figma/`.
- [ ] Add these SCREENS:
  - `/damage-reports` as Admin_01, Agritech_02 and Encoder_03;
  - `/damage-reports/create` as Admin_01 and Agritech_02.
- [ ] Run `npm run visual`, review crops of the card regions, and fix structural mismatches. Expected: the harness passes; the remaining differences are the logo, sample data, font anti-aliasing, the added DE button and the Estimated Loss box.
- [ ] The full suite is green; run Pint; commit `test: add damage recording screens to the visual harness`.

---

## Self-Review Notes

- **Spec coverage:**
  - §4 tables → T1.
  - §5.11:
    - form fields (disaster, barangay, farmer, crop/farm location, stage, total/partial area, GPS, JPG/PNG photos) → T2;
    - loss/cost formula → T1;
    - "formula and values editable by Admin" → T5 (per-crop factor, yield, price);
    - "AT can adjust when validating" → T4;
    - summary cards, filters and status → T3;
    - Export to Excel and Generate PDF → T6.
  - §5.2 audit → T2–T6.
  - §5.3 recoverable deletes → T4.
  - §5.13 AT "Reports Filed This Month" → T1/T3.
  - §6 steps 38–40 → T1–T3, T6; Figma frames → T7.
- **Rulings taken while planning:**
  - **The editable "formula" is the partial-damage weight, stored per crop** (default 0.5). Yield and price are per crop. Values are snapshotted on the report, so later edits never change filed figures.
  - **Disasters and crop values are managed by Admin** through a new `damage.configure` permission. The AT and DE pick from the existing disasters.
  - **Photos are kept on the public disk (spec) but served through an authenticated route.** Farm photos with GPS are personal data, and this also avoids `storage:link`.
  - **One report per farmer + crop + disaster;** a second crop is a second report.
  - **The DE gets "+ New Damage Report"** (the spec says so). "Export to Excel" stays Admin-only as in Figma, via `export.run`.
  - **AT "Reports Filed This Month" counts damage reports.** The AT files disaster reports, which is their "File Disaster Report" action.
  - **The detail page, archive/restore and the reference-values modal have no Figma frames.** They reuse the 423:786 card style below or beside the mirrored region (spec §2).
