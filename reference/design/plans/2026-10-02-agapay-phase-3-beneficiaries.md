# AGAPAY Phase 3 (Beneficiaries & RSBSA) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** OMAG staff can register RSBSA applicants, move them through validation → DA endorsement → registration, find any beneficiary by name, RSBSA number or barangay, see auto-grouped households, and (Data Encoder) correct profiles — on screens matching Figma frames 329:1596, 430:2029, 310:2, 237:1659, 430:1276, 329:2822, 407:1181, 430:1461.

**Architecture:** New `barangays`, `households`, `beneficiaries` tables. `HouseholdService` groups beneficiaries by normalized address + barangay on every save. `RsbsaWorkflow` owns the RSBSA status machine and writes its own audit rows. Pages reuse the Phase 1–2 shell and components; one new `<x-beneficiary.table>` component serves the dashboard and search results. Intervention-dependent columns/buttons render an explicit "—" / disabled state until Phase 4 replaces them.

**Tech Stack:** Laravel 13 / PHP 8.5, MariaDB 10.4 (dev) / SQLite in-memory (tests), Pest 4, Blade + Tailwind 4 + Alpine 3. Laravel Boost guidelines in `CLAUDE.md` apply (run `vendor/bin/pint --dirty --format agent` after PHP changes; use `php artisan make:*` with `--no-interaction`).

**Spec:** `docs/superpowers/specs/2026-09-28-agapay-design.md` (§4 data model, §5 rules 4, 5, 8, 13, 14; §6a steps 18–23). Previous plan: `docs/superpowers/plans/2026-09-28-agapay-phase-1-2-shell-auth.md` (components, `Navigation`, `DashboardStats`, `Auditable`, `AuditLogger`, `queryText()` macro).

## Global Constraints

- Work on branch `feature/phase-3-beneficiaries` from `master`. Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Tests: Pest on SQLite in-memory; never the `agapay` MySQL DB. Query-string text is always read with `$request->queryText('key')` (arrays become `''`).
- The 16 barangays, exact spelling: Alab Oriente, Alab Proper, Bayyo, Balili, Bontoc Ili, Can-eo, Caluttit, Dalican, Gonogon, Guina-ang, Mainit, Maligcong, Poblacion, Samoki, Talubin, Tocucan.
- RSBSA eligibility: Filipino applicant **at least 18 years old** at registration (birthdate not in the future).
- RSBSA statuses (string column): `pending_validation`, `validated`, `endorsed`, `registered`, `returned`, `rejected`. "Pending RSBSA" counts `pending_validation`, `validated`, `endorsed`.
- RSBSA number shown as stored; null renders `(pending)`. Unique ignoring case and surrounding spaces.
- Figma form field (329:3298): 39px tall, radius 15, fill `rgba(126,128,255,0.1)`, 16px left padding, medium 14px text, placeholder `#b4b4b4`; labels bold 16px, 27px above field; 2 columns 705px wide starting at 21.5 / 755.5; row pitch 75px. Card subtitle (329:3297) medium 14px `#b4b4b4`. Divider at 70.5px.
- Figma "Submit Registration" bar (329:3330): full width, 59px, radius 50, `bg-brand-bar`, bold 20px white text centered with 21px plus icon; 20px below the form card.
- Figma profile banner (329:3398): 1439×59, 4px `#f9a8a9` border, radius 15, bold 20px text at 22px left.
- Figma profile info (329:3373): 338px card; label medium 14px `#b4b4b4`, value bold 14px, 45px pitch starting at 57.5. Edit variant (430:2421): label medium 14px black; value field 298×28, radius 8, fill `rgba(126,128,255,0.1)`, text medium 14px `#7e80ff`; household value `#b4b4b4`.
- Figma history card (329:3336): 1129px; headers Date@21.5, Source@285.5, Intervention@521.5, Barangay@769.5, Status chip 155px @949.5; divider at 88.5.
- Figma action bar (329:3400): 1488×59, radius 30, `bg-brand-bar`; title bold 20px white at (25,9); subtitle regular 13px white at (25,33); white pill 253×39 at right 11px, bold 20px.
- Figma DE list (440:43): filters 230px wide each at 21.5 / 276.5 / 531.5 / 786.5; headers Household@1064.5, Status 231px centered @1254, Action 98px @1363.5; rows: name@36.5, RSBSA@287.5, barangay@547.5, intervention@802.5, household@1064.5; Edit chip 98×39, 4px border `#7e80ff`, radius 10.
- Audit action labels: "Added Beneficiary Profile", "Updated Beneficiary Profile", "Archived Beneficiary Profile", "Restored Beneficiary Profile", "Validated RSBSA Registration", "Endorsed RSBSA Registration to DA-RFO", "Recorded RSBSA Number", "Returned RSBSA Registration", "Rejected RSBSA Registration", "Resubmitted RSBSA Registration". Record label: `"{Full Name} ({rsbsa_number or 'pending'})"`, e.g. `Juan Dela Cruz (RSBSA-0231)`.

## Review Focus

