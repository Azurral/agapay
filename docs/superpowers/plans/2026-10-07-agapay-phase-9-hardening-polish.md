# AGAPAY Phase 9 (Hardening, Seed, Verify, Polish) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close every issue deferred from Phases 3–8, add the missing distribution-cycle screen, seed the Figma sample data, verify all 46 frames and the main flows end to end, and write the README, so AGAPAY is ready for the defense and for real use at OMAG.

**Architecture:** No new subsystems except a small Distribution Cycles screen (Admin). Hardening fixes go into the existing services, controllers and views, grouped by module. Each fix carries a RED→GREEN test.

**Tech Stack:** Laravel 13 / PHP 8.5, MariaDB 10.4, Pest 4, Playwright (already used by the visual harness), Blade + Tailwind 4 + Alpine 3.

**Spec:** `docs/superpowers/specs/2026-09-28-agapay-design.md`, §6 Phase 9 (steps 46–50) and §7 Verification. The deferred-issue list comes from the final-review ledgers of Phases 3–8, as collected by the user.

## Global Constraints

- **Git:** branch `feature/phase-9-polish` from `master`. Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Errors:** a user never sees an error page for input or races they can cause. Unique-constraint and lock clashes become form messages. Forged array inputs fall back to defaults.
- **Permissions:** new permissions reach existing databases through a data migration, as `damage.configure` did. Re-seeding is never needed.
- **Unit rule (Phase 8 ruling):** quantities in different units are never summed together.

## Review Focus

1. **Concurrent staff:** two users on the same RSBSA application, claim, restore or import must produce one result and one audit row (Tasks 1, 2, 5).
2. **Data typed inconsistently:** extra spaces, letter case in RSBSA numbers, "Last, First" searches. Matching must behave the same for typed and imported data (Tasks 1, 5).
3. **The new cycle screen:**
   - The cycle code is unique.
   - Exactly one cycle can be "ongoing".
   - Cycles are ordered by schedule date, not creation order.
   - Reports, the current-cycle default and "latest record" follow it.
   (Task 3.)
4. **The Figma demo data:** it must not break the existing test fixtures, which use their own seeders (Task 7).
5. **The README:** its setup steps must work on a fresh XAMPP machine (Task 9).

---

### Task 1: Beneficiaries and RSBSA hardening (Phase 3 deferred)

Fixes, with the test that pins each one:

| Fix | Test |
|---|---|
| Editing a profile checks the duplicate identity (first + last name, birthdate, barangay) against other profiles. | `it('refuses an edit that duplicates another profile')` |
| Names, address and farm location are squished (trimmed, single spaces) on save; a data migration squishes the existing rows. | `it('stores names without extra spaces')` |
| RSBSA numbers are stored trimmed and upper-case. This applies to forms, the RSBSA workflow and imports; existing rows are migrated. | `it('stores RSBSA numbers in upper case')` |
| A unique-constraint clash on the RSBSA No. becomes "RSBSA No. {n} is already used by another profile." | `it('turns a simultaneous RSBSA number into a form error')`, by catching `UniqueConstraintViolationException` |
| `RsbsaWorkflow::apply` locks the beneficiary row, re-checks the status inside the transaction, and writes the audit row in the same transaction. | `it('lets only one of two simultaneous transitions through')`, `it('writes the workflow audit in the same transaction')` |
| Search matches "First Middle Last", "First Last" and "Last, First". | `it('finds people by full name in any order')` |
| The search bar is hidden for roles without `beneficiaries.view`. | `it('hides the search bar without beneficiaries.view')` |
| The search-bar barangay list is cached (cleared when barangays change). | `it('loads the barangay list once')`, which asserts the query count across 2 requests |
| A household left with no members after a move or an archive is deleted. | `it('removes a household left empty')` |

- [ ] RED → implement → GREEN → full suite → commit `fix: harden beneficiary profiles, RSBSA workflow and search`.

### Task 2: Interventions hardening (Phase 4 deferred)

