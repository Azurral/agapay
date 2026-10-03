# AGAPAY Phase 6 (Excel Import and Export) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** OMAG staff can upload the office's existing Excel/CSV masterlists — even with misspelled headers, shifted columns, title rows and corrupt cells — see exactly how every row will be read (Processing Feedback + preview), then Confirm & Import them in one transaction (optionally with DA/LGU intervention records), and the Administrator can export the privacy-limited beneficiary list (Name, RSBSA No., Address) as .xlsx — on screens matching Figma 470:540, 430:1887 and 329:3134.

**Architecture:** Upload → `ExcelImportService::stage()` reads the first sheet (`SpreadsheetReader`), finds the header row and maps columns (`ColumnMapper`: synonym dictionary + fuzzy match), normalises and checks each row (`RowNormalizer`: names, birthdate, barangay fuzzy match, RSBSA number, corrupt cells, age ≥ 18, duplicates, intervention), and stores the result as an `import_batches` row plus one `import_rows` row per data row. Nothing touches beneficiaries until `ExcelImportService::confirm()`, which replays the staged rows in one transaction through the existing models and Phase 4 services. Export is a read-only page plus a `maatwebsite/excel` download.

**Tech Stack:** Laravel 13 / PHP 8.5, `maatwebsite/excel` 4 (PhpSpreadsheet) — already installed, MariaDB 10.4 (READ COMMITTED) / SQLite in-memory tests, Pest 4, Blade + Tailwind 4 + Alpine 3.

**Spec:** `docs/superpowers/specs/2026-09-28-agapay-design.md` (§3 `/import`, `/export`; §4 `import_batches`, `import_rows`; §5 rules 2, 8, 9, 10; §6a steps 34–37). Previous plans: Phase 3 (`Beneficiary`, `HouseholdService`, `BeneficiaryRules`, encoding queue), Phase 4 (`InterventionAssignment::assign`, `ClaimService::claim(..., historical: true)`, `InterventionRuleViolation`), Phase 5 (`InventoryService` stock deduction, `InsufficientStock`).

## Global Constraints