1. **Duplicate applicant** — the same person (same first + last name, birthdate, barangay; ignoring case/spaces) submitted twice must be refused with "This person is already registered." (Task 2).
2. **Address spelling variants** — "Purok 3, Barangay Poblacion", "purok 3 brgy. poblacion" and "Purok 3" (barangay Poblacion) must land in one household (Task 1).
3. **Illegal workflow jumps** — recording an RSBSA number on a `pending_validation` record, or acting on a `rejected` one, must be refused with an error and leave the record unchanged (Task 3).
4. **RSBSA number collisions** — `" rsbsa-0231 "` when `RSBSA-0231` exists must be refused, both on workflow "record number" and on DE edit (Tasks 3, 6).
5. **Hostile search input** — `%`, `_`, quotes, 200-character strings or `?q[]=` must return a normal page (no 500), `%` must not match everything (Task 4).

---

## File Structure

```
app/Models/Barangay.php, Household.php, Beneficiary.php
app/Services/HouseholdService.php          normalize address, find-or-create household
app/Services/RsbsaWorkflow.php              status machine + audit rows
app/Exceptions/InvalidRsbsaTransition.php
app/Http/Middleware/EnsureCanAny.php        alias can.any:perm1,perm2
app/Http/Requests/BeneficiaryRules.php      shared rules trait (store + update)
app/Http/Requests/StoreRsbsaRegistrationRequest.php
app/Http/Requests/UpdateBeneficiaryRequest.php
app/Http/Controllers/RsbsaRegistrationController.php   create, store, transition
app/Http/Controllers/BeneficiaryController.php         index (DE list), show, update
app/Http/Controllers/SearchController.php
app/Support/DashboardStats.php              (modify: 4 beneficiary arms)
app/Http/Controllers/DashboardController.php (modify: table data)
database/migrations/2026_10_02_000001_create_barangays_table.php
database/migrations/2026_10_02_000002_create_households_table.php
database/migrations/2026_10_02_000003_create_beneficiaries_table.php
database/factories/BeneficiaryFactory.php
database/seeders/BarangaySeeder.php, BeneficiarySeeder.php, DatabaseSeeder.php (modify)
resources/views/components/beneficiary/table.blade.php       All Beneficiaries style table
resources/views/components/beneficiary/form-field.blade.php  Figma 329:3298 field
resources/views/components/beneficiary/action-bar.blade.php  Figma 329:3400 bar
resources/views/rsbsa/register.blade.php
resources/views/beneficiaries/index.blade.php, show.blade.php
resources/views/search/index.blade.php
resources/views/dashboard.blade.php (modify), components/app/search-bar.blade.php (modify: filter popover)
routes/web.php, bootstrap/app.php (modify)
tests/Feature/{Household,RsbsaRegistration,RsbsaWorkflow,Search,BeneficiaryList,BeneficiaryProfile,DashboardBeneficiaries}Test.php
tests/visual/screens.spec.js, tests/visual/figma/*.png (modify/add)
```

---

### Task 1: Barangays, households, beneficiaries and household grouping

**Files:**
- Create: the three migrations, `Barangay`, `Household`, `Beneficiary` models, `HouseholdService`, `BeneficiaryFactory`, `BarangaySeeder`
- Modify: `database/seeders/DatabaseSeeder.php` (call `BarangaySeeder` before `UserSeeder`)
- Test: `tests/Feature/HouseholdTest.php`

**Interfaces:**
- Produces:
  - `Barangay` (`id`, `name` unique). `Barangay::NAMES` = the 16 names in Global Constraints order.
  - `Household` (`id`, `barangay_id`, `address_key`, `household_no` unique, timestamps; unique `[barangay_id, address_key]`); `members(): HasMany<Beneficiary>`; `household_no` format `HH-` + 5-digit zero-padded id (`HH-00012`), assigned right after insert.
  - `Beneficiary` (uses `Auditable`, `SoftDeletes`, `HasFactory`): columns `first_name`, `middle_name?`, `last_name`, `birthdate` (date), `address`, `barangay_id`, `contact_number?`, `farm_location?`, `crop_type?`, `rsbsa_number?` (unique), `rsbsa_status` (default `pending_validation`), `rsbsa_status_reason?`, `life_status` (default `active`), `household_id?`, `encoding_issue?`, `source` (`manual`|`excel_import`, default `manual`), `created_by?`, `updated_by?`, `delete_reason?`, `deleted_by?`, timestamps, softDeletes.
  - `Beneficiary` constants: `RSBSA_PENDING`, `RSBSA_VALIDATED`, `RSBSA_ENDORSED`, `RSBSA_REGISTERED`, `RSBSA_RETURNED`, `RSBSA_REJECTED` (values as in Global Constraints), `RSBSA_IN_PROGRESS` = first three, `SOURCE_MANUAL`, `SOURCE_IMPORT`.
  - `Beneficiary` methods: `fullName(): string` (first + middle + last, single spaces), `age(): int`, `rsbsaDisplay(): string` (`(pending)` when null), `auditRecordLabel(): string`, `householdSize(): int` (members incl. self, 1 when no household), `otherHouseholdMembers(): Collection`, `barangay(): BelongsTo`, `household(): BelongsTo`, `creator(): BelongsTo`; `protected string $auditSubject = 'Beneficiary Profile'`; `protected array $auditIgnore = ['household_id', 'updated_by']`.
  - Observer behaviour in `Beneficiary::booted()`: on `saving`, if `address` or `barangay_id` is dirty (or on create) call `HouseholdService::assign($beneficiary)`; trim and uppercase-normalize nothing else.
  - `HouseholdService::normalizeAddress(string $address, string $barangayName): string` and `HouseholdService::assign(Beneficiary $beneficiary): Household`.

- [ ] **Step 1: Write the failing tests** — `tests/Feature/HouseholdTest.php` (seed `BarangaySeeder` in `beforeEach`):