| Fix | Test |
|---|---|
| A double-submitted Add Record shows the slot message, not an error page. | `it('turns a double-submitted record into a message')` |
| The sidebar counters (Pending Validation, Active Interventions) skip records of archived beneficiaries. | `it('leaves archived farmers out of the counters')` |
| A claimed record cannot be re-validated to Deceased without proxy details: "Unclaim it first, or record the proxy." | `it('keeps proxy rules on claimed records')` |
| A refused Process Claim keeps what the Admin typed (`withInput`). | `it('keeps the claim form input after a refusal')` |
| The household banner reflects the cycle chosen in Process Claim (it lists claims per cycle). | `it('warns about household claims in the chosen cycle')` |
| An Encoder refused a deceased proxy claim sees "Only the Administrator can record a proxy claim for a deceased beneficiary." | `it('tells encoders who can record a proxy claim')` |
| Editing a record of a deactivated program still shows its intervention, marked "(inactive)". | `it('shows an inactive program on edit')` |
| Restoring a record of an archived beneficiary is refused: "Restore {name}'s profile first." | `it('refuses to restore a record of an archived farmer')` |
| Restore is idempotent: lock the row and refuse a non-archived record, so there is exactly 1 audit row. | `it('restores once')` |

- [ ] RED → implement → GREEN → full suite → commit `fix: harden intervention records, claims and restores`.

### Task 3: Distribution Cycles screen

- **Permission:** new `cycles.manage` ("Manage distribution cycles", group "Interventions"; Admin by default), added through a data migration.
- **Page:** `/distribution-cycles` (Admin), linked from the Interventions page as an outlined pill. It reuses the card, table, modal and inline-field components and has no Figma frame.
  - Columns: Code, Label, Schedule Date, Venue, Status, Records.
  - "+ Add Cycle" and Edit open a modal with: code (unique, e.g. `2026-Q4`), label, schedule date, venue, and status (scheduled / ongoing / completed).
- **Rules:**
  - Setting a cycle to ongoing makes the previous ongoing cycle completed. There is exactly one ongoing cycle, enforced in a transaction.
  - A cycle with records cannot be deleted. There is no delete action; completed cycles stay.
  - `DistributionCycle::current()` and "latest record" order by `schedule_date` (then code), not by id.
- **Audit:** "Added Distribution Cycle" / "Updated Distribution Cycle" (Auditable).
- **Tests:**
  - `it('adds a cycle that the forms and reports offer')`
  - `it('keeps one ongoing cycle')`
  - `it('orders cycles by schedule date')`
  - `it('is for administrators only')`
  - `it('refuses a duplicate code')`
- [ ] RED → implement → GREEN → full suite + build → commit `feat: add distribution cycle management`.

### Task 4: Inventory hardening (Phase 5 deferred)

| Fix | Test |
|---|---|
| Forged item-form inputs (arrays, unknown units) and unique-name clashes give field errors. | dataset `it('rejects forged item input with field errors')` |
| A movement note is limited to the column length; long beneficiary names are shortened with "…". | `it('fits long names in an automatic movement note')` |
| `InventorySeeder` is safe to run again on a dev database that has extra claims (idempotent; uses firstOrCreate). | `it('re-seeds inventory without duplicating movements')` |
| Moving a program from one item to another is audited on both items. | `it('audits a program move on both items')` |
| Ruling: renaming an item relabels its past movement lines. That is the expected behavior (movement lines show the current item name). | none (no change) |

- [ ] RED → implement → GREEN → full suite → commit `fix: harden inventory items, notes and seeding`.

### Task 5: Import and export hardening (Phase 6 deferred)

| Fix | Test |
|---|---|
| The unreadable feedback line reads "{n} rows excluded — see the reasons in the preview". This replaces the Figma copy "corrupted cells", which was wrong for the other reasons. | updated staging test |
| A file row whose person exists with a different RSBSA No. becomes unreadable: "{Name} already has RSBSA No. {x} in AGAPAY". | `it('reports a different RSBSA number for an existing profile')` |
| A unique clash during Confirm becomes "Row n: RSBSA No. {x} was registered by someone else meanwhile." | `it('turns a clash during confirm into a row message')` |
| An uploaded file is deleted on discard and after import; the rows stay as the record. | `it('deletes the uploaded file after confirm or discard')` |
| The reader is chosen by file contents (`IOFactory::identify`) within the allowed types, so a renamed .xls is read. | `it('reads an xls file saved with an xlsx name')` |
| After a confirm error, the page explains "Discard this upload, fix the file and upload it again." when the error cannot be cleared by retrying. | `it('tells the user how to recover from a confirm error')` |

