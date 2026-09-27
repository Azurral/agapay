# AGAPAY — Build Plan (Design Spec + Implementation Outline)

## Context

AGAPAY is the capstone system from *"Agapay: A Centralized Agricultural Beneficiary Information and Intervention Management System for OMAG Bontoc"* (King's College of the Philippines, July 2026). OMAG serves 16 barangays and 5,000+ beneficiaries and currently works across about 10 separate Excel workbooks; looking up one farmer takes 5–10 minutes. The paper defines a **web-based** system (§1.4.1) with **3 roles**: Administrator (Municipal Agriculturist), Agricultural Technologist, and Data Encoder.

The UI is already designed in Figma ("Agapay - Wireframes", file key `ZYDqjMYN1h4OBNbQUpadrk`, page `0:1`): **46 desktop frames at 1820×1024**, split into Login (1), Administrator (21), Agri Tech (11), and Data Encoder (13). Goal: a **fully working** system that does what the paper says, with every Figma screen reproduced as closely as possible in code.

**Confirmed decisions (from the user):**
- Stack: **Laravel + MySQL (XAMPP MariaDB) + Blade + Tailwind CSS + Alpine.js**
- Account switcher: dropdown lists accounts that have signed in on this device → clicking one opens login with that **username prefilled; password is still required**
- Conflicts: **the paper decides behavior, Figma decides the look.** Where the paper needs something Figma lacks, reuse the same Figma components (e.g., add "+ New Damage Report" to the Data Encoder screen).

**Process note:** In plan mode only this file can be edited. So this file serves as the brainstorming design spec. After approval, Step 0 copies it to `docs/superpowers/specs/2026-09-28-agapay-design.md` (committed), then `superpowers:writing-plans` expands the phases below into bite-sized tasks.

---

## 1. Stack & environment

| Layer | Choice |
|---|---|
| Backend | Laravel (latest stable via `composer create-project laravel/laravel`), PHP 8.5 at `X:\Program Files\php-8.5.0` |
| DB | MariaDB 10.4.32 from `X:\xampp\mysql`, database `agapay`, utf8mb4 |
| Frontend | Blade components + Tailwind CSS v4 (Vite) + Alpine.js; **IBM Plex Sans** self-hosted via `@fontsource/ibm-plex-sans` (works offline on the office LAN) |
| Excel | `maatwebsite/excel` (PhpSpreadsheet): import (ETL) and .xlsx export |
| PDF | `barryvdh/laravel-dompdf`: damage report PDF, distribution report PDF |
| Tests | Pest (feature tests, SQLite in-memory) + Playwright (E2E and visual comparison against Figma PNGs using ImageMagick `magick compare`, already installed) |

**Environment fix needed (will ask before touching):** the PHP 8.5 CLI `php.ini` (`X:\Program Files\php-8.5.0\php.ini`) has `pdo_mysql`, `zip`, `gd`, `fileinfo`, `intl`, `pdo_sqlite`, and `sqlite3` disabled. The DLLs exist in `ext\` and just need uncommenting. The project folder `X:\Documents\SchoolProjects\AGAPAY` is empty and not a git repo, so run `git init`.

---

## 2. Design fidelity system (Figma → code)

**Tokens (extracted via `get_design_context` on frame `310:2`), to go into `resources/css/app.css` `@theme`:**
- Font `IBM Plex Sans` 400/500/700; ink `#2f2f2f`; muted header text `#b4b4b4`; subtitle `#b5b5b5`
- Brand radial gradient `#5a5de3 → #7876ea (25%) → #9690f1 (50%) → #d2c4ff`: search bar, primary buttons, the "Return to login" card, the Processing Feedback card
- Heading gradient text `#00fff2 → #7e80ff`: "Select an action to get started:"
- Pill buttons: white, 1.5px cyan→purple border, radius 50, 176×39, bold 20px
- Status chips 155×39, radius 10, 4px border: green `#a8f9b1` (Claimed / Validated / Active / Restore), red `#f9a8a9` (Unclaimed / Validate / Inactive / For Validation)
- Stat colors: `#da37ff`, `#8037ff`, `#4671ff`, `#0bcaff`; 11px rounded-3 dots
- Content area `#f9f6ff` with the Figma grid SVG background, 1.5px `rgba(0,0,0,.15)` border, top-left radius 20; cards white, 1.5px `rgba(0,0,0,.1)` border, radius 20
- Header 70px white with `0 4px 4px rgba(0,0,0,.05)`; logo = Figma's placeholder square (`#edefea`, 3px `#5a5de3` border) until a real logo is supplied
- Sidebar 259px; nav items 235×51, radius 15, bold 16px, 24px icons (material-symbols, mingcute, icon-park, bxs, ep)

**Method (per screen):** load `figma-design-to-code` → `get_design_context` on the frame → translate Figma's absolute positioning into flex/grid with the **exact** px sizes, spacing, and typography, so tables grow with real data → download every icon, avatar, and grid-background asset into `public/images/figma/` (no expiring Figma URLs left in code).

**Shared Blade component library** (`resources/views/components/`): `layout/app`, `sidebar` (role-driven nav, stat block, account dropdown, return-to-login card), `header` (live date "D, F j" + page title), `quick-actions`, `search-bar` (+ filter popover), `card`, `table`, `status-chip`, `pill-button`, `gradient-button`, `field`, `select`, `dropzone`, `modal`, `segmented-toggle` (Beneficiaries | Archived/Restore), `stat-card`.

**Rules for functions that have no Figma frame** (Add User, Configure Roles, Edit record, photo viewer, pending RSBSA list, etc.): build them as modals or cards below the mirrored region, using only the components above, and never change the Figma-mirrored layout.

**Layout target:** pixel match at 1820×1024 with the Figma sample data seeded; fluid content area, usable from 1280px wide. Desktop only, matching the paper's office-PC setting.

---

## 3. Screen → route → role map

| Route | Figma frames (Admin / AgriTech / Encoder) | Notes |
|---|---|---|
| `/login` | 482:339 | username + password, gradient LOGIN |
| `/dashboard` | 310:2, 310:844 (dropdown open) / 237:1470, 313:1395 / 237:1659, 313:1162 | Admin & AT: "All Beneficiaries" table; DE: "Pending Encoding Queue" |
| `/users` | 329:695 | search, role filter, status; Add User and **Configure Roles** (permission matrix) as modals |
| `/audit-trail` | 329:904 | filter by timestamp, user, action; read-only |
| `/interventions` | 329:2475 / 407:2 | DA (National) vs LGU (Municipal) cards |
| `/interventions/da` (+`?intervention=`) | 329:1250, 400:4 / 407:323, 407:463 | Admin: chips + **Archive**; AT: Validation & Status **dropdowns** |
| `/interventions/da/archived` | 344:236 | reason, deleted on/by, **Restore** |
| `/interventions/lgu` (+filters) | 329:1423, 329:3407 / 407:603, 407:743 | extra "Registration" column/filter (Registered / New / Unregistered-Eligible) |
| `/interventions/lgu/archived` | 344:531, 344:817 | Restore |
| `/rsbsa/register` ("Newly Registered", "Add Beneficiary") | 329:1596 / — / 430:2029 | form; pending-applications card added below |
| `/beneficiaries` | — / — / 430:1276 | DE list with Edit |
| `/beneficiaries/{id}` | 329:2822 **Process Claim** / 407:1181 **Verify Eligibility** / 430:1461 **Edit Mode + Save** | household alert banner + intervention history |
| `/validation` (AT "Beneficiary Validation") | reuses table components | queue of records pending validation → profile |
| `/intervention-records` (+create) | — / — / 430:1603, 440:166, 446:198 | DE: claim dropdown, Edit, Add Record form |
| `/damage-reports` (+create) | 329:2978, 423:786 / 407:1554, 423:306 / 470:1863 (+ create button added per paper) | cards, table, photos, Excel/PDF |
| `/inventory` | 470:785, 470:986 (modal) / — / 430:1745, 446:3 | stock-in/out/balance, Record Movement modal, Recent Movements |
| `/import` | 470:540 / — / 430:1887 | drop zone → Processing Feedback → Confirm & Import |
| `/export` | 329:3134 | Name, RSBSA No., Address → .xlsx |
| `/reports` | 340:51 / 407:1694 / 470:2205 | program, format, date range, distribution cycle |

**Additions to Figma navigation:** the AT and DE sidebars get a "Reports" item in the same style, because their report screens exist in Figma but have no nav link. Quick actions route to the matching pages.

---

## 4. Data model (migrations)

- `roles`, `permissions`, `permission_role`: "Configure Roles" edits this matrix; `users.role_id` is nullable (shown as "No Role")
- `users`: username, name, password (bcrypt), role_id, status active/inactive, avatar, last_login_at; soft deletes
- `barangays`: 16 seeded (Alab Oriente … Tocucan)
- `households`: barangay_id, normalized address key, `household_no` (auto-grouped)
- `beneficiaries`: first/middle/last name, birthdate (age ≥ 18 validated), sex, address, barangay_id, contact, farm_location, farm_area_ha, crop_type, sector (farmer/fisherfolk/agri-youth/farmworker/livestock-poultry), `rsbsa_number` (nullable = "(pending)"), `rsbsa_status` (pending_validation/endorsed/registered/returned/rejected), `life_status` (active/inactive/deceased/relocated/ofw/bedridden), household_id, `encoding_issue` (e.g., "Missing RSBSA Number") + source (manual/excel_import); soft deletes with `delete_reason`, `deleted_by`
- `interventions`: source DA/LGU, name, unit, inventory_item_id (nullable for cash aid), `one_per_household`, `allow_repeat`, is_active. Seeded: Certified Rice Seeds, Organic Liquid Fertilizer, Complete Fertilizer, PAFF, RFFA, HDPE Pipes, Molasses, Agri Machinery, Emergency Seedlings, Municipal Cash Subsidy
- `distribution_cycles`: code "2026-Q3", label "2026-Q3 Dry Season", schedule date/venue, status scheduled/ongoing/completed
- `intervention_records`: beneficiary_id, intervention_id, cycle_id, quantity, `validation_status` (pending/eligible/ofw/bedridden/deceased/inactive/relocated/duplicate), `claim_status` (unclaimed/claimed), date_distributed, proxy_claimant + proof note (deceased case); soft deletes with reason
- `inventory_items` (name, unit, low_stock_threshold) and `inventory_movements` (in/out, qty, date, notes, `source` manual/auto, intervention_record_id, user_id); balance is computed
- `disasters`; `crops` (avg yield MT/ha, farmgate ₱/MT); `damage_reports` (disaster, beneficiary, barangay, crop, farm location, crop stage, total and partial damaged ha, loss MT, cost ₱, lat/long, status for_validation/validated, reported_by, validated_by); `damage_photos`
- `import_batches` + `import_rows` (staged preview, mapping JSON, per-row status/issues)
- `generated_reports` (history); `audit_logs` (user, action, model type/id, record label, old/new JSON, IP, timestamp; append-only)

---

## 5. Behavior (paper rules → implementation)

1. **Auth & RBAC:** login by username; inactive or no-role users are blocked; every route and action is checked by permission via a `can:` middleware and Gates seeded from the matrix; the sidebar and quick actions render from the role's permissions. Session timeout; passwords hashed (Data Privacy Act).
2. **Audit trail:** a model observer trait logs every create/update/delete/restore, plus login/logout, import, export, and report generation, using human action labels ("Updated Beneficiary Profile", "Uploaded Excel File") and record labels ("Juan Dela Cruz (RSBSA-0231)").
3. **Recoverable deletes:** Archive requires a reason (soft delete); the Archived/Restore tab lists reason, date, and user; Restore brings the record back.
4. **Distributions only for existing profiles:** intervention records require a beneficiary FK; the Add Record form searches existing beneficiaries only.
5. **Household rule:** households auto-group by normalized address and barangay. The profile banner shows other members and whether any has claimed. "Process Claim" blocks a second claim of the same intervention in the same cycle for `one_per_household` programs (with an admin override plus reason, logged).
6. **Validation:** AT sets eligibility (Verify Eligibility / dropdown). Deceased beneficiaries can only be claimed by proxy with a recorded proof note, and are flagged for removal in the next cycle. For LGU, a repeat of identical assistance is auto-flagged "Duplicate" unless `allow_repeat` is set.
7. **Inventory auto-update:** marking a record Claimed creates an AUTO stock-out movement ("−2 sacks · Certified Rice Seeds · Auto-deducted: distribution to …"); un-claiming reverses it; insufficient stock blocks the claim. Manual Stock In/Out goes through the modal.
8. **RSBSA workflow:** registration form → pending_validation (counted as "Pending RSBSA") → validated/endorsed to DA-RFO → registered once the DA-issued RSBSA number is entered (manually or by import). Returned/rejected records keep a reason.
9. **Excel import (ETL):** detect the header row; map columns by a synonym dictionary plus fuzzy match ('Brgy' → 'Barangay'); fuzzy-normalize barangay names to the 16; dedupe on RSBSA number or name + birthdate + barangay; rows missing an RSBSA number are imported but flagged (they go to the DE Pending Encoding Queue); unreadable rows are excluded and logged; show a preview with Processing Feedback, then **Confirm & Import**. An optional intervention column creates DA/LGU intervention records.
10. **Export list:** only Name, RSBSA No., and Address (privacy), as .xlsx.
11. **Damage recording:** form with disaster, barangay, farmer, crop/farm location, stage, total/partial area, GPS, photo upload (JPG/PNG). Loss and cost are computed from crop reference values (`loss = (total + 0.5 × partial) × yield`, `cost = loss × price`; formula and values editable by Admin, and AT can adjust when validating). Summary cards (farmers affected, area, loss MT, cost ₱), filters, status, Export to Excel, Generate PDF.
12. **Reports:** only for scheduled distribution cycles (paper §1.4.1). Filter by program (All/DA/LGU), date range, and cycle, in PDF or Excel: per-intervention assigned/claimed/unclaimed, quantity distributed, per-barangay breakdown, beneficiary list, inventory used.
13. **Dashboard stats:** Admin shows Total Beneficiaries, Pending RSBSA, Active Interventions, Active Users; AT shows Pending Validation, Active Interventions, Reports Filed This Month; DE shows Encoded This Month, Records to be Updated, Low Stock Items. All are live queries.
14. **Global search and Filter:** name, RSBSA number, or barangay → results table → profile.

---

## 6. Build phases

0. **Setup:** approve the php.ini extension change; `git init`; save the spec; create the Laravel project; `.env` for XAMPP MariaDB; Tailwind v4 + Alpine + IBM Plex; `writing-plans` produces the detailed task plan.
1. **Design system and shell:** tokens, Figma assets, Blade components, login, app layout for all 3 roles; set up the visual comparison harness first.
2. **Auth, roles/permissions, user management, audit trail.**
3. **Beneficiaries:** RSBSA registration, households, profiles (3 role variants), search/filter, DE list/edit, encoding queue.
4. **Interventions:** types page, DA/LGU lists, validation and claims, archive/restore, distribution cycles, DE intervention records.
5. **Inventory** with auto-deduction.
6. **Excel import (ETL) and export list.**
7. **Agricultural damage recording** with photos, Excel, and PDF.
8. **Reports and live dashboard stats.**
9. **Seeders** that mirror the Figma sample data exactly (Juan Dela Cruz RSBSA-0231, Typhoon Cristina records, inventory 120/86/34, etc.), accounts Admin_01 / Agritech_02 / Encoder_03 / Encoder_04 (inactive, no role); dev passwords live only in `DatabaseSeeder` and `README`. Then a final full verification pass.

---

## 6a. Step-by-step build sequence

Each step ends with its tests passing and a git commit. A step's screens are finished only when they match their Figma frames in the visual check.

**Phase 0: Setup (≈ 1 session)**
1. ✅ **Done by the user, verified with `php -m`:** `pdo_mysql`, `zip`, `gd`, `fileinfo`, `intl`, `pdo_sqlite`, and `sqlite3` are loaded. `mysqli` (line 930) is still commented out, which is fine because Laravel only uses `pdo_mysql`. Project root (main folder) = `X:\Documents\SchoolProjects\AGAPAY` (empty; Laravel is created directly in it).
2. Start XAMPP MySQL and create the `agapay` database (utf8mb4).
3. `git init`; `composer create-project laravel/laravel .`; set `.env` (DB, `APP_TIMEZONE=Asia/Manila`, `SESSION_LIFETIME=30`).
4. `composer require maatwebsite/excel barryvdh/laravel-dompdf`, `composer require --dev pestphp/pest pestphp/pest-plugin-laravel`; `npm i -D tailwindcss @tailwindcss/vite alpinejs @fontsource/ibm-plex-sans @playwright/test`.
5. Copy this spec to `docs/superpowers/specs/2026-09-28-agapay-design.md`; run `superpowers:writing-plans` for the detailed task file; commit.

**Phase 1: Design system and app shell**
6. Put the design tokens in `resources/css/app.css` (`@theme`) and load IBM Plex 400/500/700.
7. Download the Figma assets (nav icons, avatars, grid background, arrow) into `public/images/figma/`.
8. Build the Blade components: `x-layout.app`, `x-sidebar`, `x-header`, `x-quick-actions`, `x-search-bar`, `x-card`, `x-table`, `x-status-chip`, `x-pill-button`, `x-gradient-button`, `x-field`, `x-select`, `x-dropzone`, `x-modal`, `x-segmented-toggle`, `x-stat`.
9. Build `config/agapay.php` with the nav, quick-action, and stat definitions per role (mapped to permissions).
10. Build the login page (frame 482:339) and a static Admin dashboard shell (310:2).
11. Set up the visual harness: `tests/visual/` Playwright script → 1820×1024 screenshots → `magick compare` against the Figma PNGs in `tests/visual/figma/`. Check login and the dashboard shell.

**Phase 2: Auth, roles, users, audit**
12. Migrations and models for `roles`, `permissions`, `permission_role`, and `users` (username, status, role_id, avatar, soft deletes); the `RolePermissionSeeder`.
13. `LoginController`: authenticate by username, block inactive or no-role users, update last_login_at; the remembered-accounts dropdown (cookie list → login with username prefilled); logout; Return-to-login card.
14. Register a Gate for each permission; apply `can:` middleware on routes; sidebar, quick actions, and stats render by permission.
15. `audit_logs` table, an `Auditable` trait with an observer (create/update/delete/restore), and events for login, logout, import, export, and report. Build the `/audit-trail` page (329:904) with filters.
16. `/users` (329:695): search, role filter, status chips; Add/Edit User modal; Configure Roles modal (permission matrix checkboxes); activate/deactivate.
17. Tests: route access per role, login rules, audit entries.

**Phase 3: Beneficiaries and RSBSA**
18. Migrations: `barangays` (16 seeded), `households`, and `beneficiaries` (soft deletes, delete reason/by).
19. `HouseholdService`: normalize the address, then find or create the household and assign `household_no`.
20. `/rsbsa/register` (329:1596 / 430:2029): form validation (age ≥ 18, required fields), pending-applications card, status actions (validate → endorse → enter RSBSA No. → registered / returned / rejected with reason).
21. Global search and Filter popover → results table; the dashboard "All Beneficiaries" table (Admin/AT) and "Pending Encoding Queue" (DE).
22. `/beneficiaries` DE list (430:1276) and `/beneficiaries/{id}` profile with 3 role variants (329:2822, 407:1181, 430:1461 edit mode), household banner, and intervention history.
23. Tests: age rule, household grouping, RSBSA status flow, search.

**Phase 4: Interventions and distribution**
24. Migrations: `interventions`, `distribution_cycles`, and `intervention_records` (soft deletes with reason); seed the programs and the 2026 cycles.
25. `/interventions` types page (329:2475 / 407:2).
26. `/interventions/da` and `/interventions/lgu`: filters (name, RSBSA, barangay, intervention, registration); Admin chips + Archive-with-reason modal; AT Validation and Status dropdowns (407:323, 407:603).
27. `/interventions/{da|lgu}/archived` Restore tabs (344:236, 344:531/817).
28. `ClaimService`: eligibility check, deceased proxy claim with proof note, household one-per-cycle block (admin override + reason), LGU repeat → "Duplicate" flag. Wire it to Process Claim, Verify Eligibility, and the claim dropdowns.
29. DE `/intervention-records` list, Edit, and Add Record form (430:1603, 440:166, 446:198), with beneficiary autocomplete restricted to existing profiles.
30. Tests: every `ClaimService` rule, archive/restore, the no-profile rejection.

**Phase 5: Inventory**
31. Migrations `inventory_items` and `inventory_movements`; `/inventory` (470:785) with computed stock-in/out/balance and Recent Movements.
32. Record Stock Movement modal (470:986). `ClaimService` creates the AUTO stock-out on claim, reverses it on unclaim, and blocks the claim when stock is insufficient. Low-stock threshold feeds the DE stat.
33. Tests: deduct, reverse, block, balance math.

**Phase 6: Excel import and export**
34. Migrations `import_batches` and `import_rows`; `ExcelImportService`: header-row detection, synonym + fuzzy column mapping, barangay fuzzy match, dedupe, missing-RSBSA flag, unreadable-row log.
35. `/import` (470:540 / 430:1887): drop zone upload → staged preview → Processing Feedback card → Confirm & Import (transaction), including optional intervention records.
36. `/export` (329:3134): preview of Name / RSBSA No. / Address → Export as .xlsx.
37. Tests using fixture workbooks (clean, 'Brgy' header, shifted columns, corrupt cells, duplicates).

**Phase 7: Damage recording**
38. Migrations `disasters`, `crops`, `damage_reports`, and `damage_photos`; `DamageCalculator` (loss and cost formula from crop values).
39. `/damage-reports` (329:2978 / 407:1554 / 470:1863): filters, 4 summary cards, table with photo viewer, validate action (AT/Admin), Export to Excel, Generate PDF (DomPDF view).
40. `/damage-reports/create` (423:786 / 423:306; the DE also gets the button): form, GPS fields, multi-photo upload to `storage/app/public/damage`.
41. Tests: formula, permissions, photo validation, PDF and Excel responses.

**Phase 8: Reports and dashboards**
42. `ReportService`: distribution monitoring report per cycle (program, date range) → PDF view and Excel export; save to `generated_reports`; only scheduled or completed cycles are selectable.
43. `/reports` (340:51 / 407:1694 / 470:2205).
44. `DashboardStats` live queries for all 3 roles' stat blocks.
45. Tests: cycle restriction, report totals.

**Phase 9: Seed, verify, polish**
46. `DemoSeeder`: reproduce every Figma sample row exactly so seeded screens match the frames.
47. Run the visual comparison for all 46 frames; fix mismatches until only font-rendering noise remains.
48. Playwright E2E flows for each role; full Pest suite green.
49. `README.md`: setup on XAMPP, seeded accounts, how to run the tests, and deployment on the office LAN (Apache vhost or `php artisan serve --host=0.0.0.0`).
50. Final review (`superpowers:requesting-code-review`) → fixes → final commit.

---

## 7. Verification

- **Feature tests (Pest):** one per rule in §5: role access for every route, audit log on CRUD, archive/restore, household claim block, inventory auto-deduct/reverse and insufficient-stock block, LGU duplicate flag, age ≥ 18, import mapping/dedupe/flagging against fixture .xlsx files (misspelled headers, shifted columns, corrupt cells), export columns, report restricted to cycles, damage formula.
- **E2E (Playwright):** log in as each role and walk the main flows: register → validate → claim → stock drops → report PDF downloads; Excel import preview → confirm; file damage report with photo.
- **Visual comparison:** for each of the 46 frames, `get_screenshot` at 1820px → Playwright screenshot of the matching route at 1820×1024 with seeded data → `magick compare` diff image plus a side-by-side review in the browser pane; fix every structural mismatch (font rendering noise accepted).
- **Manual run:** `php artisan serve` + XAMPP MySQL, log in as each seeded account, and click through every nav item and quick action.