```php
it('seeds the 16 Bontoc barangays', fn () => expect(Barangay::pluck('name')->all())->toBe(Barangay::NAMES));

it('normalizes address spelling variants to one key', function (string $address) {
    expect(HouseholdService::normalizeAddress($address, 'Poblacion'))->toBe('purok 3');
})->with(['Purok 3, Barangay Poblacion', 'purok 3 brgy. poblacion', '  PUROK   3 ', 'Purok 3, Bgy Poblacion']);

it('groups beneficiaries sharing an address into one household', function () {
    $poblacion = Barangay::firstWhere('name', 'Poblacion');
    $juan = Beneficiary::factory()->create(['address' => 'Purok 3, Barangay Poblacion', 'barangay_id' => $poblacion->id]);
    $maria = Beneficiary::factory()->create(['address' => 'purok 3', 'barangay_id' => $poblacion->id]);
    $other = Beneficiary::factory()->create(['address' => 'Purok 4', 'barangay_id' => $poblacion->id]);

    expect($maria->household_id)->toBe($juan->household_id)
        ->and($other->household_id)->not->toBe($juan->household_id)
        ->and($juan->fresh()->householdSize())->toBe(2)
        ->and($juan->household->household_no)->toMatch('/^HH-\d{5}$/')
        ->and($juan->otherHouseholdMembers()->pluck('id')->all())->toBe([$maria->id]);
});

it('same address in a different barangay is a different household', function () { /* Purok 3 Poblacion vs Purok 3 Samoki → different household_id */ });

it('moves a beneficiary to a new household when the address changes', function () { /* update address → household_id changes, old household size drops to 1 */ });

it('labels beneficiaries for screens and the audit trail', function () {
    $b = Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'rsbsa_number' => 'RSBSA-0231', 'birthdate' => now()->subYears(45)->subDay()]);
    expect($b->fullName())->toBe('Juan Dela Cruz')
        ->and($b->age())->toBe(45)
        ->and($b->auditRecordLabel())->toBe('Juan Dela Cruz (RSBSA-0231)')
        ->and(Beneficiary::factory()->make(['rsbsa_number' => null])->rsbsaDisplay())->toBe('(pending)');
});
```

The `/* … */` tests are two assertions each; write them in the same style.

- [ ] **Step 2: Run** `./vendor/bin/pest tests/Feature/HouseholdTest.php` — Expected: FAIL, `Class "App\Models\Barangay" not found`.

- [ ] **Step 3: Implement migrations, models, service, factory, seeder.** `normalizeAddress`: lowercase → replace `.` and `,` with spaces → remove the barangay name and the words `barangay`, `brgy`, `bgy` as whole words → collapse whitespace → trim. `assign`: `firstOrCreate` on `[barangay_id, address_key]`, then set `household_no` if empty; sets `$beneficiary->household_id` (no save — it runs inside `saving`). Factory: Filipino-sounding names, birthdate 20–70 years ago, random barangay, `address` "Purok {1-9}", status `registered`, unique `RSBSA-####`.

- [ ] **Step 4: Run** the test file — Expected: PASS (7 tests + dataset). Then `./vendor/bin/pest` — all green; `php artisan migrate --no-interaction` on dev DB — 3 migrations DONE; `php artisan db:seed --class=BarangaySeeder --no-interaction`.

- [ ] **Step 5: Commit** `feat: add barangays, beneficiaries and automatic household grouping`.

---

### Task 2: RSBSA registration form (frames 329:1596 / 430:2029)

**Files:**
- Create: `EnsureCanAny` middleware (alias `can.any` in `bootstrap/app.php`), `BeneficiaryRules` trait, `StoreRsbsaRegistrationRequest`, `RsbsaRegistrationController` (`create`, `store`), `components/beneficiary/form-field.blade.php`, `rsbsa/register.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/RsbsaRegistrationTest.php`

**Interfaces:**
- Consumes: `Beneficiary`, `Barangay::NAMES`, `<x-layouts.app>`, `<x-ui.card>`, `<x-ui.status-chip>`.
- Produces:
  - Routes (inside `auth, active`): `GET /rsbsa/register` → `rsbsa.register` (`can.any:rsbsa.register,rsbsa.process`); `POST /rsbsa/register` → `rsbsa.store` (`can:rsbsa.register`).
  - `BeneficiaryRules::beneficiaryRules(?Beneficiary $ignore = null): array` — `first_name`, `last_name` required string max 100; `middle_name` nullable max 100; `birthdate` required date, `before_or_equal:` today minus 18 years (message "Applicant must be at least 18 years old."); `address` required max 255; `barangay_id` required exists; `contact_number` nullable regex `/^09\d{2}-?\d{3}-?\d{4}$/` ("Use the format 09XX-XXX-XXXX."); `farm_location`, `crop_type` nullable max 255.
  - Duplicate check in `StoreRsbsaRegistrationRequest::after()`: a non-deleted beneficiary with same lowercased trimmed first + last name, birthdate and barangay → error on `first_name`: "This person is already registered."
  - `<x-beneficiary.form-field label name :value type placeholder :error icon>` renders the Figma field (Global Constraints); `type="date"` shows `icons/calendar.svg` at right 11px.
  - Store creates with `rsbsa_status = pending_validation`, `source = manual`, `created_by = auth id`, redirects to `rsbsa.register` with status "{Full Name} was registered and is awaiting OMAG validation."