- [ ] RED → implement → GREEN → full suite → commit `fix: harden excel import edge cases`.

### Task 6: Damage and report hardening (Phases 7 and 8 deferred)

| Fix | Test |
|---|---|
| Cost over 999,999,999,999.99 → "The computed cost is too large — check the crop values." | `it('refuses a cost larger than the column')` |
| A deadlock retry deletes the photos stored by the failed attempt before retrying. | `it('cleans photos of a retried attempt')` |
| Forged array input on the damage form and the reports form gives defaults or field errors, never 500. | datasets on both forms |
| A duplicate damage report error links to the existing report. | `it('links the existing report on a duplicate')` |
| An archived report's chip is grey ("Archived"), not green. | `it('shows archived reports with a neutral chip')` |
| A shared `MemoryLimit::atLeast('512M')` raises the limit only, and is used by both PDF paths. | `it('never lowers a higher memory limit')` |
| Reports count records that can never be claimed (Duplicate, Relocated, Inactive) as "Not Claimable", separately from Unclaimed. The summary, per-intervention and per-barangay rows get the new column. | `it('separates records that cannot be claimed')` |
| The `generated_reports.user_id` foreign key becomes nullable / null on delete, like the other user references. | migration |
| `inventory()` uses a subquery instead of a long id list. | covered by existing tests |
| The missing tests: `it('sorts the beneficiary list by barangay, last and first name')` and `it('only reports on distribution cycles')`. | as named |

- [ ] RED → implement → GREEN → full suite → commit `fix: harden damage reports and distribution reports`.

### Task 7: Figma demo data and visual check of all frames

- `DemoSeeder` (`php artisan db:seed --class=DemoSeeder`) seeds the Figma sample rows (names, numbers and statuses shown in the 46 frames) on top of `DatabaseSeeder`. The test suite keeps using `DatabaseSeeder` only.
- Export any of the 46 frames not yet in `tests/visual/figma/` and add every frame to SCREENS. Run `npm run visual` against the demo seed and fix structural mismatches until only font rendering, logo and documented rulings remain.
- [ ] Commit `test: seed figma demo data and cover all 46 frames`.

### Task 8: End-to-end flows (Playwright)

- **File:** `tests/e2e/flows.spec.js`, run with `npm run e2e`. It uses the existing Playwright config pattern, a freshly migrated and seeded database, and the dev server on :8765.
- **Flows:**
  1. **Encoder:** registers a farmer.
  2. **Agri Tech:** validates the farmer, then validates and claims a record. The stock drops.
  3. **Admin:** generates the distribution PDF, which downloads.
  4. **Encoder:** imports an .xlsx, then confirms it.
  5. **Encoder:** files a damage report with a photo. The Agri Tech validates it.
- [ ] Commit `test: add end-to-end flows for each role`.

### Task 9: README

- **`README.md` covers:**
  - Requirements: XAMPP (PHP 8.5, MariaDB), Composer, Node.
  - php.ini: `upload_max_filesize=25M`, `post_max_size=60M`, `memory_limit=512M`, plus the needed extensions.
  - Setup: `.env`, `key:generate`, `migrate --seed`, the optional DemoSeeder, `npm run build`, `php artisan serve --port=8765`.
  - The seeded accounts and the password from `AGAPAY_SEED_PASSWORD`.
  - Roles and their permissions.
  - Running the tests: Pest, visual, e2e.
  - Office network deployment: `serve --host=0.0.0.0` or an Apache vhost, and the HTTPS note for "Use my location".
  - Backups: database dump and `storage/app/private`.
  - Known limits: the PDF row caps.
- [ ] Commit `docs: add setup, usage and deployment README`.

### Final review

- One whole-branch review by the opus reviewer, then one fix pass. Rulings and deferred minors are reported to the user.