- Branch `feature/phase-6-import-export` from `master`; commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Permissions already exist: `import.run` (Admin, Data Encoder), `export.run` (Admin). Page titles "EXCEL IMPORT" and "EXPORT BENEFICIARY LIST". Quick actions "Upload Excel" → `import.index`, "Export List" → `export.index` (already in `config/agapay.php`).
- Accepted uploads: `.xlsx`, `.xls`, `.csv`; max 25 MB; max 20,000 data rows; first sheet only. Stored privately under `storage/app/private/imports/`. PHP must allow it (php.ini `upload_max_filesize = 25M`, `post_max_size = 30M`, `memory_limit = 512M` — the user edits php.ini); a file PHP itself rejects (`PostTooLargeException`, or an upload error such as `UPLOAD_ERR_INI_SIZE`) must still return to the upload card with "The file is larger than 25 MB." — never a blank 413 page.
- Header row: the first row within the first 15 rows that maps ≥ 2 columns; none → upload refused "Couldn't find a header row. Make sure one row has column names such as Name, Barangay and RSBSA No.".
- Fields and synonyms (matched after lower-casing and removing everything except letters/digits, e.g. "Brgy." → `brgy`): `first_name` first name, firstname, given name, fname; `middle_name` middle name, middlename, mname, mi, middle initial; `last_name` last name, lastname, surname, family name, lname; `full_name` name, full name, fullname, farmer name, farmer, beneficiary, beneficiary name; `birthdate` birthdate, birthday, birth date, date of birth, dob; `address` address, purok, sitio, street, house no; `barangay` barangay, brgy, bgy, barangay name; `contact_number` contact, contact no, contact number, mobile, mobile no, cellphone, cp no, phone; `farm_location` farm location, farm address, farm; `crop_type` crop, crop type, commodity; `rsbsa_number` rsbsa, rsbsa no, rsbsa number, rsbsa id, reference no; `intervention` intervention, assistance, program; `quantity` qty, quantity; `cycle` cycle, batch, batch cycle; `date_distributed` date distributed, date released, date given, distribution date. Fuzzy fallback: Levenshtein distance ≤ 2 to a synonym of length ≥ 5 (e.g. "Barangy" → barangay). Each column maps at most once (first wins).
- Field labels for feedback, by field: `first_name` First Name, `middle_name` Middle Name, `last_name` Last Name, `full_name` Name, `birthdate` Birthdate, `address` Address, `barangay` Barangay, `contact_number` Contact No., `farm_location` Farm Location, `crop_type` Crop Type, `rsbsa_number` RSBSA No., `intervention` Intervention, `quantity` Quantity, `cycle` Cycle, `date_distributed` Date Distributed — listed in the sheet's column order.
- Barangay fuzzy match: compare the cell (lower-cased, "barangay/brgy/bgy" and non-letters removed) with the 16 names normalised the same way; exact, else Levenshtein ≤ 2 → that barangay; else the row is unreadable "Unknown barangay 'X'".
- Row statuses: `ready` (new profile with RSBSA No.), `flagged` (new profile missing RSBSA No. → imported with `encoding_issue = "Missing RSBSA Number"`, `rsbsa_status = endorsed`, `source = excel_import`), `update` (an existing profile without an RSBSA No. matched by name + birthdate + barangay gets the row's number → `registered`), `duplicate` (already in AGAPAY or repeated in the file → skipped), `unreadable` (excluded, with its reasons). New profiles with an RSBSA No. are `registered`.
- Unreadable reasons (exact text, several may apply): "Corrupted cell in {Field}", "Missing name", "Missing birthdate", "Unreadable birthdate '{value}'", "Under 18 on {M j, Y}", "Missing barangay", "Unknown barangay '{value}'", "RSBSA No. {n} belongs to {Full Name}". Dedupe: RSBSA No. (trimmed, case-insensitive) or LOWER(first)+LOWER(last)+birthdate+barangay — against non-archived profiles and earlier rows of the same file.
- Values: names trimmed/space-collapsed; `full_name` split as "Last, First Middle" when it has a comma, else first word = first name, last word = last name, the rest middle; birthdate accepts Excel serial numbers, `Y-m-d`, `m/d/Y`, `d/m/Y` only when the day is > 12, `M j, Y`, `F j, Y`; contact numbers keep a leading 0 (a numeric `9171234567` becomes `09171234567`); an RSBSA No. read as a number keeps its digits; a blank address becomes "Barangay {name}".
- Optional intervention columns: `intervention` cell like "DA - Certified Rice Seeds", "LGU Complete Fertilizer" or a unique program name; `cycle` code (default current cycle); `quantity`; `date_distributed` → the record is a historical claim (stock deducted). Unknown program or cycle → the profile still imports, the row is marked with issue "Intervention skipped: unknown program 'X'" / "… unknown cycle 'X'".
- Processing Feedback lines (Figma 470:540; ✓ green `#a8f9b1`, ✗ pink `#f9a8a9`): "✓ {n} rows matched automatically ({mapped labels, comma-separated})", one "✓ Column shift auto-corrected: '{header}' -> mapped to '{Label}'" per header that was not already the canonical label, "✓ {n} existing profiles will receive their RSBSA No." (when > 0), "✕ {n} rows missing RSBSA No. — flagged for manual review", "✕ {n} duplicates skipped (already in AGAPAY or repeated in the file)", "✕ {n} rows unreadable (corrupted cells) — excluded, see log". Lines with a count of 0 are omitted except the first.
- Audit: "Uploaded Excel File" (record label = original file name, new values `{rows, ready, flagged, update, duplicate, unreadable}`), "Imported Excel File" (same counts as imported), "Discarded Excel Import", "Exported Beneficiary List" (`{rows}`). Profiles created by an import write their own "Added Beneficiary Profile" rows (Auditable).
- Export (spec rule 10): only Name, RSBSA No., Address; non-archived profiles ordered by last name, first name; file `agapay-beneficiaries-YYYY-MM-DD.xlsx`; headings exactly `Name`, `RSBSA No.`, `Address`; RSBSA shown as stored or "(pending)"; Address = `"{address}, {barangay}"` (or the address alone when it already names the barangay).
- Figma 470:540 layout: "Excel Upload" card (title + muted 14px subtitle "Scans incoming spreadsheets and will detect, align, and parse fields despite column shifts or label differences.", divider); dashed drop zone ≈1440×248, radius 15, lavender fill, 1.5px dashed `#b4b4b4`; centre text bold 14px "drag & drop .xlsx / .csv files here" + 12px "or click to browse"; gradient pill "+ Choose File" ≈262×39. "Processing Feedback" card: `bg-brand-card` gradient, white title with icon, inner rounded box (white/15 overlay) of 13px lines. Full-width "+ Confirm & Import" pill 59 tall: gradient-outlined and faded while disabled, `bg-brand-bar gradient-button` when a staged batch is ready.
- Figma 329:3134 layout: card "Export Beneficiary List to OMAG / DA", muted subtitle "Only Name, RSBSA No., and Address are included in this export.", column heads Name / RSBSA Number / Address in `#7e80ff` at 23 / 252 / 447 (card coords ×1.3), rows bold 14px, then outlined pill "+ Export as .xlsx" ≈262×39 under the card.

## Review Focus

1. **Real office files** — a title row and blank columns above/left of the table, merged-looking empty rows inside the data, headers like "Brgy.", "RSBSA #", "Date of Birth" — must still be detected and mapped (Tasks 1, 2).
2. **Numbers Excel mangles** — RSBSA numbers and contact numbers stored as numbers (lost leading 0), birthdates as serial numbers, `#REF!`/`#VALUE!` error cells — read correctly or excluded with the reason, never a 500 (Tasks 1, 2).
3. **Duplicates** — the same person twice in one file, or already in AGAPAY with different letter case/spaces, or an RSBSA No. that belongs to someone else — one profile, the rest reported, never a unique-constraint 500 (Task 2).
4. **Confirming twice** — a double-clicked "Confirm & Import" or the browser's back button must import the batch exactly once (the batch row is locked and its status checked inside the transaction) (Task 4).
5. **Bad uploads** — an empty file, a renamed PDF, a sheet with only headers, more than 20,000 rows, a file over 25 MB (including one PHP rejects before Laravel sees it) — must show a friendly error on the upload card (Task 3).

---

## File Structure

```
app/Models/ImportBatch.php, ImportRow.php
app/Imports/SpreadsheetReader.php          rows of strings from xlsx/xls/csv; error cells marked
app/Imports/ColumnMapper.php               header detection + synonym/fuzzy mapping
app/Imports/RowNormalizer.php              one raw row → normalised data + status + issues
app/Services/ExcelImportService.php        stage(), confirm(), discard()
app/Exports/BeneficiaryListExport.php      FromQuery + WithHeadings + WithMapping
app/Http/Controllers/ImportController.php  index, store, confirm, discard
app/Http/Controllers/ExportController.php  index, download
database/migrations/2026_10_04_00001{0,1}_create_import_{batches,rows}_table.php
resources/views/import/index.blade.php, export/index.blade.php
tests/Pest.php (spreadsheet() fixture helper)
tests/Feature/{ImportReading,ImportStaging,ImportPage,ImportConfirm,Export}Test.php
tests/visual/screens.spec.js, tests/visual/figma/{470-540,430-1887,329-3134}.png
```

---

### Task 1: Reading sheets and mapping columns

**Files:** Create `SpreadsheetReader`, `ColumnMapper`, migrations + `ImportBatch` / `ImportRow` models; add `spreadsheet(array $rows, string $type = 'xlsx'): UploadedFile` to `tests/Pest.php` (writes a temp workbook with PhpSpreadsheet; a cell given as `['error' => '#REF!']` is written as an error value). Test: `tests/Feature/ImportReadingTest.php`.

**Interfaces:**
- `import_batches`: id, user_id FK, original_name, stored_path, status (`staged`/`imported`/`discarded`), header_row int, mapping json, feedback json, counts json, imported_at nullable, timestamps. `import_rows`: id, import_batch_id FK (cascade), row_number int, status string(12), data json, issues json, beneficiary_id nullable FK (null on delete), timestamps; index (import_batch_id, status).
- `ImportBatch` (relations `user`, `rows`; casts; `auditRecordLabel()` = original_name; consts `STAGED`, `IMPORTED`, `DISCARDED`); `ImportRow` (consts `READY`, `FLAGGED`, `UPDATE`, `DUPLICATE`, `UNREADABLE`; `statusLabel()` Ready / Missing RSBSA / Adds RSBSA No. / Duplicate / Excluded; `statusTone()` ok for ready/update, bad otherwise).
- `SpreadsheetReader::read(string $path, string $extension): array` → `list<list<string|null>>` of the first sheet, cell values as trimmed strings (numbers without trailing `.0`, dates left as Excel serials), error cells as the sentinel `"\0ERROR:#REF!"`; throws `ImportFileException` ("This file couldn't be read as a spreadsheet.") for unreadable files.
- `ColumnMapper::map(array $rows): MappedHeader` (readonly: `int $headerIndex`, `array<int,string> $columns` col index → field, `array<int,array{header:string,field:string}> $corrections`) — throws `ImportFileException` with the Global Constraints "Couldn't find a header row…" message.
- [ ] **Step 1: Failing tests** — `it('reads xlsx and csv rows as strings')`; `it('keeps numeric RSBSA and contact digits')` (`171234567890` → "171234567890"); `it('marks error cells')`; `it('finds the header below title rows and after blank columns')` (row 3, starting at column C); `it('maps synonyms and fuzzy headers')` (dataset "Brgy." → barangay, "RSBSA #" → rsbsa_number, "Date of Birth" → birthdate, "Barangy" → barangay, "Farmer Name" → full_name; "Notes" unmapped); `it('reports header corrections')` ("Brgy" → corrections `['header' => 'Brgy', 'field' => 'barangay']`, "Barangay" → none); `it('refuses a sheet without a header row')`; `it('refuses a file that is not a spreadsheet')`.
- [ ] **Step 2: Run** `./vendor/bin/pest tests/Feature/ImportReadingTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement** (use `PhpOffice\PhpSpreadsheet\IOFactory` through maatwebsite's dependency; `getCalculatedValue()` guarded — formula errors become error sentinels).
- [ ] **Step 4: Run** file then full suite — Expected: PASS.
- [ ] **Step 5: Commit** `feat: read spreadsheets and map columns by synonym and fuzzy match`.

---

### Task 2: Normalising rows and staging a batch

**Files:** Create `RowNormalizer`, `ExcelImportService` (`stage`), `ImportFileException`. Test: `tests/Feature/ImportStagingTest.php`.

**Interfaces:**
- `RowNormalizer::normalize(array $cells, MappedHeader $header, int $rowNumber): array{status:string, data:array, issues:list<string>}` — applies every value rule and unreadable reason in Global Constraints (dedupe is done by the service, which sees the whole file).
- `ExcelImportService::stage(UploadedFile $file, User $actor): ImportBatch` — validates size/type/row limits (`ImportFileException` messages: "Upload an .xlsx, .xls or .csv file.", "The file is larger than 25 MB.", "The sheet has no data rows under its header.", "The sheet has more than 20,000 rows. Split it into smaller files."), reads + maps, normalises each non-empty row, dedupes (Global Constraints), resolves the optional intervention, stores the file, the batch (`staged`, mapping, counts, feedback lines as `list<array{ok:bool,text:string}>`) and rows in one transaction; audit "Uploaded Excel File".
- [ ] **Step 1: Failing tests** — `it('stages a clean masterlist')` (columns Name / Birthdate / Barangay / RSBSA No.; 3 rows → 2 ready + 1 flagged; feedback first line "✓ 3 rows matched automatically (Name, Birthdate, Barangay, RSBSA No.)" without the ✓ glyph in `text`); `it('reports column shift corrections')` ("Brgy" header → "Column shift auto-corrected: 'Brgy' -> mapped to 'Barangay'"); `it('splits full names')` ("Dela Cruz, Juan Abenoja" and "Juan Abenoja Dela Cruz"); `it('reads birthdates in office formats')` (dataset: serial 30000, 1982-02-17, 02/17/1982, 17/02/1982, Feb 17, 1982; "31/31/1990" → unreadable "Unreadable birthdate '31/31/1990'"); `it('fuzzy-matches barangays')` ("Bontoc-Ili", "brgy. samoki", "Guinaang" → Guina-ang; "Atlantis" → unknown); `it('excludes corrupted, nameless and under-age rows with reasons')`; `it('dedupes against AGAPAY and within the file')` (existing Juan with different case → duplicate; same row twice → second duplicate; RSBSA No. of another person → unreadable "RSBSA No. RSBSA-0198 belongs to Maria Santos"); `it('plans RSBSA updates for existing profiles without a number')` (Federico seeded without number, row with RSBSA-0777 → update); `it('resolves optional intervention columns')` ("DA - Certified Rice Seeds", unknown program → issue, profile still ready); `it('refuses bad files')` (dataset per message); `it('fixes leading zeros of contact numbers')`.
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** file then full suite — Expected: PASS.
- [ ] **Step 5: Commit** `feat: normalise, dedupe and stage spreadsheet rows`.

---

### Task 3: Upload page with Processing Feedback and preview

**Files:** Create `ImportController` (`index`, `store`, `discard`), `resources/views/import/index.blade.php`; modify `routes/web.php`. Test: `tests/Feature/ImportPageTest.php`.

**Interfaces:**
- Routes (`can:import.run`): `GET /import` → `import.index` (shows the user's latest `staged` batch, or `?batch={id}` of their own); `POST /import` → `import.store` (field `file`; `ImportFileException` → error on `file`); `POST /import/{batch}/discard` → `import.discard` (own batch, `staged` only; audit "Discarded Excel Import").
- View: Figma 470:540 cards (Global Constraints); the drop zone is a `<label>` around a hidden file input that auto-submits on change, with Alpine drag-and-drop (`dragover` highlight, `drop` puts the file into the input and submits); upload errors show inside the card in `text-danger`. Processing Feedback shows "Upload a spreadsheet to see how its rows will be read." when there is no staged batch. Below the Figma region: a "Preview" card (file name, counts) listing up to 200 rows — Row #, Name, Birthdate, Barangay, RSBSA No., status chip, issues — plus "Discard" (outlined pill). "+ Confirm & Import" is disabled (faded) without a staged batch with importable rows.
- [ ] **Step 1: Failing tests** — `it('shows the Figma upload screen')` (title, subtitle, drop text, "+ Choose File", "Processing Feedback", disabled "+ Confirm & Import"); `it('uploads a file and shows feedback and the preview')`; `it('shows the upload error on the card')` (renamed PDF); `it('turns a request too large for PHP into the size message')` (throw `PostTooLargeException` → redirect to `import.index` with the size error; register the handler in `bootstrap/app.php` for the `import.store` route); `it('discards a staged batch')`; `it('keeps batches private to their uploader')` (another encoder's `?batch=` → 404); `it('forbids agri techs')`.
- [ ] **Step 2: Run** — FAIL. **Step 3:** implement. **Step 4:** file + full suite; `npm run build` — PASS. **Step 5:** commit `feat: add excel upload with processing feedback and preview`.

---

### Task 4: Confirm & Import

**Files:** Modify `ExcelImportService` (`confirm`), `ImportController` (`confirm`), `routes/web.php`. Test: `tests/Feature/ImportConfirmTest.php`.

**Interfaces:**
- Route `POST /import/{batch}/confirm` → `import.confirm` (`can:import.run`, own batch).
- `ExcelImportService::confirm(ImportBatch $batch, User $actor): array{created:int, updated:int, records:int}` — one `DB::transaction`: lock the batch row; refuse unless `staged` ("This import was already confirmed." / "This import was discarded."); for `ready`/`flagged` rows create profiles (Global Constraints statuses, `source = excel_import`, `created_by`), for `update` rows set the RSBSA No. and `registered` (clearing "Missing RSBSA Number"); skip `duplicate`/`unreadable`; create intervention records through `InterventionAssignment::assign` and, with a date, `ClaimService::claim(..., historical: true)` — any `InterventionRuleViolation` (incl. `InsufficientStock`) aborts the whole import with "Row {n}: {message}" and the batch stays `staged`; set `beneficiary_id` on rows, batch `imported` + `imported_at`; audit "Imported Excel File". Success: redirect to `import.index` with status "Imported {created} new profiles, updated {updated}, added {records} intervention records."
- [ ] **Step 1: Failing tests** — `it('imports ready and flagged rows and updates RSBSA numbers')` (flagged row lands in the DE Pending Encoding Queue with "Missing RSBSA Number"; update row → Federico registered); `it('creates intervention records and deducts stock for distributed rows')`; `it('rolls everything back when a row breaks a distribution rule')` (stock short → nothing created, message "Row 4: Not enough stock: …", batch still staged); `it('imports a batch only once')` (second confirm → error, profile count unchanged); `it('groups imported people into households')`; `it('writes the import to the audit trail')`.
- [ ] **Step 2–5:** RED, implement, GREEN + full suite, commit `feat: confirm and import staged spreadsheet rows in one transaction`.

---

### Task 5: Export beneficiary list

**Files:** Create `ExportController`, `BeneficiaryListExport`, `resources/views/export/index.blade.php`; modify `routes/web.php`. Test: `tests/Feature/ExportTest.php`.

**Interfaces:**
- Routes (`can:export.run`): `GET /export` → `export.index` (preview, paginated 15, Figma 329:3134); `GET /export/download` → `export.download` (xlsx stream; audit "Exported Beneficiary List").
- `BeneficiaryListExport` implements `FromQuery`, `WithHeadings`, `WithMapping` with the Global Constraints columns.
- [ ] **Step 1: Failing tests** — `it('previews only name, RSBSA number and address')` (`assertSeeInOrder(['Name','RSBSA Number','Address','Ana Dela Cruz','RSBSA-0233','Purok 3, Poblacion'])`, `assertDontSee` birthdate/contact values); `it('downloads the xlsx with the privacy columns')` (`Excel::fake()`; `Excel::assertDownloaded('agapay-beneficiaries-'.today()->format('Y-m-d').'.xlsx', fn (BeneficiaryListExport $e) => $e->headings() === ['Name','RSBSA No.','Address'] && …)`); `it('leaves archived profiles out')`; `it('audits the export')`; `it('is for the administrator only')` (encoder 403).
- [ ] **Step 2–5:** RED, implement, GREEN + full suite + build, commit `feat: export the privacy-limited beneficiary list as xlsx`.

---

### Task 6: Figma fidelity

- [ ] Export `470:540`, `430:1887`, `329:3134` with the Figma MCP (`maxDimension: 1820`) to `tests/visual/figma/`; add SCREENS `/import` (Admin_01, Encoder_03) and `/export` (Admin_01); `npm run visual`; review crops of the cards; fix structural mismatches. Expected: harness passes; remaining diff = logo, sample data, font anti-aliasing.
- [ ] Full suite green; Pint; commit `test: add import and export screens to the visual harness`.

---

## Self-Review Notes

- **Spec coverage:** §4 import tables → T1; §5.9 header detection, synonym + fuzzy mapping, barangay fuzzy, dedupe, missing-RSBSA flag to the encoding queue, unreadable rows excluded and logged, preview with Processing Feedback, Confirm & Import, optional intervention column → T1–T4; §5.8 "registered once the RSBSA number is entered … by import" → T2 `update` rows + T4; §5.10 export columns, .xlsx → T5; §5.2 audit of import/export → T2–T5; §6a 34–37 → T1–T6.
- **Rulings taken while planning:**
  - Rows missing an RSBSA No. import as `endorsed` profiles with "Missing RSBSA Number" (like the seeded Federico Wasing), so they reach the encoder's queue and get registered once the number is entered.
  - A distribution-rule break on any row rolls back the whole import (the spec asks for one transaction); per-row parsing problems never do — they are excluded at staging.
  - Imported intervention rows with a date are historical claims and deduct stock, like encoder-entered past distributions.
  - The export Address column shows the street/purok and barangay (Figma shows only the barangay, but a usable list needs the address the spec allows).
  - The preview (not in Figma) sits below the mirrored region, as spec §2 requires.