- [ ] **Step 1: Write the failing tests**

```php
beforeEach(function () { seedRoles(); test()->seed(BarangaySeeder::class); });

it('shows the Figma registration form to encoders and admins', function (string $role) {
    $this->actingAs(userWithRole($role))->get('/rsbsa/register')->assertOk()
        ->assertSee('RSBSA REGISTRATION FORM')
        ->assertSeeInOrder(['RSBSA Registration Form (To be filled up by the beneficiary)',
            'Registered applicant → OMAG validation → endorse to DA-RFO → masterlist returns.',
            'First Name', 'Address', 'Middle Name', 'Barangay', 'Last Name', 'Contact Number',
            'Birthdate', 'Farm Location', 'Age', 'Crop Type', 'Submit Registration']);
})->with([Role::ADMIN, Role::ENCODER]);

it('lets processors view the page without the form', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->get('/rsbsa/register')   // has rsbsa.process only
        ->assertOk()->assertDontSee('Submit Registration');
});

it('hides the page from users with neither RSBSA permission', function () {
    $user = userWithRole(Role::ENCODER);
    $user->role->permissions()->detach(Permission::whereIn('slug', ['rsbsa.register', 'rsbsa.process'])->pluck('id'));
    $this->actingAs($user->fresh())->get('/rsbsa/register')->assertForbidden();
});

it('registers an applicant as pending validation', function () {
    $encoder = userWithRole(Role::ENCODER);
    $this->actingAs($encoder)->post('/rsbsa/register', validRegistration())
        ->assertRedirect(route('rsbsa.register'))->assertSessionHas('status');
    $b = Beneficiary::sole();
    expect($b->rsbsa_status)->toBe(Beneficiary::RSBSA_PENDING)->and($b->created_by)->toBe($encoder->id)
        ->and(AuditLog::where('action', 'Added Beneficiary Profile')->where('record_label', 'Juan Abenoja Dela Cruz (pending)')->exists())->toBeTrue();
});

it('refuses applicants under 18 and future birthdates', function (string $birthdate) {
    $this->actingAs(userWithRole(Role::ENCODER))->post('/rsbsa/register', validRegistration(['birthdate' => $birthdate]))
        ->assertSessionHasErrors(['birthdate' => 'Applicant must be at least 18 years old.']);
})->with([fn () => now()->subYears(17)->toDateString(), fn () => now()->addDay()->toDateString()]);

it('refuses a duplicate applicant ignoring case and spaces', function () {
    $encoder = userWithRole(Role::ENCODER);
    $this->actingAs($encoder)->post('/rsbsa/register', validRegistration());
    $this->actingAs($encoder)->post('/rsbsa/register', validRegistration(['first_name' => ' JUAN ', 'last_name' => 'dela cruz']))
        ->assertSessionHasErrors(['first_name' => 'This person is already registered.']);
    expect(Beneficiary::count())->toBe(1);
});

it('validates the contact number format', /* '12345' → error 'Use the format 09XX-XXX-XXXX.'; '0917-123-4567' and '09171234567' accepted */);
```

Define the helper in the test file: `function validRegistration(array $overrides = []): array` returning first_name Juan, middle_name Abenoja, last_name Dela Cruz, birthdate 45 years ago, address "Purok 3", Poblacion's id, contact `0917-123-4567`, farm_location "Poblacion (1.5 hectares)", crop_type "Cabbage".

- [ ] **Step 2: Run** `./vendor/bin/pest tests/Feature/RsbsaRegistrationTest.php` — Expected: FAIL (404 on `/rsbsa/register`).

- [ ] **Step 3: Implement** middleware (`handle(Request, Closure, string ...$abilities)`: 403 unless the user `can()` any), requests, controller, views. Page layout: `<x-layouts.app title="RSBSA REGISTRATION FORM">`, card with icon/title + subtitle (14px muted) + divider at 70.5px, a 2-column grid (`grid-cols-2 gap-x-[29px]`, each row: label then field, row pitch 75px) in Figma order — left column First Name, Middle Name, Last Name, Birthdate, Age (read-only, Alpine-computed from birthdate, placeholder "Must be 18 years old or above"); right column Address ("House No / Purok / Street"), Barangay (select over 16), Contact Number ("09XX-XXX-XXXX"), Farm Location ("Poblacion (1.5 hectares)"), Crop Type ("Cabbage"). Placeholders are the Figma sample texts (Juan, Abenoja, Dela Cruz, MM / DD / YYYY). Then the Submit Registration bar (button, `bg-brand-bar` + `gradient-button`, plus icon). Only users with `rsbsa.register` see the form; others see only the pending card (Task 3).

- [ ] **Step 4: Run** the test file — Expected: PASS. Full suite green. `npm run build`.

- [ ] **Step 5: Commit** `feat: add RSBSA registration form with age and duplicate checks`.

---

### Task 3: RSBSA workflow and pending-applications card

**Files:**
- Create: `app/Exceptions/InvalidRsbsaTransition.php`, `app/Services/RsbsaWorkflow.php`
- Modify: `RsbsaRegistrationController` (`transition`), `rsbsa/register.blade.php` (pending card below the submit bar), `routes/web.php`
- Test: `tests/Feature/RsbsaWorkflowTest.php`

**Interfaces:**
- Consumes: `Beneficiary` constants, `AuditLogger::record()`.
- Produces:
  - `RsbsaWorkflow::apply(Beneficiary $beneficiary, string $action, array $input = []): Beneficiary` — throws `InvalidRsbsaTransition` (extends `DomainException`, message shown to user). Allowed:

    | action | from | to | input |
    |---|---|---|---|
    | `validate` | pending_validation | validated | — |
    | `endorse` | validated | endorsed | — |
    | `record-number` | endorsed | registered | `rsbsa_number` required, unique (trim, case-insensitive) |
    | `return` | pending_validation, validated, endorsed | returned | `reason` required (max 255); also sets `encoding_issue = reason` |
    | `reject` | pending_validation, validated, endorsed, returned | rejected | `reason` required |
    | `resubmit` | returned | pending_validation | — ; clears `rsbsa_status_reason`, `encoding_issue` |

    Saves with `saveQuietly()` (no generic "Updated" row) and records the matching action label from Global Constraints with `old`/`new` status.
  - Route `POST /rsbsa/{beneficiary}/{action}` → `rsbsa.transition`, `can:rsbsa.process`, `whereIn('action', [...6 actions])`; redirects back with status "{Full Name}: {new status label}." or error bag `rsbsa`.
  - Status labels (chips): Pending Validation (bad), Validated (ok), Endorsed to DA-RFO (ok), Registered (ok), Returned (bad), Rejected (bad).
  - Pending card ("Pending RSBSA Applications"): lists beneficiaries whose status is in progress or `returned`, newest first, columns Name / Barangay / Date Submitted (`M j, Y`) / Status chip / Actions; action buttons only for `rsbsa.process`; `record-number`, `return`, `reject` open a `<x-ui.modal>` with one `<x-ui.inline-field>`.

- [ ] **Step 1: Write the failing tests**

```php
it('walks an applicant from pending to registered', function () {
    $admin = userWithRole(Role::ADMIN);
    $b = Beneficiary::factory()->create(['rsbsa_status' => Beneficiary::RSBSA_PENDING, 'rsbsa_number' => null]);
    $this->actingAs($admin);
    $this->post(route('rsbsa.transition', [$b, 'validate']))->assertRedirect();
    $this->post(route('rsbsa.transition', [$b, 'endorse']));
    $this->post(route('rsbsa.transition', [$b, 'record-number']), ['rsbsa_number' => 'RSBSA-0500']);
    expect($b->fresh())->rsbsa_status->toBe('registered')->rsbsa_number->toBe('RSBSA-0500')
        ->and(AuditLog::pluck('action'))->toContain('Validated RSBSA Registration', 'Endorsed RSBSA Registration to DA-RFO', 'Recorded RSBSA Number')
        ->and(AuditLog::where('action', 'Updated Beneficiary Profile')->exists())->toBeFalse();
});

it('refuses illegal jumps and leaves the record unchanged', function (string $from, string $action) {
    $b = Beneficiary::factory()->create(['rsbsa_status' => $from, 'rsbsa_number' => null]);
    $this->actingAs(userWithRole(Role::ADMIN))->post(route('rsbsa.transition', [$b, $action]), ['rsbsa_number' => 'X-1', 'reason' => 'x'])
        ->assertSessionHasErrorsIn('rsbsa');
    expect($b->fresh()->rsbsa_status)->toBe($from);
})->with([['pending_validation', 'record-number'], ['pending_validation', 'endorse'], ['rejected', 'validate'], ['registered', 'return']]);

it('requires a reason to return or reject and flags returned records for the encoder', /* return without reason → error; with reason 'Awaiting Barangay Confirmation' → status returned, encoding_issue set; resubmit clears it */);

it('refuses an RSBSA number already in use, ignoring case and spaces', /* existing 'RSBSA-0231'; record-number ' rsbsa-0231 ' → error, status stays endorsed */);

it('only lets rsbsa.process holders change status', fn () => /* encoder posts validate → 403 */);

it('lists pending applications with actions for processors only', /* admin sees "Pending RSBSA Applications", name, "Pending Validation", a "Validate" button; encoder sees the row but no "Validate" button */);
```

- [ ] **Step 2: Run** `./vendor/bin/pest tests/Feature/RsbsaWorkflowTest.php` — Expected: FAIL (route not defined).

- [ ] **Step 3: Implement** `RsbsaWorkflow` as a transition table (`private const TRANSITIONS = ['validate' => [[from…], to], …]`), the controller method (catch `InvalidRsbsaTransition` and `ValidationException` → `back()->withErrors([...], 'rsbsa')`), the route, and the pending card.

- [ ] **Step 4: Run** test file then full suite — Expected: PASS / all green.

- [ ] **Step 5: Commit** `feat: add RSBSA validation, endorsement and registration workflow`.

---

### Task 4: Global search, Filter popover, dashboard tables and live counters

**Files:**
- Create: `SearchController` (`__invoke`), `search/index.blade.php`, `components/beneficiary/table.blade.php`
- Modify: `components/app/search-bar.blade.php` (form action `route('search')`, Filter opens popover), `DashboardController`, `dashboard.blade.php`, `app/Support/DashboardStats.php`, `routes/web.php`, `Beneficiary` (add `scopeSearch`)
- Test: `tests/Feature/SearchTest.php`, `tests/Feature/DashboardBeneficiariesTest.php`

**Interfaces:**
- Consumes: `Beneficiary`, `Barangay`, `<x-ui.card>`.
- Produces:
  - `Beneficiary::scopeSearch(Builder $query, string $term): Builder` — trims; empty → no-op; escapes `%`, `_`, `\`; matches `first_name`, `last_name`, `CONCAT(first_name,' ',last_name)` (use `first_name || ' ' || last_name` on SQLite via `DB::getDriverName()`), `rsbsa_number`, or barangay name (`whereHas`).
  - Route `GET /search` → `search` (`can:beneficiaries.view`); query `q`, `barangay` (id), `rsbsa_status` (one of the six). Page title "SEARCH RESULTS"; card title "Search Results"; empty → "No beneficiaries match your search."; paginated 10, `withQueryString()`.
  - Filter popover in the search bar: Alpine dropdown anchored under the Filter pill (white, radius 15, 1.5px black/10 border, brand shadow), with Barangay and RSBSA Status `<select>`s and an "Apply" `gradient-button`; selects are form fields of the same search form.
  - `<x-beneficiary.table :beneficiaries :title :empty>` — the 310:2 table: columns Name / RSBSA Number / Barangay / Household / Intervention / Status (grid `310px 261px 236px 234px 1fr 155px`); Household "N member(s)"; Intervention and Status cells render `—` (Phase 4 fills them); whole row links to `beneficiaries.show`.
  - Dashboard: Admin/AT card "All Beneficiaries" = 10 most recently updated beneficiaries via the component. DE card "Pending Encoding Queue" = beneficiaries with `encoding_issue` not null, columns Name / Source ("Excel Import" | "Manual Entry") / Issue / Date Added (`M j, Y`) / Status chip "Incomplete" (bad), each row links to the profile.
  - `DashboardStats` arms: `total_beneficiaries` = non-deleted count; `pending_rsbsa` = status in `RSBSA_IN_PROGRESS`; `encoded_this_month` = created this month with `created_by` = user; `records_to_update` = `encoding_issue` not null. Other arms stay 0 (Phase 4–7).

- [ ] **Step 1: Write the failing tests** — `SearchTest.php`:

```php
it('finds beneficiaries by name, full name, RSBSA number and barangay', function (string $q) {
    Beneficiary::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'rsbsa_number' => 'RSBSA-0231', 'barangay_id' => brgy('Poblacion')]);
    Beneficiary::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'rsbsa_number' => 'RSBSA-0198', 'barangay_id' => brgy('Samoki')]);
    $this->actingAs(userWithRole(Role::AGRITECH))->get('/search?q='.urlencode($q))
        ->assertOk()->assertSee('Juan Dela Cruz')->assertDontSee('Maria Santos');
})->with(['juan', 'Juan Dela', 'rsbsa-0231', 'poblacion']);

it('filters by barangay and RSBSA status', /* two records; ?barangay={samoki}&rsbsa_status=registered shows only Maria */);

it('treats hostile input as plain text', function (string $q) {
    Beneficiary::factory()->create(['first_name' => 'Juan']);
    $this->actingAs(userWithRole(Role::ADMIN))->get('/search?'.$q)->assertOk()->assertDontSee('Juan');
})->with(['q=%25', 'q=_', 'q=%27%22', 'q[]=x&q[]=y', 'q='.str_repeat('a', 200)]);
```

`DashboardBeneficiariesTest.php`:

```php
it('shows recent beneficiaries with household size on the admin dashboard', /* Juan + Maria same household → row "Juan Dela Cruz", "RSBSA-0231", "Poblacion", "2 members" */);
it('shows the encoder their pending encoding queue', /* encoding_issue 'Missing RSBSA Number', source excel_import → "Excel Import", "Missing RSBSA Number", "Incomplete" */);
it('counts beneficiary stats live', function () {
    $encoder = userWithRole(Role::ENCODER);
    Beneficiary::factory()->count(2)->create(['rsbsa_status' => 'pending_validation', 'created_by' => $encoder->id]);
    Beneficiary::factory()->create(['rsbsa_status' => 'registered', 'encoding_issue' => 'Missing RSBSA Number']);
    $admin = userWithRole(Role::ADMIN);
    expect(DashboardStats::value('total_beneficiaries', $admin))->toBe(3)
        ->and(DashboardStats::value('pending_rsbsa', $admin))->toBe(2)
        ->and(DashboardStats::value('encoded_this_month', $encoder))->toBe(2)
        ->and(DashboardStats::value('records_to_update', $encoder))->toBe(1);
});
```

Add `function brgy(string $name): int` to `tests/Pest.php` (returns the seeded barangay id) and seed `BarangaySeeder` in these files' `beforeEach`.

- [ ] **Step 2: Run** both files — Expected: FAIL (route `search` not defined / stats are 0).

- [ ] **Step 3: Implement** scope, controller, component, popover, dashboard data and stat arms. Update `AppShellTest` stat expectations only if they break (they assert labels, not values).

- [ ] **Step 4: Run** both files then full suite — Expected: PASS / all green. `npm run build`.

- [ ] **Step 5: Commit** `feat: add beneficiary search with filters, dashboard tables and live counters`.

---

### Task 5: Data Encoder "Beneficiary Profiles" list (frame 430:1276)

**Files:**
- Create: `BeneficiaryController::index`, `beneficiaries/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BeneficiaryListTest.php`

**Interfaces:**
- Consumes: `Beneficiary::scopeSearch`, `<x-ui.pill-input>`, `<x-ui.pill-select>`.
- Produces: route `GET /beneficiaries` → `beneficiaries.index` (`can:beneficiaries.manage`); query `name`, `rsbsa`, `barangay`; Intervention select present with only "All" until Phase 4. Columns per Global Constraints (440:43); Status cell `—` until Phase 4; "Edit" chip (98×39, `#7e80ff` border) links to `beneficiaries.show` with `?edit=1`. Paginated 15.

- [ ] **Step 1: Write the failing tests** — encoder sees `BENEFICIARY PROFILES`, filter placeholders "Search Name...", "RSBSA Number...", "Barangay: All", "Intervention: All", headers Household/Status/Action, a row with name, RSBSA, barangay, "2 members" and an `Edit` link to the profile; `?name=juan` and `?rsbsa=0198` and `?barangay=` filter; agritech (no `beneficiaries.manage`) gets 403.

- [ ] **Step 2: Run** — Expected: FAIL (404).

- [ ] **Step 3: Implement** controller action and view (filters in one GET form; text inputs submit on Enter, selects auto-submit via `pill-select`).

- [ ] **Step 4: Run** — Expected: PASS; full suite green.

- [ ] **Step 5: Commit** `feat: add Data Encoder beneficiary profiles list`.

---

### Task 6: Beneficiary profile in three role variants + encoder edit (frames 329:2822 / 407:1181 / 430:1461)

**Files:**
- Create: `BeneficiaryController::show`, `::update`, `UpdateBeneficiaryRequest` (uses `BeneficiaryRules` + `rsbsa_number` nullable unique-ignoring-self, trimmed, case-insensitive), `components/beneficiary/action-bar.blade.php`, `beneficiaries/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BeneficiaryProfileTest.php`

**Interfaces:**
- Consumes: `Beneficiary::otherHouseholdMembers()`, `householdSize()`, `HouseholdService` (via model observer), `<x-ui.card>`, `<x-ui.status-chip>`.
- Produces:
  - Routes: `GET /beneficiaries/{beneficiary}` → `beneficiaries.show` (`can:beneficiaries.view`); `PUT /beneficiaries/{beneficiary}` → `beneficiaries.update` (`can:beneficiaries.manage`).
  - Variant by role slug: `data_encoder` → always the edit variant (430:1461 is the only DE profile frame; the list's `?edit=1` link lands there too); `agricultural_technologist` → "ELIGIBILITY VERIFICATION" / "Checking eligibility status for current intervention cycle..." / "Verify Eligibility"; others → "CLAIM VERIFICATION" / "Checking household claim status for current intervention cycle..." / "Process Claim". Until Phase 4 the Verify/Process buttons render `disabled` with `title="Available once intervention records exist (Phase 4)."`.
  - Banner text: with others → "{N} other registered member(s) share this address ({names, comma-separated}) - no other claims made yet." (bad border); none → "No other registered members share this address." (ok border). (Figma's leading word "Search" is a design-file typo and is dropped.)
  - Profile info (read variant): Full Name, Age, Address (as stored + ", Barangay {name}" when the address doesn't already contain the barangay name), Barangay, RSBSA Number (`rsbsaDisplay()`), Household Number (`household_no`).
  - Edit variant fields (Figma 430:2421 style): First Name, Middle Name, Last Name, Birthdate (date) with Age shown read-only, Address, Barangay (select), RSBSA Number, Contact Number, Farm Location, Crop Type, Household Number (read-only, muted). Action bar "EDIT MODE" / "Editing beneficiary profile - remember to save your changes." / "Save Changes" (submit).
  - Update: on success redirect back to the profile with status "Profile saved."; setting a non-empty `rsbsa_number` on a record whose status is not `registered` sets status `registered` and clears `encoding_issue` when it was "Missing RSBSA Number". Generic "Updated Beneficiary Profile" audit row comes from `Auditable`; `updated_by` is set.
  - Intervention History card: headers Date / Source / Intervention / Barangay / Status, body "No interventions recorded yet." (Phase 4 replaces).

- [ ] **Step 1: Write the failing tests**

```php
it('shows the household banner and profile information', function () {
    $juan = Beneficiary::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'address' => 'Purok 3', 'barangay_id' => brgy('Poblacion'), 'rsbsa_number' => 'RSBSA-0231']);
    Beneficiary::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'address' => 'Purok 3', 'barangay_id' => brgy('Poblacion')]);
    $this->actingAs(userWithRole(Role::ADMIN))->get(route('beneficiaries.show', $juan))->assertOk()
        ->assertSee('BENEFICIARY PROFILE')
        ->assertSee('1 other registered member share this address (Maria Dela Cruz) - no other claims made yet.')
        ->assertSeeInOrder(['Full Name', 'Juan Dela Cruz', 'Age', 'Address', 'Purok 3, Barangay Poblacion', 'Barangay', 'Poblacion', 'RSBSA Number', 'RSBSA-0231', 'Household Number', $juan->household->household_no])
        ->assertSee('No interventions recorded yet.')
        ->assertSee('CLAIM VERIFICATION')->assertSee('Process Claim');
});

it('shows each role its Figma action bar', function (string $role, string $title, string $button) { /* … */ })->with([
    [Role::ADMIN, 'CLAIM VERIFICATION', 'Process Claim'],
    [Role::AGRITECH, 'ELIGIBILITY VERIFICATION', 'Verify Eligibility'],
    [Role::ENCODER, 'EDIT MODE', 'Save Changes'],
]);

it('lets encoders correct a profile and regroups the household', /* PUT new address 'Purok 9' → saved, household changes, AuditLog 'Updated Beneficiary Profile' with record label */);

it('registers a record when its RSBSA number is entered', /* status endorsed, encoding_issue 'Missing RSBSA Number'; PUT rsbsa_number 'RSBSA-0777' → status registered, issue null */);

it('refuses a taken RSBSA number and under-age birthdates on edit', /* other record RSBSA-0231; PUT ' rsbsa-0231 ' → error rsbsa_number; PUT birthdate 10 years ago → error birthdate */);

it('only lets beneficiaries.manage holders save', fn () => /* agritech PUT → 403 */);

it('returns 404 for archived beneficiaries', /* soft-deleted record → GET 404 */);
```

- [ ] **Step 2: Run** — Expected: FAIL (route not defined).

- [ ] **Step 3: Implement** request, controller, action-bar component (props `title`, `subtitle`; slot for the white pill button/submit), and the view: banner card (full width), then a row with the 338px info card and the history card (`grid-cols-[338px_1fr] gap-[21px]`), then the action bar. Edit variant wraps info card + action bar in one `<form method="POST">` with `@method('PUT')`.

- [ ] **Step 4: Run** test file then full suite — Expected: PASS / all green. `vendor/bin/pint --dirty --format agent`; `npm run build`.

- [ ] **Step 5: Commit** `feat: add beneficiary profiles with household banner and encoder edit mode`.

---

### Task 7: Demo data and Figma fidelity check

**Files:**
- Create: `database/seeders/BeneficiarySeeder.php`
- Modify: `DatabaseSeeder.php` (call after `UserSeeder`), `tests/visual/screens.spec.js`, add Figma PNGs `329-1596`, `329-2822`, `407-1181`, `430-1461`, `430-1276`, `237-1659` (already present: `310-2`)
- Test: `tests/Feature/HouseholdTest.php` (add one seeder test) + `npm run visual`

**Interfaces:**
- Consumes: everything above.
- Produces: `BeneficiarySeeder` reproducing the Figma sample people — Juan Dela Cruz (RSBSA-0231, Poblacion, Purok 3, age 45, household with Maria Dela Cruz and Ana Dela Cruz), Maria Santos (RSBSA-0198, Samoki), Pedro Reyes (pending, Bontoc Ili, household of 2 with Lorna Reyes), Rosa Mendez (RSBSA-0187, Samoki), Carlos Ibanez (RSBSA-0099, Bontoc Ili, household of 2 with Teresa Ibanez), Liza Domingo (RSBSA-0304, Poblacion), Ana Gomez (pending, Poblacion), Federico Wasing (excel_import, endorsed, no number, issue "Missing RSBSA Number", created Jul 20, 2026), Estrella Domogen (manual, returned, issue "Awaiting Barangay Confirmation", created Jul 19, 2026). Idempotent (`updateOrCreate` on name + birthdate).

- [ ] **Step 1: Write the failing test** — `it('seeds the Figma sample beneficiaries')`: run `BarangaySeeder`, `RolePermissionSeeder`, `UserSeeder`, `BeneficiarySeeder` twice; assert 13 beneficiaries, Juan's `householdSize()` is 3, Pedro's and Carlos's are 2, two have `encoding_issue`.

- [ ] **Step 2: Run** — Expected: FAIL (class missing).

- [ ] **Step 3: Implement** the seeder; register it in `DatabaseSeeder`.

- [ ] **Step 4: Run** test, full suite; `php artisan db:seed --class=BeneficiarySeeder --no-interaction` on dev. Export the six Figma frames with the Figma MCP `get_screenshot` (fileKey `ZYDqjMYN1h4OBNbQUpadrk`, `maxDimension: 1820`) into `tests/visual/figma/<id>.png`; add SCREENS entries (`/rsbsa/register` as Admin_01 and Encoder_03, `/beneficiaries/{juan}` as each role, `/beneficiaries` as Encoder_03, `/dashboard` as Encoder_03); `npm run visual`; review side-by-side crops of each card region and fix structural mismatches (>2px offsets, wrong sizes/colors, wrapped text). Expected: harness passes; remaining diff is only Phase 4 columns ("—" vs Figma chips) and font anti-aliasing.

- [ ] **Step 5: Commit** `test: seed Figma sample beneficiaries and add Phase 3 screens to visual harness`.

---

## Self-Review Notes

- **Spec coverage:** §4 barangays/households/beneficiaries → T1; §5.8 RSBSA workflow → T2–T3; §5.5 household auto-grouping + banner → T1, T6 (claim-blocking half is Phase 4); §5.13 counters Total Beneficiaries / Pending RSBSA / Encoded This Month / Records to be Updated → T4; §5.14 global search + Filter → T4; DE encoding queue → T4; DE list → T5; 3 profile variants → T6; seeded Figma data + fidelity → T7. Audit rows for every change → T1 (trait), T3 (workflow labels), T6.
- **Deviations from spec (rulings):** added status `validated` between `pending_validation` and `endorsed` so "OMAG validation" and "endorse to DA-RFO" (Figma subtitle) are separate, auditable steps; Figma banner's leading "Search" dropped as a typo; encoders always see the edit variant (that is the only DE profile frame).
- **Deferred to Phase 4 (explicit placeholders, tested as "—"/disabled):** Intervention + Status columns, intervention history rows, Process Claim / Verify Eligibility actions, "no other claims made yet" claim check, Intervention filter options, AT "Pending Validation" counter.
