# AGAPAY Phase 1–2 (Design System, App Shell, Auth, Roles, Users, Audit) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the Figma-exact Agapay shell (login, header, sidebar, greeting, quick actions, search bar) for all three roles, with working username login, role/permission access control, user management with Configure Roles, and an append-only audit trail.

**Architecture:** Laravel 13 monolith with Blade anonymous components styled in Tailwind v4 using tokens taken from Figma. Roles and permissions live in the DB (`roles`, `permissions`, `permission_role`); a `Gate::before` hook maps every permission slug to an ability so routes use `can:<slug>` middleware. Auditing is a model trait plus login/logout listeners writing to an append-only `audit_logs` table. Navigation, quick actions and sidebar stats are declared per role in `config/agapay.php` and filtered by permission at render time.

**Tech Stack:** PHP 8.5, Laravel 13.33, MariaDB 10.4 (XAMPP) in dev / SQLite in-memory in tests, Pest 4, Blade, Tailwind CSS 4 (Vite 8), Alpine.js 3, IBM Plex Sans (@fontsource), Playwright 1.63 (Edge channel), ImageMagick 7 (`magick compare`).

**Spec:** `docs/superpowers/specs/2026-09-28-agapay-design.md`

## Global Constraints

- Project root: `X:\Documents\SchoolProjects\AGAPAY`. Run PHP as `php` (PHP 8.5 with pdo_mysql, pdo_sqlite, zip, gd, intl, fileinfo enabled).
- Figma file key `ZYDqjMYN1h4OBNbQUpadrk`. Frames are 1820×1024. The paper decides behavior, and Figma decides the look.
- Font `IBM Plex Sans` 400/500/600/700; ink `#2f2f2f`; muted header text `#b4b4b4`; subtitle `#b5b5b5`.
- Brand radial gradient stops `#5a5de3 0` → `#7876ea .25` → `#9690f1 .5` → `#d2c4ff 1`.
- Heading gradient text `#00fff2 → #7e80ff (75.489%)`.
- Pill buttons: white fill, 1.5px border with a linear gradient from `#00fff2` (left) to `#5a5de3` (right), radius 50, height 39, bold 20px. Widths: Admin 176px (gap 14), Agri Tech 226px (gap 17), Data Encoder 200px (gap 22).
- Status chips 155×39, radius 10, 4px border: green `#a8f9b1`, red `#f9a8a9`, bold 14px.
- Stat colors in order: `#da37ff`, `#8037ff`, `#4671ff`, `#0bcaff`.
- Content area `#f9f6ff` + Figma grid SVG, 1.5px `rgba(0,0,0,.15)` border, top-left radius 20; cards white, 1.5px `rgba(0,0,0,.1)` border, radius 20.
- Header 70px; sidebar 259px; nav items 235×51 radius 15.
- Dividers: 1.5px `rgba(0,0,0,.15)`.
- Timezone `Asia/Manila`; session lifetime 30 minutes.
- Passwords hashed; audit logs never store `password` or `remember_token`; audit rows cannot be updated or deleted.
- Tests run on SQLite in-memory (`phpunit.xml`); never against the `agapay` MySQL database.
- Every commit message ends with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Brute-force / case variants at login:** `ADMIN_01` must log in as `Admin_01`, and the 6th wrong password within a minute must be refused with a "Too many login attempts" message (Task 3).
2. **Admin lockout:** an admin must not be able to deactivate themselves, change their own role, or remove `users.manage` / `roles.configure` from the Administrator role (Task 7).
3. **Account deactivated mid-session:** the very next request by that user must log them out and send them to the login page (Task 3).
4. **Tampered or stale remembered-accounts cookie:** garbage JSON, deleted or inactive user IDs must be silently ignored, never a 500 (Task 4).
5. **Audit integrity:** password hashes and remember tokens never appear in `audit_logs`, and editing/deleting a log throws (Task 5).

---

## File Structure

```
app/
  Http/Controllers/Auth/LoginController.php     login form, login, logout, switch-account
  Http/Controllers/DashboardController.php      home dashboard per role
  Http/Controllers/UserController.php           user list, create, update
  Http/Controllers/RolePermissionController.php Configure Roles matrix save
  Http/Controllers/AuditTrailController.php     audit trail list + filters
  Http/Middleware/EnsureAccountActive.php       logs out inactive / no-role users
  Http/Requests/LoginRequest.php                username auth + throttling
  Http/Requests/StoreUserRequest.php
  Http/Requests/UpdateUserRequest.php
  Models/Role.php, Permission.php, AuditLog.php
  Models/User.php                               (modified)
  Models/Concerns/Auditable.php                 model-event audit trait
  Services/AuditLogger.php                      single write path for audit rows
  Support/PermissionCatalog.php                 permission slugs, labels, role defaults
  Support/Navigation.php                        nav items, quick actions, stats per user
  Support/DashboardStats.php                    live stat values
  Support/KnownAccounts.php                     remembered-accounts cookie
  Providers/AppServiceProvider.php              (modified) Gate::before, auth listeners
config/agapay.php                               nav / quick actions / stats per role
database/migrations/2026_09_28_000001_create_roles_and_permissions_tables.php
database/migrations/2026_09_28_000002_add_agapay_columns_to_users_table.php
database/migrations/2026_09_28_000003_create_audit_logs_table.php
database/seeders/RolePermissionSeeder.php, UserSeeder.php, DatabaseSeeder.php (modified)
database/factories/UserFactory.php              (modified)
public/images/figma/                            Figma assets (icons, avatars, grid, gradients)
resources/css/app.css                           tokens + component classes
resources/js/app.js                             (modified) add font weight 600
resources/views/components/layouts/{guest,app}.blade.php
resources/views/components/app/{header,sidebar,account-menu,nav-item,stat,return-card,greeting,search-bar}.blade.php
resources/views/components/ui/{card,status-chip,pill-input,pill-select,inline-field,modal,gradient-button}.blade.php
resources/views/auth/login.blade.php
resources/views/dashboard.blade.php
resources/views/users/index.blade.php
resources/views/audit/index.blade.php
routes/web.php                                  (modified)
bootstrap/app.php                               (modified) middleware aliases, redirects
tests/Pest.php                                  (modified) RefreshDatabase + helpers
tests/Feature/{Assets,RolesPermissions,Login,AppShell,Audit,AuditTrail,UserManagement}Test.php
tests/visual/{playwright.config.js,screens.spec.js,figma/*.png}
```

---

### Task 1: Frontend foundation (tokens, Figma assets, test bootstrap)

**Files:**
- Create: `public/images/figma/**` (assets), `public/images/figma/gradients/{bar,button,login,card,logout}.svg`
- Modify: `resources/css/app.css`, `resources/js/app.js`, `tests/Pest.php`
- Delete: `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`
- Test: `tests/Feature/AssetsTest.php`

**Interfaces:**
- Produces: CSS classes `bg-grid`, `bg-grid-login`, `bg-brand-bar`, `bg-brand-button`, `bg-brand-login`, `bg-brand-card`, `bg-brand-logout`, `border-gradient`, `text-gradient-heading`, `logo-box`, `divider`; Tailwind colors `ink`, `muted`, `subtle`, `canvas`, `logo`, `brand`, `brand-soft`, `cyan`, `ok`, `bad`, `field`, `arrow`, `stat-1..4`, `danger`; asset URLs `/images/figma/icons/{home,report,newly-registered,disaster,user-mgmt,audit,validation,profile,inventory,task,arrow-down,arrow-up,search,plus,ring}.svg`, `/images/figma/avatars/{admin,agritech,encoder}.png`, `/images/figma/grid-bg.svg`.

- [ ] **Step 1: Write the failing asset test**

`tests/Feature/AssetsTest.php`:
```php
<?php

it('ships every Figma asset the shell references', function (string $path) {
    expect(public_path($path))->toBeFile()
        ->and(filesize(public_path($path)))->toBeGreaterThan(100);
})->with([
    'images/figma/grid-bg.svg',
    'images/figma/icons/home.svg',
    'images/figma/icons/report.svg',
    'images/figma/icons/newly-registered.svg',
    'images/figma/icons/disaster.svg',
    'images/figma/icons/user-mgmt.svg',
    'images/figma/icons/audit.svg',
    'images/figma/icons/validation.svg',
    'images/figma/icons/profile.svg',
    'images/figma/icons/inventory.svg',
    'images/figma/icons/task.svg',
    'images/figma/icons/arrow-down.svg',
    'images/figma/icons/arrow-up.svg',
    'images/figma/icons/search.svg',
    'images/figma/icons/plus.svg',
    'images/figma/icons/ring.svg',
    'images/figma/avatars/admin.png',
    'images/figma/avatars/agritech.png',
    'images/figma/avatars/encoder.png',
    'images/figma/gradients/bar.svg',
    'images/figma/gradients/button.svg',
    'images/figma/gradients/login.svg',
    'images/figma/gradients/card.svg',
    'images/figma/gradients/logout.svg',
]);
```

Replace `tests/Pest.php` with:
```php
<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Seed the three roles and their default permissions. */
function seedRoles(): void
{
    test()->seed(RolePermissionSeeder::class);
}

/** Create a user holding the given role slug (roles must be seeded). */
function userWithRole(?string $slug, array $attributes = []): User
{
    return User::factory()->create([
        'role_id' => $slug ? Role::where('slug', $slug)->value('id') : null,
        ...$attributes,
    ]);
}
```

Delete the example tests:
```bash
rm tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/AssetsTest.php`
Expected: FAIL. `RolePermissionSeeder` isn't referenced yet, so Pest.php loads, but every dataset row fails with "is not a file".

- [ ] **Step 3: Copy the Figma assets into the project**

The assets were downloaded from the Figma MCP on 2026-09-28 into the session scratchpad. Copy them (the source dir is `C:/Users/typho/AppData/Local/Temp/claude/X--Documents-SchoolProjects-AGAPAY/ed7520ed-a89b-4649-b955-0eb29ed8e156/scratchpad/figma`):

```bash
S="C:/Users/typho/AppData/Local/Temp/claude/X--Documents-SchoolProjects-AGAPAY/ed7520ed-a89b-4649-b955-0eb29ed8e156/scratchpad/figma"
D=public/images/figma
mkdir -p $D/icons $D/avatars $D/gradients
cp "$S/grid-bg.svg" $D/grid-bg.svg
cp "$S/icon-home.svg" $D/icons/home.svg
cp "$S/icon-report.svg" $D/icons/report.svg
cp "$S/icon-newly-registered.svg" $D/icons/newly-registered.svg
cp "$S/icon-disaster.svg" $D/icons/disaster.svg
cp "$S/icon-user-mgmt.svg" $D/icons/user-mgmt.svg
cp "$S/icon-audit.svg" $D/icons/audit.svg
cp "$S/icon-validation.svg" $D/icons/validation.svg
cp "$S/icon-profile.svg" $D/icons/profile.svg
cp "$S/icon-inventory.svg" $D/icons/inventory.svg
cp "$S/icon-task.svg" $D/icons/task.svg
cp "$S/icon-arrow-down.svg" $D/icons/arrow-down.svg
cp "$S/icon-arrow-up.svg" $D/icons/arrow-up.svg
cp "$S/icon-search-b.svg" $D/icons/search.svg
cp "$S/icon-plus.svg" $D/icons/plus.svg
cp "$S/ring-agritech.svg" $D/icons/ring.svg
cp "$S/avatar-admin.png" $D/avatars/admin.png
cp "$S/avatar-agritech.png" $D/avatars/agritech.png
cp "$S/avatar-encoder.png" $D/avatars/encoder.png
```

If the scratchpad is gone, re-download with the Figma MCP (`get_design_context` on frames `310:2`, `310:1049`, `329:700`, `237:1470`, `237:1659`) and save each returned asset URL under the same names. The mapping is: `imgGroup4`→grid-bg, `imgMaterialSymbolsHomeRounded`→home, `imgBxsReport`→report, `imgVector1` (310:2)→newly-registered, `imgVector` (310:2)→disaster, `imgGroup2`→user-mgmt, `imgGroup3`→audit, `imgStreamlineUserCheckValidateSolid`→validation, `imgIxUserProfileFilled`→profile, `imgIcBaselineInventory`→inventory, `imgGroup` (card icon)→task, `imgEpArrowDown`→arrow-down, `imgAccountButtonBack`→arrow-up, `tdesign:search` vector→search, `imgTypcnPlus`→plus, `imgVector` (310:1049)→ring, `imgImage1`/`imgIcons8CircledMaleUserSkinType3961`/`imgIcons8Profile961`→avatars.

- [ ] **Step 4: Write the five brand-gradient SVGs (exact Figma gradients)**

Each file stretches (`preserveAspectRatio='none'`) so the gradient keeps Figma's proportions at any element size.

`public/images/figma/gradients/bar.svg` (search bar, 1488×59):
```xml
<svg viewBox="0 0 1488 59" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><rect x="0" y="0" height="100%" width="100%" fill="url(#grad)"/><defs><radialGradient id="grad" gradientUnits="userSpaceOnUse" cx="0" cy="0" r="10" gradientTransform="matrix(149.16 4.0319 -393.17 5.9142 -35.769 16.012)"><stop stop-color="rgba(90,93,227,1)" offset="0"/><stop stop-color="rgba(120,118,234,1)" offset="0.25"/><stop stop-color="rgba(150,144,241,1)" offset="0.5"/><stop stop-color="rgba(210,196,255,1)" offset="1"/></radialGradient></defs></svg>
```

`public/images/figma/gradients/button.svg` (modal save button, 652×48):
```xml
<svg viewBox="0 0 652 48" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><rect x="0" y="0" height="100%" width="100%" fill="url(#grad)"/><defs><radialGradient id="grad" gradientUnits="userSpaceOnUse" cx="0" cy="0" r="10" gradientTransform="matrix(65.357 3.2802 -172.28 4.8115 -15.673 13.027)"><stop stop-color="rgba(90,93,227,1)" offset="0"/><stop stop-color="rgba(120,118,234,1)" offset="0.25"/><stop stop-color="rgba(150,144,241,1)" offset="0.5"/><stop stop-color="rgba(210,196,255,1)" offset="1"/></radialGradient></defs></svg>
```

`public/images/figma/gradients/login.svg` (login button, 320×44):
```xml
<svg viewBox="0 0 320 44" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><rect x="0" y="0" height="100%" width="100%" fill="url(#grad)"/><defs><radialGradient id="grad" gradientUnits="userSpaceOnUse" cx="0" cy="0" r="10" gradientTransform="matrix(32.077 3.0068 -84.553 4.4106 -7.6923 11.941)"><stop stop-color="rgba(90,93,227,1)" offset="0"/><stop stop-color="rgba(120,118,234,1)" offset="0.25"/><stop stop-color="rgba(150,144,241,1)" offset="0.5"/><stop stop-color="rgba(210,196,255,1)" offset="1"/></radialGradient></defs></svg>
```

`public/images/figma/gradients/card.svg` (Return-to-login card, 203×129):
```xml
<svg viewBox="0 0 203 129" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><rect x="0" y="0" height="100%" width="100%" fill="url(#grad)"/><defs><radialGradient id="grad" gradientUnits="userSpaceOnUse" cx="0" cy="0" r="10" gradientTransform="matrix(17.261 10.984 -50.204 3.7654 26 13.326)"><stop stop-color="rgba(90,93,227,1)" offset="0"/><stop stop-color="rgba(120,118,234,1)" offset="0.25"/><stop stop-color="rgba(150,144,241,1)" offset="0.5"/><stop stop-color="rgba(210,196,255,1)" offset="1"/></radialGradient></defs></svg>
```

`public/images/figma/gradients/logout.svg` (account-menu Log out, 215×39):
```xml
<svg viewBox="0 0 215 39" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><rect x="0" y="0" height="100%" width="100%" fill="url(#grad)"/><defs><radialGradient id="grad" gradientUnits="userSpaceOnUse" cx="0" cy="0" r="10" gradientTransform="matrix(20.814 5.3542 -60.582 -3.2725 0.86695 19.831)"><stop stop-color="rgba(90,93,227,1)" offset="0"/><stop stop-color="rgba(120,118,234,1)" offset="0.25"/><stop stop-color="rgba(150,144,241,1)" offset="0.5"/><stop stop-color="rgba(210,196,255,1)" offset="1"/></radialGradient></defs></svg>
```

- [ ] **Step 5: Write the design tokens and component classes**

Replace `resources/css/app.css`:
```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';

@theme {
    --font-sans: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif;

    --color-ink: #2f2f2f;
    --color-muted: #b4b4b4;
    --color-subtle: #b5b5b5;
    --color-canvas: #f9f6ff;
    --color-logo: #edefea;
    --color-brand: #5a5de3;
    --color-brand-soft: #7e80ff;
    --color-cyan: #00fff2;
    --color-ok: #a8f9b1;
    --color-bad: #f9a8a9;
    --color-field: #e2e2e2;
    --color-arrow: #868686;
    --color-danger: #d64545;
    --color-stat-1: #da37ff;
    --color-stat-2: #8037ff;
    --color-stat-3: #4671ff;
    --color-stat-4: #0bcaff;
}

@layer components {
    /* Figma "Group 4" grid, placed at (-278, -298) inside the content container. */
    .bg-grid {
        background-color: var(--color-canvas);
        background-image: url('/images/figma/grid-bg.svg');
        background-repeat: no-repeat;
        background-position: -278px -298px;
        background-size: 2135.5px 1529px;
    }

    /* Login frame: grid container sits at (-66, -121) on the page. */
    .bg-grid-login {
        background-color: var(--color-canvas);
        background-image: url('/images/figma/grid-bg.svg');
        background-repeat: no-repeat;
        background-position: -344px -419px;
        background-size: 2135.5px 1529px;
    }

    .bg-brand-bar { background: url('/images/figma/gradients/bar.svg') 0 0 / 100% 100% no-repeat; }
    .bg-brand-button { background: url('/images/figma/gradients/button.svg') 0 0 / 100% 100% no-repeat; }
    .bg-brand-login { background: url('/images/figma/gradients/login.svg') 0 0 / 100% 100% no-repeat; }
    .bg-brand-card { background: url('/images/figma/gradients/card.svg') 0 0 / 100% 100% no-repeat; }
    .bg-brand-logout { background: url('/images/figma/gradients/logout.svg') 0 0 / 100% 100% no-repeat; }

    /* Pill buttons and login inputs: cyan -> brand linear stroke (sampled from Figma render). */
    .border-gradient {
        border: 1.5px solid transparent;
        background:
            linear-gradient(#fff, #fff) padding-box,
            linear-gradient(90deg, #00fff2 0%, #5a5de3 100%) border-box;
    }

    .text-gradient-heading {
        background-image: linear-gradient(90deg, #00fff2 0%, #7e80ff 75.489%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    /* Placeholder logo square: brand gradient stroke (sampled), fill set by --logo-fill. */
    .logo-box {
        border-style: solid;
        border-color: transparent;
        background:
            linear-gradient(var(--logo-fill, #edefea), var(--logo-fill, #edefea)) padding-box,
            linear-gradient(170deg, #7473e9 10%, #afa6f7 80%, #d2c4ff 100%) border-box;
    }

    .divider {
        border-top: 1.5px solid rgb(0 0 0 / 0.15);
    }
}

[x-cloak] { display: none !important; }
```

Replace `resources/js/app.js` (adds weight 600, used by login labels):
```js
import '@fontsource/ibm-plex-sans/400.css';
import '@fontsource/ibm-plex-sans/500.css';
import '@fontsource/ibm-plex-sans/600.css';
import '@fontsource/ibm-plex-sans/700.css';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
```

- [ ] **Step 6: Run tests and build**

Run: `./vendor/bin/pest tests/Feature/AssetsTest.php && npm run build`
Expected: 24 passed; Vite prints `✓ built` with no CSS errors.

- [ ] **Step 7: Commit**

```bash
git add public/images/figma resources/css/app.css resources/js/app.js tests
git commit -m "feat: add Figma design tokens, assets and gradient backgrounds

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Roles, permissions and the user model

**Files:**
- Create: `database/migrations/2026_09_28_000001_create_roles_and_permissions_tables.php`, `database/migrations/2026_09_28_000002_add_agapay_columns_to_users_table.php`, `app/Models/Role.php`, `app/Models/Permission.php`, `app/Support/PermissionCatalog.php`, `database/seeders/RolePermissionSeeder.php`, `database/seeders/UserSeeder.php`, `config/agapay.php` (seed password only for now)
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `app/Providers/AppServiceProvider.php`, `.env.example`
- Test: `tests/Feature/RolesPermissionsTest.php`

**Interfaces:**
- Produces:
  - `Role::ADMIN = 'administrator'`, `Role::AGRITECH = 'agricultural_technologist'`, `Role::ENCODER = 'data_encoder'`; `Role` columns `slug, name, short_name, greeting`; `Role::permissions(): BelongsToMany`.
  - `Permission` columns `slug, label, group`.
  - `PermissionCatalog::PERMISSIONS` (`array<string, array{0:string,1:string}>` slug ⇒ [label, group]), `PermissionCatalog::LOCKED_FOR_ADMIN` (`list<string>`), `PermissionCatalog::defaults(): array<string, list<string>>`.
  - `User` columns `name, username, email (nullable), password, role_id (nullable), status ('active'|'inactive'), avatar (nullable 'admin'|'agritech'|'encoder'), last_login_at`; methods `role(): BelongsTo`, `isActive(): bool`, `canSignIn(): bool`, `hasPermission(string $slug): bool`, `permissionSlugs(): Collection`, `roleName(): string`, `roleShortName(): string`, `greetingName(): string`, `avatarUrl(): string`; constants `User::STATUS_ACTIVE`, `User::STATUS_INACTIVE`.
  - Gate: `$user->can('<permission slug>')`.
  - `UserFactory` default state: unique `username`, `status = active`, `role_id = null`.
  - Seeded accounts: `Admin_01`, `Agritech_02`, `Encoder_03` (active) and `Encoder_04` (inactive, no role), all using `config('agapay.seed_password')`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/RolesPermissionsTest.php`:
```php
<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\UserSeeder;

beforeEach(fn () => seedRoles());

it('seeds three roles and every catalog permission', function () {
    expect(Role::pluck('slug')->all())
        ->toEqualCanonicalizing([Role::ADMIN, Role::AGRITECH, Role::ENCODER])
        ->and(Permission::count())->toBe(count(PermissionCatalog::PERMISSIONS));
});

it('is idempotent', function () {
    seedRoles();

    expect(Role::count())->toBe(3)
        ->and(Permission::count())->toBe(count(PermissionCatalog::PERMISSIONS));
});

it('gives each role its default permissions', function () {
    $admin = userWithRole(Role::ADMIN);
    $agritech = userWithRole(Role::AGRITECH);
    $encoder = userWithRole(Role::ENCODER);

    expect($admin->hasPermission('users.manage'))->toBeTrue()
        ->and($admin->hasPermission('audit.view'))->toBeTrue()
        ->and($agritech->hasPermission('interventions.validate'))->toBeTrue()
        ->and($agritech->hasPermission('users.manage'))->toBeFalse()
        ->and($encoder->hasPermission('import.run'))->toBeTrue()
        ->and($encoder->hasPermission('audit.view'))->toBeFalse();
});

it('denies every permission to inactive and role-less users', function () {
    $inactive = userWithRole(Role::ADMIN, ['status' => User::STATUS_INACTIVE]);
    $noRole = userWithRole(null);

    expect($inactive->hasPermission('dashboard.view'))->toBeFalse()
        ->and($inactive->canSignIn())->toBeFalse()
        ->and($noRole->hasPermission('dashboard.view'))->toBeFalse()
        ->and($noRole->roleName())->toBe('No Role');
});

it('exposes permissions as gate abilities', function () {
    $admin = userWithRole(Role::ADMIN);
    $encoder = userWithRole(Role::ENCODER);

    expect($admin->can('users.manage'))->toBeTrue()
        ->and($encoder->can('users.manage'))->toBeFalse()
        ->and($admin->can('not.a.permission'))->toBeFalse();
});

it('labels users by role', function () {
    $agritech = userWithRole(Role::AGRITECH);

    expect($agritech->roleName())->toBe('Agricultural Technologist')
        ->and($agritech->roleShortName())->toBe('Agricultural Tech')
        ->and($agritech->greetingName())->toBe('Agritech')
        ->and($agritech->avatarUrl())->toEndWith('/images/figma/avatars/agritech.png');
});

it('seeds the four Figma accounts', function () {
    $this->seed(UserSeeder::class);

    expect(User::pluck('username')->all())
        ->toEqualCanonicalizing(['Admin_01', 'Agritech_02', 'Encoder_03', 'Encoder_04'])
        ->and(User::firstWhere('username', 'Encoder_04'))
        ->status->toBe(User::STATUS_INACTIVE)
        ->role_id->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/RolesPermissionsTest.php`
Expected: FAIL with `Class "Database\Seeders\RolePermissionSeeder" not found`.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_09_28_000001_create_roles_and_permissions_tables.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name');
            $table->string('greeting');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('group');
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
```

`database/migrations/2026_09_28_000002_add_agapay_columns_to_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->string('status', 16)->default('active')->after('role_id');
            $table->string('avatar', 16)->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['username', 'status', 'avatar', 'last_login_at']);
        });
    }
};
```

- [ ] **Step 4: Write the models and catalog**

`app/Support/PermissionCatalog.php`:
```php
<?php

namespace App\Support;

use App\Models\Role;

final class PermissionCatalog
{
    /** slug => [label, group] */
    public const PERMISSIONS = [
        'dashboard.view' => ['View home dashboard', 'General'],
        'users.manage' => ['Manage user accounts', 'Administration'],
        'roles.configure' => ['Configure role permissions', 'Administration'],
        'audit.view' => ['View audit trail', 'Administration'],
        'beneficiaries.view' => ['View beneficiary profiles', 'Beneficiaries'],
        'beneficiaries.manage' => ['Add and edit beneficiary profiles', 'Beneficiaries'],
        'rsbsa.register' => ['Encode RSBSA registrations', 'Beneficiaries'],
        'rsbsa.process' => ['Validate and endorse RSBSA registrations', 'Beneficiaries'],
        'interventions.view' => ['View DA and LGU intervention lists', 'Interventions'],
        'interventions.validate' => ['Validate beneficiary eligibility', 'Interventions'],
        'interventions.claim' => ['Process intervention claims', 'Interventions'],
        'interventions.archive' => ['Archive and restore intervention records', 'Interventions'],
        'intervention_records.manage' => ['Encode intervention records', 'Interventions'],
        'inventory.view' => ['View inventory', 'Inventory'],
        'inventory.manage' => ['Record stock movements', 'Inventory'],
        'damage.view' => ['View agricultural damage reports', 'Damage Recording'],
        'damage.create' => ['File agricultural damage reports', 'Damage Recording'],
        'damage.validate' => ['Validate agricultural damage reports', 'Damage Recording'],
        'import.run' => ['Import Excel files', 'Data'],
        'export.run' => ['Export beneficiary lists', 'Data'],
        'reports.generate' => ['Generate reports', 'Reports'],
    ];

    /** Permissions the Administrator role can never lose (prevents lock-out). */
    public const LOCKED_FOR_ADMIN = ['users.manage', 'roles.configure'];

    /** @return array<string, list<string>> role slug => permission slugs */
    public static function defaults(): array
    {
        return [
            Role::ADMIN => array_keys(self::PERMISSIONS),
            Role::AGRITECH => [
                'dashboard.view', 'beneficiaries.view', 'rsbsa.process',
                'interventions.view', 'interventions.validate', 'interventions.claim',
                'damage.view', 'damage.create', 'damage.validate', 'reports.generate',
            ],
            Role::ENCODER => [
                'dashboard.view', 'beneficiaries.view', 'beneficiaries.manage', 'rsbsa.register',
                'intervention_records.manage', 'interventions.claim',
                'inventory.view', 'inventory.manage', 'import.run',
                'damage.view', 'damage.create', 'reports.generate',
            ],
        ];
    }
}
```

`app/Models/Role.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'short_name', 'greeting'])]
class Role extends Model
{
    public const ADMIN = 'administrator';
    public const AGRITECH = 'agricultural_technologist';
    public const ENCODER = 'data_encoder';

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
```

`app/Models/Permission.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'label', 'group'])]
class Permission extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
```

Replace `app/Models/User.php`:
```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'username', 'email', 'password', 'role_id', 'status', 'avatar', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    private ?Collection $permissionSlugCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Active accounts with an assigned role are the only ones allowed in. */
    public function canSignIn(): bool
    {
        return $this->isActive() && $this->role_id !== null;
    }

    public function hasPermission(string $slug): bool
    {
        return $this->canSignIn() && $this->permissionSlugs()->contains($slug);
    }

    /** @return Collection<int, string> */
    public function permissionSlugs(): Collection
    {
        return $this->permissionSlugCache ??= ($this->role?->permissions->pluck('slug') ?? collect());
    }

    public function roleName(): string
    {
        return $this->role?->name ?? 'No Role';
    }

    public function roleShortName(): string
    {
        return $this->role?->short_name ?? 'No Role';
    }

    public function greetingName(): string
    {
        return $this->role?->greeting ?? $this->username;
    }

    public function avatarUrl(): string
    {
        $key = $this->avatar ?? match ($this->role?->slug) {
            Role::AGRITECH => 'agritech',
            Role::ENCODER => 'encoder',
            default => 'admin',
        };

        return asset("images/figma/avatars/{$key}.png");
    }
}
```

- [ ] **Step 5: Register the permission gate**

Replace `app/Providers/AppServiceProvider.php`:
```php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Every permission slug is a gate ability: can:users.manage, @can('audit.view'), ...
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ? true : null);
    }
}
```

- [ ] **Step 6: Factory, seeders, config**

Replace `database/factories/UserFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'user_'.fake()->unique()->numerify('####'),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => null,
            'status' => User::STATUS_ACTIVE,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => User::STATUS_INACTIVE]);
    }
}
```

`config/agapay.php`:
```php
<?php

return [
    // Development/demo password for seeded accounts. Change in .env for any real deployment.
    'seed_password' => env('AGAPAY_SEED_PASSWORD', 'Agapay@2026'),
];
```

Append to `.env.example` and `.env`:
```
AGAPAY_SEED_PASSWORD=Agapay@2026
```

`database/seeders/RolePermissionSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::PERMISSIONS as $slug => [$label, $group]) {
            Permission::updateOrCreate(['slug' => $slug], ['label' => $label, 'group' => $group]);
        }

        $roles = [
            Role::ADMIN => ['Administrator', 'Administrator', 'Admin'],
            Role::AGRITECH => ['Agricultural Technologist', 'Agricultural Tech', 'Agritech'],
            Role::ENCODER => ['Data Encoder', 'Data Encoder', 'Encoder'],
        ];

        foreach ($roles as $slug => [$name, $short, $greeting]) {
            $role = Role::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'short_name' => $short, 'greeting' => $greeting,
            ]);

            $role->permissions()->sync(
                Permission::whereIn('slug', PermissionCatalog::defaults()[$slug])->pluck('id')
            );
        }
    }
}
```

`database/seeders/UserSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Admin_01', 'Municipal Agriculturist', Role::ADMIN, User::STATUS_ACTIVE, 'admin'],
            ['Agritech_02', 'Agricultural Technologist', Role::AGRITECH, User::STATUS_ACTIVE, 'agritech'],
            ['Encoder_03', 'Data Encoder', Role::ENCODER, User::STATUS_ACTIVE, 'encoder'],
            ['Encoder_04', 'Data Encoder (Unassigned)', null, User::STATUS_INACTIVE, 'encoder'],
        ];

        foreach ($accounts as [$username, $name, $role, $status, $avatar]) {
            User::updateOrCreate(['username' => $username], [
                'name' => $name,
                'password' => config('agapay.seed_password'),
                'role_id' => $role ? Role::where('slug', $role)->value('id') : null,
                'status' => $status,
                'avatar' => $avatar,
            ]);
        }
    }
}
```

Replace `database/seeders/DatabaseSeeder.php`:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Seed data is not user activity, so it stays out of the audit trail.
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
        ]);
    }
}
```

- [ ] **Step 7: Run the tests and migrate the dev database**

Run: `./vendor/bin/pest tests/Feature/RolesPermissionsTest.php`
Expected: 7 passed.

Run: `php artisan migrate:fresh --seed --no-interaction`
Expected: all migrations `DONE`, then `Seeding database.` with no errors.

- [ ] **Step 8: Commit**

```bash
git add app config database tests .env.example
git commit -m "feat: add roles, permissions and seeded OMAG accounts

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Login, logout, switch account and active-account enforcement

**Files:**
- Create: `app/Http/Requests/LoginRequest.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Middleware/EnsureAccountActive.php`, `app/Support/KnownAccounts.php`, `resources/views/components/layouts/guest.blade.php`, `resources/views/auth/login.blade.php`, `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php` (minimal; Task 4 replaces it)
- Modify: `routes/web.php`, `bootstrap/app.php`
- Test: `tests/Feature/LoginTest.php`

**Interfaces:**
- Consumes: `User::canSignIn()`, `User::isActive()`, `userWithRole()`, `seedRoles()`.
- Produces:
  - Routes: `GET /login` `login`, `POST /login` `login.store`, `POST /logout` `logout`, `POST /switch-account` `account.switch` (field `username`, optional), `GET /` → redirect to `dashboard`, `GET /dashboard` `dashboard` (middleware `auth`, `active`, `can:dashboard.view`).
  - Middleware alias `active` → `EnsureAccountActive`.
  - `KnownAccounts::COOKIE = 'agapay_accounts'`; `KnownAccounts::remember(User $user, Request $request): void`; `KnownAccounts::ids(Request $request): list<int>`; `KnownAccounts::others(Request $request, User $current): Collection<int, User>`.
  - Guest layout `<x-layouts.guest :title="...">`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/LoginTest.php`:
```php
<?php

use App\Models\Role;
use App\Models\User;
use App\Support\KnownAccounts;

beforeEach(function () {
    seedRoles();
    $this->admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01', 'password' => 'secret-pass']);
});

it('shows the Figma login screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSeeInOrder(['Agapay', 'Username', 'Password', 'LOGIN']);
});

it('prefills the username from the account switcher', function () {
    $this->get('/login?username=Agritech_02')->assertSee('value="Agritech_02"', false);
});

it('logs in by username, ignoring letter case', function () {
    $this->post('/login', ['username' => 'ADMIN_01', 'password' => 'secret-pass'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->admin);
    expect($this->admin->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password', function () {
    $this->from('/login')
        ->post('/login', ['username' => 'Admin_01', 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['username' => 'These credentials do not match our records.']);

    $this->assertGuest();
});

it('blocks inactive accounts', function () {
    userWithRole(Role::ENCODER, ['username' => 'Encoder_09', 'password' => 'secret-pass', 'status' => User::STATUS_INACTIVE]);

    $this->post('/login', ['username' => 'Encoder_09', 'password' => 'secret-pass'])
        ->assertSessionHasErrors(['username' => 'This account is inactive. Contact the administrator.']);

    $this->assertGuest();
});

it('blocks accounts without a role', function () {
    userWithRole(null, ['username' => 'Encoder_04', 'password' => 'secret-pass']);

    $this->post('/login', ['username' => 'Encoder_04', 'password' => 'secret-pass'])
        ->assertSessionHasErrors(['username' => 'This account has no role assigned. Contact the administrator.']);

    $this->assertGuest();
});

it('throttles after five failed attempts', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['username' => 'Admin_01', 'password' => 'wrong']);
    }

    $this->post('/login', ['username' => 'Admin_01', 'password' => 'secret-pass'])
        ->assertSessionHasErrors('username');

    expect(session('errors')->first('username'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
});

it('remembers the account on this device', function () {
    $this->post('/login', ['username' => 'Admin_01', 'password' => 'secret-pass'])
        ->assertCookie(KnownAccounts::COOKIE, json_encode([$this->admin->id]));
});

it('logs out', function () {
    $this->actingAs($this->admin)->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('switches account by returning to login with the username filled in', function () {
    $this->actingAs($this->admin)
        ->post('/switch-account', ['username' => 'Agritech_02'])
        ->assertRedirect(route('login', ['username' => 'Agritech_02']));

    $this->assertGuest();
});

it('kicks out a user deactivated mid-session', function () {
    $this->actingAs($this->admin);
    $this->admin->update(['status' => User::STATUS_INACTIVE]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('sends guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/')->assertRedirect(route('dashboard'));
});

it('sends signed-in users away from the login page', function () {
    $this->actingAs($this->admin)->get('/login')->assertRedirect(route('dashboard'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/LoginTest.php`
Expected: FAIL. `/login` returns 404.

- [ ] **Step 3: Write KnownAccounts, the request, controller and middleware**

`app/Support/KnownAccounts.php`:
```php
<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

/** Accounts that have signed in on this browser, shown in the sidebar account switcher. */
final class KnownAccounts
{
    public const COOKIE = 'agapay_accounts';

    private const LIMIT = 5;

    private const MINUTES = 60 * 24 * 30;

    public static function remember(User $user, Request $request): void
    {
        $ids = collect([$user->id, ...self::ids($request)])->unique()->take(self::LIMIT)->values()->all();

        Cookie::queue(self::COOKIE, json_encode($ids), self::MINUTES);
    }

    /** @return list<int> */
    public static function ids(Request $request): array
    {
        $decoded = json_decode((string) $request->cookie(self::COOKIE), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($id) => is_int($id) && $id > 0));
    }

    /** @return Collection<int, User> */
    public static function others(Request $request, User $current): Collection
    {
        $ids = array_values(array_diff(self::ids($request), [$current->id]));

        if ($ids === []) {
            return collect();
        }

        return User::with('role')
            ->whereIn('id', $ids)
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotNull('role_id')
            ->get()
            ->sortBy(fn (User $user) => array_search($user->id, $ids, true))
            ->values();
    }
}
```

`app/Http/Requests/LoginRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /** @throws ValidationException */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::whereRaw('LOWER(username) = ?', [Str::lower((string) $this->input('username'))])->first();

        if (! $user || ! Hash::check((string) $this->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->canSignIn()) {
            throw ValidationException::withMessages([
                'username' => $user->isActive()
                    ? 'This account has no role assigned. Contact the administrator.'
                    : 'This account is inactive. Contact the administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Auth::login($user);

        return $user;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('username')).'|'.$this->ip());
    }
}
```

`app/Http/Controllers/Auth/LoginController.php`:
```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Support\KnownAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.login', ['username' => $request->query('username')]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        KnownAccounts::remember($user, $request);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signOut($request);

        return redirect()->route('login');
    }

    /** "Return to login" card and account-menu entries: sign out, prefill the chosen username. */
    public function switch(Request $request): RedirectResponse
    {
        $username = $request->string('username')->limit(255, '')->toString();
        $this->signOut($request);

        return redirect()->route('login', array_filter(['username' => $username]));
    }

    private function signOut(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
```

`app/Http/Middleware/EnsureAccountActive.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out users deactivated (or stripped of their role) while logged in. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->canSignIn()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => 'Your account is no longer active. Contact the administrator.']);
        }

        return $next($request);
    }
}
```

`app/Http/Controllers/DashboardController.php` (Task 4 extends it):
```php
<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
```

`resources/views/dashboard.blade.php` (temporary; Task 4 replaces):
```blade
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Agapay</title></head>
<body>
    <p>{{ auth()->user()->username }}</p>
    <form method="POST" action="{{ route('logout') }}">@csrf<button>Log out</button></form>
</body></html>
```

- [ ] **Step 4: Wire routes and middleware**

Replace `routes/web.php`:
```php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/switch-account', [LoginController::class, 'switch'])->name('account.switch');
});

// Guests go / -> /dashboard -> /login; signed-in users land on their dashboard.
Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
});
```

In `bootstrap/app.php` replace the `withMiddleware` closure body:
```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccountActive::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
```

- [ ] **Step 5: Write the guest layout and login view (frame 482:339)**

`resources/views/components/layouts/guest.blade.php`:
```blade
@props(['title' => 'Agapay'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-grid-login min-h-screen font-sans text-ink antialiased">
    {{ $slot }}
</body>
</html>
```

`resources/views/auth/login.blade.php` (Figma values: logo 84px/5px stroke, wordmark 64px bold with 14px gap, card 400×292 radius 30 `#b4b4b4` 1px border, labels 12px semibold, inputs 320×40 radius 100, LOGIN button 320×44):
```blade
<x-layouts.guest title="Login · Agapay">
    <main class="flex min-h-screen items-center justify-center">
        <div class="flex flex-col items-center">
            <div class="flex items-center gap-[14px]">
                <span class="logo-box block size-[84px] border-[5px]" style="--logo-fill: transparent"></span>
                <h1 class="text-[64px] font-bold leading-[83px]">Agapay</h1>
            </div>

            <form method="POST" action="{{ route('login.store') }}"
                  class="mt-[14px] flex w-[400px] flex-col items-center rounded-[30px] border border-muted bg-white px-[40px] pt-[30px] pb-[38px]">
                @csrf

                <label for="username" class="h-[20px] text-[12px] font-semibold leading-[20px]">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required autofocus
                       value="{{ old('username', $username) }}"
                       class="border-gradient mt-[2px] h-[40px] w-[320px] rounded-[100px] px-[18px] text-[14px] font-medium outline-none">

                <label for="password" class="mt-[18px] h-[20px] text-[12px] font-semibold leading-[20px]">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="border-gradient mt-[2px] h-[40px] w-[320px] rounded-[100px] px-[18px] text-[14px] font-medium outline-none">

                <p class="mt-[9px] h-[20px] text-center text-[12px] font-semibold leading-[20px] text-danger" role="alert">
                    {{ $errors->first('username') ?: $errors->first('password') }}
                </p>

                <button type="submit"
                        class="bg-brand-login mt-[9px] h-[44px] w-[320px] rounded-[100px] text-[14px] font-bold text-white">
                    LOGIN
                </button>
            </form>
        </div>
    </main>
</x-layouts.guest>
```

- [ ] **Step 6: Run the tests**

Run: `./vendor/bin/pest tests/Feature/LoginTest.php`
Expected: 13 passed.

- [ ] **Step 7: Commit**

```bash
git add app bootstrap routes resources/views tests
git commit -m "feat: add username login, logout, account switch and inactive-account guard

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: App shell (header, sidebar, account menu, greeting, quick actions, search bar, dashboard)

**Files:**
- Create: `app/Support/Navigation.php`, `app/Support/DashboardStats.php`, `resources/views/components/layouts/app.blade.php`, `resources/views/components/app/{header,sidebar,account-menu,nav-item,stat,return-card,greeting,search-bar}.blade.php`, `resources/views/components/ui/{card,status-chip}.blade.php`
- Modify: `config/agapay.php`, `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`
- Test: `tests/Feature/AppShellTest.php`

**Interfaces:**
- Consumes: `KnownAccounts::others()`, `User::{hasPermission, roleShortName, greetingName, avatarUrl}`, routes `logout`, `account.switch`, `dashboard`.
- Produces:
  - `Navigation::items(User $user): list<array{label:string, url:string, icon:string, active:bool}>`
  - `Navigation::quickActions(User $user): array{width:int, gap:int, items:list<array{label:string, url:string}>}`
  - `Navigation::stats(User $user): list<array{label:string, value:int, color:string}>`
  - `DashboardStats::value(string $key, User $user): int` (keys: `total_beneficiaries, pending_rsbsa, active_interventions, active_users, pending_validation, reports_filed_this_month, encoded_this_month, records_to_update, low_stock_items`)
  - Layout `<x-layouts.app title="HOME DASHBOARD">` (slot = page cards under the search bar; optional named slot `below` for content outside the card stack).
  - `<x-ui.card title="…">` (slots: default, `actions`), `<x-ui.status-chip tone="ok|bad">Label</x-ui.status-chip>`.
  - Nav URLs for routes that don't exist yet resolve to `#` (`Route::has` check), so later phases only register routes.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AppShellTest.php`:
```php
<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\KnownAccounts;
use App\Support\Navigation;

beforeEach(fn () => seedRoles());

it('builds each role\'s Figma navigation in order', function (string $role, array $labels) {
    expect(array_column(Navigation::items(userWithRole($role)), 'label'))->toBe($labels);
})->with([
    'admin' => [Role::ADMIN, ['Home', 'DA Intervention', 'LGU Intervention', 'Newly Registered', 'Disaster Reports', 'User Management', 'Audit Trail', 'Reports']],
    'agritech' => [Role::AGRITECH, ['Home', 'Beneficiary Validation', 'DA Intervention', 'LGU Intervention', 'Disaster Reports', 'Reports']],
    'encoder' => [Role::ENCODER, ['Home', 'Beneficiary Profiles', 'Intervention Records', 'Inventory', 'Reports']],
]);

it('hides nav items whose permission the role lost', function () {
    $admin = userWithRole(Role::ADMIN);
    $admin->role->permissions()->detach(Permission::where('slug', 'audit.view')->value('id'));

    expect(array_column(Navigation::items($admin->fresh()), 'label'))->not->toContain('Audit Trail');
});

it('builds each role\'s quick actions with Figma pill sizes', function (string $role, array $labels, int $width, int $gap) {
    $actions = Navigation::quickActions(userWithRole($role));

    expect(array_column($actions['items'], 'label'))->toBe($labels)
        ->and($actions['width'])->toBe($width)
        ->and($actions['gap'])->toBe($gap);
})->with([
    'admin' => [Role::ADMIN, ['Add User', 'Export List', 'Interventions', 'Upload Excel', 'Inventory'], 176, 14],
    'agritech' => [Role::AGRITECH, ['File Disaster Report', 'Interventions'], 226, 17],
    'encoder' => [Role::ENCODER, ['Upload Excel', 'Add Beneficiary', 'Disaster Reports'], 200, 22],
]);

it('shows each role\'s stat labels with Figma colors', function () {
    $stats = Navigation::stats(userWithRole(Role::AGRITECH));

    expect(array_column($stats, 'label'))->toBe(['Pending Validation', 'Active Interventions', 'Reports Filed This Month'])
        ->and(array_column($stats, 'color'))->toBe(['#da37ff', '#8037ff', '#4671ff']);
});

it('counts active users with a role', function () {
    $admin = userWithRole(Role::ADMIN);
    userWithRole(Role::ENCODER);
    userWithRole(Role::ENCODER, ['status' => User::STATUS_INACTIVE]);
    userWithRole(null);

    $activeUsers = collect(Navigation::stats($admin))->firstWhere('label', 'Active Users');

    expect($activeUsers['value'])->toBe(2);
});

it('renders the dashboard shell for every role', function (string $role, string $greeting, string $firstStat) {
    $user = userWithRole($role);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Agapay')
        ->assertSee(now()->format('D, F j'))
        ->assertSee('HOME DASHBOARD')
        ->assertSee("Hello, {$greeting}")
        ->assertSee('Select an action to get started:')
        ->assertSee('Search beneficiary by name, RSBSA No., or barangay...', false)
        ->assertSee($user->username)
        ->assertSee($firstStat)
        ->assertSee('RETURN TO LOGIN');
})->with([
    [Role::ADMIN, 'Admin', 'Total Beneficiaries'],
    [Role::AGRITECH, 'Agritech', 'Pending Validation'],
    [Role::ENCODER, 'Encoder', 'Encoded This Month'],
]);

it('lists remembered accounts in the account menu', function () {
    $admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01']);
    $agritech = userWithRole(Role::AGRITECH, ['username' => 'Agritech_02']);
    $inactive = userWithRole(Role::ENCODER, ['username' => 'Encoder_05', 'status' => User::STATUS_INACTIVE]);

    $this->actingAs($admin)
        ->withCookie(KnownAccounts::COOKIE, json_encode([$admin->id, $agritech->id, $inactive->id, 99999]))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Agritech_02')
        ->assertDontSee('Encoder_05');
});

it('ignores a garbage remembered-accounts cookie', function () {
    $this->actingAs(userWithRole(Role::ADMIN))
        ->withCookie(KnownAccounts::COOKIE, 'not-json{')
        ->get('/dashboard')
        ->assertOk();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/AppShellTest.php`
Expected: FAIL with `Class "App\Support\Navigation" not found`.

- [ ] **Step 3: Declare the per-role navigation in config**

Replace `config/agapay.php`:
```php
<?php

use App\Models\Role;

return [
    // Development/demo password for seeded accounts. Change in .env for any real deployment.
    'seed_password' => env('AGAPAY_SEED_PASSWORD', 'Agapay@2026'),

    // Sidebar items per role (Figma order). Each is also filtered by its permission.
    'nav' => [
        Role::ADMIN => [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
            ['label' => 'DA Intervention', 'route' => 'interventions.da', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'LGU Intervention', 'route' => 'interventions.lgu', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'Newly Registered', 'route' => 'rsbsa.register', 'icon' => 'newly-registered', 'permission' => 'rsbsa.process'],
            ['label' => 'Disaster Reports', 'route' => 'damage.index', 'icon' => 'disaster', 'permission' => 'damage.view'],
            ['label' => 'User Management', 'route' => 'users.index', 'icon' => 'user-mgmt', 'permission' => 'users.manage'],
            ['label' => 'Audit Trail', 'route' => 'audit.index', 'icon' => 'audit', 'permission' => 'audit.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
        ],
        Role::AGRITECH => [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
            ['label' => 'Beneficiary Validation', 'route' => 'validation.index', 'icon' => 'validation', 'permission' => 'interventions.validate'],
            ['label' => 'DA Intervention', 'route' => 'interventions.da', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'LGU Intervention', 'route' => 'interventions.lgu', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'Disaster Reports', 'route' => 'damage.index', 'icon' => 'disaster', 'permission' => 'damage.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
        ],
        Role::ENCODER => [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
            ['label' => 'Beneficiary Profiles', 'route' => 'beneficiaries.index', 'icon' => 'profile', 'permission' => 'beneficiaries.manage'],
            ['label' => 'Intervention Records', 'route' => 'intervention-records.index', 'icon' => 'report', 'permission' => 'intervention_records.manage'],
            ['label' => 'Inventory', 'route' => 'inventory.index', 'icon' => 'inventory', 'permission' => 'inventory.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
        ],
    ],

    // "Select an action to get started:" pills. width/gap are the Figma pill sizes per role.
    'quick_actions' => [
        Role::ADMIN => ['width' => 176, 'gap' => 14, 'items' => [
            ['label' => 'Add User', 'route' => 'users.index', 'query' => ['add' => 1], 'permission' => 'users.manage'],
            ['label' => 'Export List', 'route' => 'export.index', 'permission' => 'export.run'],
            ['label' => 'Interventions', 'route' => 'interventions.index', 'permission' => 'interventions.view'],
            ['label' => 'Upload Excel', 'route' => 'import.index', 'permission' => 'import.run'],
            ['label' => 'Inventory', 'route' => 'inventory.index', 'permission' => 'inventory.view'],
        ]],
        Role::AGRITECH => ['width' => 226, 'gap' => 17, 'items' => [
            ['label' => 'File Disaster Report', 'route' => 'damage.create', 'permission' => 'damage.create'],
            ['label' => 'Interventions', 'route' => 'interventions.index', 'permission' => 'interventions.view'],
        ]],
        Role::ENCODER => ['width' => 200, 'gap' => 22, 'items' => [
            ['label' => 'Upload Excel', 'route' => 'import.index', 'permission' => 'import.run'],
            ['label' => 'Add Beneficiary', 'route' => 'rsbsa.register', 'permission' => 'rsbsa.register'],
            ['label' => 'Disaster Reports', 'route' => 'damage.index', 'permission' => 'damage.view'],
        ]],
    ],

    // Sidebar counters per role; colors are assigned in order from 'stat_colors'.
    'stats' => [
        Role::ADMIN => [
            'total_beneficiaries' => 'Total Beneficiaries',
            'pending_rsbsa' => 'Pending RSBSA',
            'active_interventions' => 'Active Interventions',
            'active_users' => 'Active Users',
        ],
        Role::AGRITECH => [
            'pending_validation' => 'Pending Validation',
            'active_interventions' => 'Active Interventions',
            'reports_filed_this_month' => 'Reports Filed This Month',
        ],
        Role::ENCODER => [
            'encoded_this_month' => 'Encoded This Month',
            'records_to_update' => 'Records to be Updated',
            'low_stock_items' => 'Low Stock Items',
        ],
    ],

    'stat_colors' => ['#da37ff', '#8037ff', '#4671ff', '#0bcaff'],
];
```

- [ ] **Step 4: Write Navigation and DashboardStats**

`app/Support/DashboardStats.php`:
```php
<?php

namespace App\Support;

use App\Models\User;

/**
 * Live sidebar counters. Each module plan replaces its own match arm
 * (beneficiaries: Phase 3, interventions: Phase 4, inventory: Phase 5, damage: Phase 7);
 * until a module's tables exist its counter is 0.
 */
final class DashboardStats
{
    public static function value(string $key, User $user): int
    {
        return match ($key) {
            'active_users' => User::where('status', User::STATUS_ACTIVE)->whereNotNull('role_id')->count(),
            'total_beneficiaries',
            'pending_rsbsa',
            'active_interventions',
            'pending_validation',
            'reports_filed_this_month',
            'encoded_this_month',
            'records_to_update',
            'low_stock_items' => 0,
        };
    }
}
```

`app/Support/Navigation.php`:
```php
<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class Navigation
{
    /** @return list<array{label:string, url:string, icon:string, active:bool}> */
    public static function items(User $user): array
    {
        return collect(config('agapay.nav.'.$user->role?->slug, []))
            ->filter(fn (array $item) => $user->hasPermission($item['permission']))
            ->map(fn (array $item) => [
                'label' => $item['label'],
                'url' => self::url($item),
                'icon' => $item['icon'],
                'active' => Route::has($item['route']) && request()->routeIs($item['route'], $item['route'].'.*'),
            ])
            ->values()
            ->all();
    }

    /** @return array{width:int, gap:int, items:list<array{label:string, url:string}>} */
    public static function quickActions(User $user): array
    {
        $config = config('agapay.quick_actions.'.$user->role?->slug, ['width' => 176, 'gap' => 14, 'items' => []]);

        return [
            'width' => $config['width'],
            'gap' => $config['gap'],
            'items' => collect($config['items'])
                ->filter(fn (array $item) => $user->hasPermission($item['permission']))
                ->map(fn (array $item) => ['label' => $item['label'], 'url' => self::url($item)])
                ->values()
                ->all(),
        ];
    }

    /** @return list<array{label:string, value:int, color:string}> */
    public static function stats(User $user): array
    {
        $colors = config('agapay.stat_colors');

        return collect(config('agapay.stats.'.$user->role?->slug, []))
            ->map(fn (string $label, string $key) => ['label' => $label, 'value' => DashboardStats::value($key, $user)])
            ->values()
            ->map(fn (array $stat, int $i) => [...$stat, 'color' => $colors[$i % count($colors)]])
            ->all();
    }

    /** Routes of modules built in later phases render as "#" until registered. */
    private static function url(array $item): string
    {
        return Route::has($item['route']) ? route($item['route'], $item['query'] ?? []) : '#';
    }
}
```

- [ ] **Step 5: Write the shell components**

`resources/views/components/layouts/app.blade.php` (header 70px; sidebar 259px; content container 11px below the header, 1.5px top/left border, radius 20, 36px/37px side padding, greeting at 22px):
```blade
@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \Illuminate\Support\Str::title(strtolower($title)) }} · Agapay</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen min-w-[1280px] bg-white font-sans text-ink antialiased">
    <x-app.header :title="$title" />

    <div class="flex">
        <x-app.sidebar />

        <main class="bg-grid relative mt-[11px] min-h-[calc(100vh-81px)] flex-1 overflow-hidden rounded-tl-[20px] border-t-[1.5px] border-l-[1.5px] border-black/15 pt-[22px] pr-[37px] pb-[40px] pl-[36px]">
            <x-app.greeting />
            <x-app.search-bar class="mt-[34px]" />

            <div class="mt-[28px] flex flex-col gap-[14px]">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
```

`resources/views/components/app/header.blade.php`:
```blade
@props(['title'])
<header class="relative z-20 flex h-[70px] items-center justify-between border border-white bg-white/10 pr-[15px] shadow-[0_4px_4px_0_rgba(0,0,0,0.05)]">
    <div class="flex items-center">
        <span class="logo-box ml-[22px] block size-[38px] border-[3px]"></span>
        <span class="ml-[15px] text-[19px] font-bold leading-[28px]">Agapay</span>
    </div>
    <div class="text-right text-[16px] font-bold leading-[20px]">
        <p>{{ now()->format('D, F j') }}</p>
        <p>{{ $title }}</p>
    </div>
</header>
```

`resources/views/components/app/sidebar.blade.php` (account 10px under header; nav at +22px, 51px items with 4px gaps; divider 12px below last item; stats 19px below divider, 51px pitch; Return card 28px from the bottom):
```blade
@php
    $user = auth()->user();
    $items = \App\Support\Navigation::items($user);
    $stats = \App\Support\Navigation::stats($user);
@endphp
<aside class="sticky top-0 flex h-[calc(100vh-70px)] w-[259px] shrink-0 flex-col overflow-y-auto pb-[28px]">
    <div class="relative z-30 mt-[10px] pl-[12px]">
        <x-app.account-menu :user="$user" />
    </div>

    <nav class="mt-[22px] flex flex-col gap-[4px] pl-[12px]" aria-label="Main">
        @foreach ($items as $item)
            <x-app.nav-item :$item />
        @endforeach
    </nav>

    <div class="divider mt-[12px] w-full"></div>

    <dl class="mt-[19px] flex flex-col pl-[27px]">
        @foreach ($stats as $stat)
            <x-app.stat :$stat />
        @endforeach
    </dl>

    <x-app.return-card class="mt-auto ml-[28px] shrink-0" />
</aside>
```

`resources/views/components/app/nav-item.blade.php`:
```blade
@props(['item'])
<a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
   class="relative block h-[51px] w-[235px] shrink-0 rounded-[15px] transition-colors hover:bg-brand/5">
    <span class="absolute top-[14px] left-[14px] flex size-[24px] items-center justify-center">
        <img src="{{ asset('images/figma/icons/'.$item['icon'].'.svg') }}" alt="">
    </span>
    <span class="absolute top-[16px] left-[47px] text-[16px] font-bold leading-[20px]">{{ $item['label'] }}</span>
</a>
```

`resources/views/components/app/stat.blade.php` (number 24px bold, label 16px regular, 11px dot, 51px pitch):
```blade
@props(['stat'])
<div class="flex h-[51px] flex-col">
    <dd class="text-[24px] font-bold leading-[31px]" style="color: {{ $stat['color'] }}">{{ number_format($stat['value']) }}</dd>
    <dt class="flex items-center gap-[6px] pl-[1px] text-[16px] leading-[20px] text-black">
        <span class="size-[11px] shrink-0 rounded-[3px]" style="background: {{ $stat['color'] }}"></span>
        {{ $stat['label'] }}
    </dt>
</div>
```

`resources/views/components/app/account-menu.blade.php` (closed 235×56; open panel overlays the nav: rows 41px with 10px gaps from 8px top, divider, 215×39 Log out):
```blade
@props(['user'])
@php($others = \App\Support\KnownAccounts::others(request(), $user))
<div x-data="{ open: false }" class="relative h-[56px] w-[235px]" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = true" :aria-expanded="open" aria-haspopup="true"
            class="relative block h-[56px] w-[235px] rounded-[15px] border-[1.5px] border-black/15 bg-white text-left">
        <img src="{{ $user->avatarUrl() }}" alt="" class="absolute top-[8.5px] left-[9.5px] size-[36px] rounded-full object-cover">
        <span class="absolute top-[5px] left-[50px] text-[16px] font-bold leading-[20px]">{{ $user->username }}</span>
        <span class="absolute top-[25px] left-[50px] text-[13px] font-medium leading-[17px] text-subtle">{{ $user->roleShortName() }}</span>
        <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="absolute top-[17px] left-[203px] size-[18px]">
    </button>

    <div x-cloak x-show="open" x-transition.opacity
         class="absolute top-0 left-0 z-40 flex w-[235px] flex-col rounded-[15px] border-[1.5px] border-black/15 bg-white pt-[6.5px] pb-[8.5px]">
        <button type="button" @click="open = false" class="absolute top-[18.5px] left-[204.5px] size-[18px]" aria-label="Close account menu">
            <img src="{{ asset('images/figma/icons/arrow-up.svg') }}" alt="">
        </button>

        <div class="relative h-[41px] pl-[9.5px]">
            <img src="{{ $user->avatarUrl() }}" alt="" class="absolute top-[2px] left-[9.5px] size-[36px] rounded-full object-cover">
            <p class="absolute top-0 left-[51.5px] text-[16px] font-bold leading-[20px]">{{ $user->username }}</p>
            <p class="absolute top-[20px] left-[51.5px] text-[13px] font-medium leading-[17px] text-subtle">{{ $user->roleShortName() }}</p>
        </div>

        @foreach ($others as $other)
            <form method="POST" action="{{ route('account.switch') }}" class="mt-[10px]">
                @csrf
                <input type="hidden" name="username" value="{{ $other->username }}">
                <button type="submit" class="relative block h-[41px] w-full text-left" title="Sign in as {{ $other->username }}">
                    <img src="{{ $other->avatarUrl() }}" alt="" class="absolute top-[2px] left-[9.5px] size-[36px] rounded-full object-cover">
                    <img src="{{ asset('images/figma/icons/ring.svg') }}" alt="" class="absolute top-[2.5px] left-[10.5px]">
                    <span class="absolute top-0 left-[51.5px] text-[16px] font-bold leading-[20px]">{{ $other->username }}</span>
                    <span class="absolute top-[20px] left-[51.5px] text-[13px] font-medium leading-[17px] text-subtle">{{ $other->roleShortName() }}</span>
                </button>
            </form>
        @endforeach

        <div class="divider mt-[7px] ml-[10.5px] w-[210px]"></div>

        <form method="POST" action="{{ route('logout') }}" class="mt-[12px] pl-[7.5px]">
            @csrf
            <button type="submit" class="bg-brand-logout h-[39px] w-[215px] rounded-[30px] text-[20px] font-bold text-white">Log out</button>
        </form>
    </div>
</div>
```

`resources/views/components/app/return-card.blade.php`:
```blade
<form method="POST" action="{{ route('account.switch') }}"
      {{ $attributes->class('bg-brand-card relative block h-[129px] w-[203px] rounded-[15px]') }}>
    @csrf
    <p class="absolute top-[14px] left-[14px] text-[16px] font-bold leading-[15px] text-white">RETURN TO LOGIN</p>
    <p class="absolute top-[34px] left-[14px] w-[152px] text-[13px] leading-[17px] text-white">Return and go back to the login screen</p>
    <button type="submit" class="absolute top-[85px] left-[14px] h-[31px] w-[175px] rounded-[50px] bg-white text-[16px] font-bold">Switch</button>
</form>
```

`resources/views/components/app/greeting.blade.php` ("Hello" 32px at the top; heading column 479px; pills 3px lower than the heading):
```blade
@php($actions = \App\Support\Navigation::quickActions(auth()->user()))
<div>
    <h1 class="text-[32px] font-bold leading-[42px]">Hello, {{ auth()->user()->greetingName() }}</h1>
    <div class="flex items-start">
        {{-- Gradient text lives on the inline span so the gradient spans the text, not the 479px column. --}}
        <p class="w-[479px] shrink-0 text-[32px] font-bold leading-[42px]">
            <span class="text-gradient-heading">Select an action to get started:</span>
        </p>
        <div class="mt-[3px] flex flex-wrap" style="gap: {{ $actions['gap'] }}px">
            @foreach ($actions['items'] as $action)
                <a href="{{ $action['url'] }}"
                   class="border-gradient flex h-[39px] items-center justify-center rounded-[50px] text-[20px] font-bold leading-[24px] whitespace-nowrap"
                   style="width: {{ $actions['width'] }}px">{{ $action['label'] }}</a>
            @endforeach
        </div>
    </div>
</div>
```

`resources/views/components/app/search-bar.blade.php` (59px bar, radius 30, text at 28px, 176×39 Filter pill 11px from the right; Phase 3 wires the `search` route and the filter popover):
```blade
<form method="GET" role="search" action="{{ Route::has('search') ? route('search') : url()->current() }}"
      {{ $attributes->class('bg-brand-bar flex h-[59px] items-center rounded-[30px] pr-[11px] pl-[28px]') }}>
    <input type="search" name="q" value="{{ request('q') }}" aria-label="Search beneficiaries"
           placeholder="Search beneficiary by name, RSBSA No., or barangay..."
           class="h-full min-w-0 flex-1 bg-transparent text-[20px] font-bold text-white outline-none placeholder:text-white">
    <button type="button" class="h-[39px] w-[176px] shrink-0 rounded-[50px] bg-white text-[20px] font-bold">Filter</button>
</form>
```

`resources/views/components/ui/card.blade.php` (radius 20, 1.5px black/10 border, 18px task icon at 21.5/21.5, bold 16px title at 44.5):
```blade
@props(['title'])
<section {{ $attributes->class('relative rounded-[20px] border-[1.5px] border-black/10 bg-white pt-[19.5px] pr-[26.5px] pb-[20px] pl-[21.5px]') }}>
    <header class="flex min-h-[20px] items-center justify-between gap-4">
        <div class="flex items-center gap-[5px]">
            <img src="{{ asset('images/figma/icons/task.svg') }}" alt="" class="size-[18px]">
            <h2 class="text-[16px] font-bold leading-[20px]">{{ $title }}</h2>
        </div>
        {{ $actions ?? '' }}
    </header>
    {{ $slot }}
</section>
```

`resources/views/components/ui/status-chip.blade.php`:
```blade
@props(['tone' => 'ok'])
<span {{ $attributes->class([
    'inline-flex h-[39px] w-[155px] items-center justify-center rounded-[10px] border-4 bg-white text-[14px] font-bold leading-[24px]',
    'border-ok' => $tone === 'ok',
    'border-bad' => $tone === 'bad',
]) }}>{{ $slot }}</span>
```

- [ ] **Step 6: Replace the dashboard view and controller**

`app/Http/Controllers/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            // Data Encoders see their encoding queue; Admin and Agri Tech see all beneficiaries (Figma).
            'showsEncodingQueue' => $request->user()->role?->slug === Role::ENCODER,
        ]);
    }
}
```

`resources/views/dashboard.blade.php` (the tables fill in Phase 3; header rows match Figma columns now):
```blade
<x-layouts.app title="HOME DASHBOARD">
    @if ($showsEncodingQueue)
        <x-ui.card title="Pending Encoding Queue">
            <div class="mt-[18px] grid grid-cols-[287px_250px_380px_1fr_155px] text-[14px] font-medium leading-[20px] text-muted">
                <span>Name</span><span>Source</span><span>Issue</span><span>Date Added</span><span class="text-center">Status</span>
            </div>
            <div class="divider mt-[13px]"></div>
            <p class="py-[14px] text-[14px] font-bold">No records are waiting to be encoded.</p>
        </x-ui.card>
    @else
        <x-ui.card title="All Beneficiaries">
            <div class="mt-[18px] grid grid-cols-[310px_261px_236px_234px_1fr_155px] text-[14px] font-medium leading-[20px] text-muted">
                <span>Name</span><span>RSBSA Number</span><span>Barangay</span><span>Household</span><span>Intervention</span><span class="text-center">Status</span>
            </div>
            <div class="divider mt-[13px]"></div>
            <p class="py-[14px] text-[14px] font-bold">No beneficiaries recorded yet.</p>
        </x-ui.card>
    @endif
</x-layouts.app>
```

- [ ] **Step 7: Run tests, build, and eyeball it**

Run: `./vendor/bin/pest`
Expected: all tests pass (Assets, RolesPermissions, Login, AppShell).

Run: `npm run build`, then start `php artisan serve` and XAMPP MySQL, log in as `Admin_01` with the seed password in `config/agapay.php`, and compare `/dashboard` side by side with Figma frame `310:2`. Open the account dropdown and compare with `310:844`, after logging in as `Agritech_02` once so it's remembered.

- [ ] **Step 8: Commit**

```bash
git add app config resources tests
git commit -m "feat: build Figma app shell with role-based sidebar, quick actions and account switcher

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Audit logging core

**Files:**
- Create: `database/migrations/2026_09_28_000003_create_audit_logs_table.php`, `app/Models/AuditLog.php`, `app/Services/AuditLogger.php`, `app/Models/Concerns/Auditable.php`
- Modify: `app/Models/User.php` (use `Auditable`), `app/Providers/AppServiceProvider.php` (login/logout listeners)
- Test: `tests/Feature/AuditTest.php`

**Interfaces:**
- Produces:
  - `AuditLog` columns `user_id, action, auditable_type, auditable_id, record_label, old_values (array), new_values (array), ip_address, user_agent, created_at`; relation `user(): BelongsTo`; update/delete throw `LogicException`.
  - `AuditLogger::record(string $action, ?Model $subject = null, ?string $label = null, array $old = [], array $new = [], ?User $actor = null): AuditLog`
  - Trait `Auditable`: models declare `protected string $auditSubject` (e.g. `'User'`), optional `protected array $auditIgnore`, and implement `auditRecordLabel(): string`. It logs `"Added {subject}"`, `"Updated {subject}"`, `"Deleted {subject}"`, or `"Archived {subject}"` (soft delete), and `"Restored {subject}"`.
  - Actions `Logged In` / `Logged Out` (label = username).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AuditTest.php`:
```php
<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

beforeEach(fn () => seedRoles());

it('logs account creation without secrets', function () {
    $actor = userWithRole(Role::ADMIN);
    $this->actingAs($actor);

    $created = userWithRole(Role::ENCODER, ['username' => 'Encoder_04']);
    $log = AuditLog::where('action', 'Added User')->where('auditable_id', $created->id)->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->record_label)->toBe('Encoder_04')
        ->and($log->new_values)->toHaveKey('username', 'Encoder_04')
        ->and($log->new_values)->not->toHaveKeys(['password', 'remember_token']);
});

it('logs updates with old and new values', function () {
    $user = userWithRole(Role::ENCODER);
    $agritechId = Role::where('slug', Role::AGRITECH)->value('id');

    $user->update(['role_id' => $agritechId]);
    $log = AuditLog::where('action', 'Updated User')->sole();

    expect($log->old_values)->toBe(['role_id' => Role::where('slug', Role::ENCODER)->value('id')])
        ->and($log->new_values)->toBe(['role_id' => $agritechId]);
});

it('logs password changes without recording the password', function () {
    $user = userWithRole(Role::ENCODER);
    $user->update(['password' => 'a-new-password']);

    $log = AuditLog::where('action', 'Updated User')->sole();

    expect($log->old_values)->toBeNull()->and($log->new_values)->toBeNull();
});

it('skips noise-only updates', function () {
    $user = userWithRole(Role::ENCODER);
    $user->update(['remember_token' => 'abc', 'last_login_at' => now()]);

    expect(AuditLog::where('action', 'Updated User')->count())->toBe(0);
});

it('is append-only', function () {
    $user = userWithRole(Role::ENCODER);
    $log = AuditLog::firstOrFail();

    expect(fn () => $log->update(['action' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('logs sign-in and sign-out', function () {
    $user = userWithRole(Role::ADMIN, ['username' => 'Admin_01', 'password' => 'secret-pass']);

    $this->post('/login', ['username' => 'Admin_01', 'password' => 'secret-pass']);
    $this->post('/logout');

    expect(AuditLog::where('user_id', $user->id)->pluck('action')->all())
        ->toContain('Logged In', 'Logged Out');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/AuditTest.php`
Expected: FAIL with `Class "App\Models\AuditLog" not found`.

- [ ] **Step 3: Write the migration, model, logger and trait**

`database/migrations/2026_09_28_000003_create_audit_logs_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->index();
            $table->nullableMorphs('auditable');
            $table->string('record_label')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

`app/Models/AuditLog.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['user_id', 'action', 'auditable_type', 'auditable_id', 'record_label', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`app/Services/AuditLogger.php`:
```php
<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/** The only write path into audit_logs. */
final class AuditLogger
{
    /** Never persisted, whatever the caller passes. */
    private const SECRET_KEYS = ['password', 'remember_token'];

    private const TIMESTAMP_KEYS = ['created_at', 'updated_at'];

    public static function record(
        string $action,
        ?Model $subject = null,
        ?string $label = null,
        array $old = [],
        array $new = [],
        ?User $actor = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => $actor?->getKey() ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'record_label' => $label ?? ($subject && method_exists($subject, 'auditRecordLabel') ? $subject->auditRecordLabel() : null),
            'old_values' => self::clean($old),
            'new_values' => self::clean($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private static function clean(array $values): ?array
    {
        $clean = Arr::except($values, [...self::SECRET_KEYS, ...self::TIMESTAMP_KEYS]);

        return $clean === [] ? null : $clean;
    }
}
```

`app/Models/Concerns/Auditable.php`:
```php
<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * Writes an audit row for every create / update / delete / restore.
 * Using models declare `protected string $auditSubject` and implement auditRecordLabel().
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLogger::record('Added '.$model->auditSubject(), $model, null, [], $model->attributesToArray());
        });

        static::updated(function (Model $model) {
            $changes = Arr::except($model->getChanges(), ['updated_at', ...$model->auditIgnoredAttributes()]);

            if ($changes === []) {
                return;
            }

            AuditLogger::record(
                'Updated '.$model->auditSubject(),
                $model,
                null,
                Arr::only($model->getOriginal(), array_keys($changes)),
                $changes,
            );
        });

        static::deleted(function (Model $model) {
            $softDeleted = in_array(SoftDeletes::class, class_uses_recursive($model), true) && ! $model->isForceDeleting();

            AuditLogger::record(($softDeleted ? 'Archived ' : 'Deleted ').$model->auditSubject(), $model);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn (Model $model) => AuditLogger::record('Restored '.$model->auditSubject(), $model));
        }
    }

    public function auditSubject(): string
    {
        return $this->auditSubject ?? class_basename($this);
    }

    /** @return list<string> */
    public function auditIgnoredAttributes(): array
    {
        return $this->auditIgnore ?? [];
    }

    abstract public function auditRecordLabel(): string;
}
```

`attributesToArray()` already drops `#[Hidden]` attributes, so password hashes never reach `created` logs. `AuditLogger::clean` is the second guard.

- [ ] **Step 4: Make User auditable and listen for auth events**

In `app/Models/User.php`: add `use App\Models\Concerns\Auditable;` to the imports, change the trait line to `use Auditable, HasFactory, Notifiable;`, and add these members after the constants:
```php
    protected string $auditSubject = 'User';

    /** Changes that happen on every sign-in are not user activity worth auditing. */
    protected array $auditIgnore = ['remember_token', 'last_login_at'];

    public function auditRecordLabel(): string
    {
        return $this->username;
    }
```

In `app/Providers/AppServiceProvider.php` `boot()`, after the `Gate::before` line, add:
```php
        Event::listen(Login::class, fn (Login $event) => AuditLogger::record('Logged In', $event->user, $event->user->username, actor: $event->user));
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                AuditLogger::record('Logged Out', $event->user, $event->user->username, actor: $event->user);
            }
        });
```
and imports:
```php
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest`
Expected: all pass. In `LoginTest`, the throttle test counts only failed logins, so the new audit rows don't affect it.

- [ ] **Step 6: Commit**

```bash
git add app database tests
git commit -m "feat: add append-only audit trail with model and login/logout logging

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Audit Trail page (frame 329:904)

**Files:**
- Create: `app/Http/Controllers/AuditTrailController.php`, `resources/views/audit/index.blade.php`, `resources/views/components/ui/pill-input.blade.php`, `resources/views/components/ui/pill-select.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AuditTrailTest.php`

**Interfaces:**
- Consumes: `AuditLog`, `AuditLogger::record()`, `<x-layouts.app>`, `<x-ui.card>`.
- Produces:
  - Route `GET /audit-trail` `audit.index` (`can:audit.view`), query params `timestamp`, `user`, `action`.
  - `<x-ui.pill-input name="…" placeholder="…" :value="…" width="223" />` (39px tall, 1.5px black/15 border, search icon at right).
  - `<x-ui.pill-select name="…" label="Role" :options="['' => 'All', …]" :selected="…" width="280" />` (renders "Label: Option" text, arrow at right, submits its form on change).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AuditTrailTest.php`:
```php
<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedRoles();
    $this->admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01']);
    $this->encoder = userWithRole(Role::ENCODER, ['username' => 'Encoder_03']);
    AuditLog::query()->toBase()->delete(); // start from an empty trail (bypasses the append-only guard)

    Carbon::setTestNow('2026-07-17 09:12:00');
    AuditLogger::record('Added User', label: 'Encoder_04', actor: $this->admin);
    Carbon::setTestNow('2026-07-16 15:45:00');
    AuditLogger::record('Updated Beneficiary Profile', label: 'Juan Dela Cruz (RSBSA-0231)', actor: $this->encoder);
    Carbon::setTestNow();
});

it('is only for users with audit.view', function () {
    $this->actingAs($this->encoder)->get('/audit-trail')->assertForbidden();
    $this->get('/audit-trail')->assertRedirect(); // guest
});

it('lists entries newest first in the Figma format', function () {
    $this->actingAs($this->admin)->get('/audit-trail')
        ->assertOk()
        ->assertSee('AUDIT TRAIL')
        ->assertSeeInOrder([
            'Jul 17, 2026 - 9:12 AM', 'Admin_01', 'Added User', 'Encoder_04',
            'Jul 16, 2026 - 3:45 PM', 'Encoder_03', 'Updated Beneficiary Profile', 'Juan Dela Cruz (RSBSA-0231)',
        ]);
});

it('filters by user', function () {
    $this->actingAs($this->admin)->get('/audit-trail?user=encoder')
        ->assertSee('Updated Beneficiary Profile')
        ->assertDontSee('Added User</td>', false);
});

it('filters by action', function () {
    $this->actingAs($this->admin)->get('/audit-trail?action=Added+User')
        ->assertSee('Encoder_04')
        ->assertDontSee('Juan Dela Cruz (RSBSA-0231)');
});

it('filters by date typed in plain words', function () {
    $this->actingAs($this->admin)->get('/audit-trail?timestamp=Jul+16,+2026')
        ->assertSee('Juan Dela Cruz (RSBSA-0231)')
        ->assertDontSee('>Encoder_04<', false);
});

it('explains an unreadable date instead of failing', function () {
    $this->actingAs($this->admin)->get('/audit-trail?timestamp=not-a-date')
        ->assertOk()
        ->assertSee('Enter a date such as Jul 17, 2026.')
        ->assertDontSee('Juan Dela Cruz (RSBSA-0231)');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/AuditTrailTest.php`
Expected: FAIL. `/audit-trail` returns 404.

- [ ] **Step 3: Write the pill inputs**

`resources/views/components/ui/pill-input.blade.php`:
```blade
@props(['name', 'placeholder' => '', 'value' => null, 'width' => 272])
<label class="relative block h-[39px] shrink-0" style="width: {{ $width }}px">
    <span class="sr-only">{{ $placeholder }}</span>
    <input type="search" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
           class="h-[39px] w-full rounded-[50px] border-[1.5px] border-black/15 bg-white pr-[36px] pl-[13.5px] text-[14px] font-medium text-ink outline-none placeholder:text-muted focus:border-brand-soft">
    <img src="{{ asset('images/figma/icons/search.svg') }}" alt="" class="pointer-events-none absolute top-[11px] right-[14px] size-[16px]">
</label>
```

`resources/views/components/ui/pill-select.blade.php`:
```blade
@props(['name', 'label', 'options' => [], 'selected' => null, 'width' => 280])
<label class="relative block h-[39px] shrink-0" style="width: {{ $width }}px">
    <span class="sr-only">{{ $label }}</span>
    <select name="{{ $name }}" onchange="this.form.requestSubmit()"
            class="h-[39px] w-full cursor-pointer appearance-none rounded-[50px] border-[1.5px] border-black/15 bg-white pr-[36px] pl-[16px] text-[14px] font-medium text-muted outline-none focus:border-brand-soft">
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}: {{ $text }}</option>
        @endforeach
    </select>
    <img src="{{ asset('images/figma/icons/arrow-down.svg') }}" alt="" class="pointer-events-none absolute top-[10.5px] right-[14.5px] size-[18px]">
</label>
```

- [ ] **Step 4: Write the controller, route and view**

`app/Http/Controllers/AuditTrailController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class AuditTrailController extends Controller
{
    public function index(Request $request): View
    {
        $timestamp = trim((string) $request->query('timestamp'));
        $date = $this->parseDate($timestamp);
        $invalidDate = $timestamp !== '' && $date === null;

        $logs = AuditLog::query()
            ->with('user:id,username')
            ->when($request->filled('user'), fn ($query) => $query->whereHas(
                'user', fn ($user) => $user->where('username', 'like', '%'.$request->query('user').'%')
            ))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->query('action')))
            ->when($date, fn ($query) => $query->whereDate('created_at', $date))
            ->when($invalidDate, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all(),
            'invalidDate' => $invalidDate,
        ]);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
```

In `routes/web.php`, add the import `use App\Http\Controllers\AuditTrailController;` and, inside the `['auth', 'active']` group:
```php
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->middleware('can:audit.view')->name('audit.index');
```

`resources/views/audit/index.blade.php` (filters at 26/323/573px with widths 223/223/300; "Record Affected" at 1000px; divider 15px under the filters; 45px row pitch; columns at 38/340/590/1000px from the card edge):
```blade
<x-layouts.app title="AUDIT TRAIL">
    <x-ui.card title="Audit Trail">
        <form method="GET" class="mt-[18.5px] grid grid-cols-[297px_250px_427px_1fr] items-center pl-[4.5px]">
            <x-ui.pill-input name="timestamp" placeholder="Search timestamp..." :value="request('timestamp')" width="223" />
            <x-ui.pill-input name="user" placeholder="Search by user..." :value="request('user')" width="223" />
            <x-ui.pill-select name="action" label="Action" :options="['' => 'All'] + $actions" :selected="request('action')" width="300" />
            <span class="text-[14px] font-medium text-muted">Record Affected</span>
            <button type="submit" class="sr-only">Apply filters</button>
        </form>

        <div class="divider mt-[15px]"></div>

        @if ($invalidDate)
            <p class="mt-[14px] pl-[16.5px] text-[14px] font-bold text-danger">Enter a date such as Jul 17, 2026.</p>
        @endif

        <table class="mt-[4px] w-full table-fixed text-left text-[14px] font-bold leading-[21px]">
            <colgroup><col class="w-[302px]"><col class="w-[250px]"><col class="w-[410px]"><col></colgroup>
            <thead class="sr-only"><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Record Affected</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="h-[45px]">
                        <td class="pl-[16.5px]">{{ $log->created_at->format('M j, Y - g:i A') }}</td>
                        <td>{{ $log->user?->username ?? 'System' }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->record_label }}</td>
                    </tr>
                @empty
                    @unless ($invalidDate)
                        <tr><td colspan="4" class="py-[12px] pl-[16.5px]">No activity matches these filters.</td></tr>
                    @endunless
                @endforelse
            </tbody>
        </table>

        @if ($logs->hasPages())
            <div class="mt-[12px] text-[14px]">{{ $logs->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
```

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/AuditTrailTest.php`
Expected: 6 passed.

- [ ] **Step 6: Commit**

```bash
git add app resources routes tests
git commit -m "feat: add Audit Trail page with timestamp, user and action filters

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: User Management with Add/Edit User and Configure Roles (frame 329:695)

**Files:**
- Create: `app/Http/Controllers/UserController.php`, `app/Http/Controllers/RolePermissionController.php`, `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`, `resources/views/users/index.blade.php`, `resources/views/components/ui/{modal,inline-field,gradient-button}.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/UserManagementTest.php`

**Interfaces:**
- Consumes: `PermissionCatalog::LOCKED_FOR_ADMIN`, `AuditLogger::record()`, `<x-ui.pill-input>`, `<x-ui.pill-select>`, `<x-ui.status-chip>`.
- Produces:
  - Routes (all `can:users.manage` except the last): `GET /users` `users.index` (query `q`, `role` = role slug | `none`, `add=1` opens the Add modal), `POST /users` `users.store`, `PUT /users/{user}` `users.update`, `PUT /roles/permissions` `roles.permissions.update` (`can:roles.configure`, body `permissions[<role_id>][] = <slug>`).
  - Error bags `createUser`, `editUser`.
  - `<x-ui.modal name="…" title="…" :open="bool">` (Figma 700px modal, radius 20, 22.5px padding), `<x-ui.inline-field label="…" name="…" :value type placeholder error>` (44px, 1px `#e2e2e2`, radius 10, bold 14px "Label:" prefix), `<x-ui.gradient-button>` (48px, radius 50, bold 20px white).
  - Audit action `Configured Roles` (label = role name, old/new = permission slug lists).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/UserManagementTest.php`:
```php
<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    seedRoles();
    $this->admin = userWithRole(Role::ADMIN, ['username' => 'Admin_01']);
    $this->roleId = fn (string $slug) => Role::where('slug', $slug)->value('id');
});

it('is only for users with users.manage', function () {
    $this->actingAs(userWithRole(Role::AGRITECH))->get('/users')->assertForbidden();
    $this->actingAs(userWithRole(Role::AGRITECH))->post('/users', [])->assertForbidden();
});

it('lists users with role and status like Figma', function () {
    userWithRole(null, ['username' => 'Encoder_04', 'status' => User::STATUS_INACTIVE]);

    $this->actingAs($this->admin)->get('/users')
        ->assertOk()
        ->assertSee('USER MANAGEMENT')
        ->assertSeeInOrder(['Admin_01', 'Administrator', 'Active', 'Encoder_04', 'No Role', 'Inactive'])
        ->assertSee('Configure Roles');
});

it('searches and filters by role', function () {
    userWithRole(Role::ENCODER, ['username' => 'Encoder_03']);
    userWithRole(null, ['username' => 'Encoder_04']);

    $this->actingAs($this->admin)->get('/users?q=enc&role=none')
        ->assertSee('Encoder_04')
        ->assertDontSee('Encoder_03');
});

it('opens the Add User modal from the quick action', function () {
    $this->actingAs($this->admin)->get('/users?add=1')->assertSee('x-data="{ open: true }"', false);
});

it('adds a user', function () {
    $this->actingAs($this->admin)->post('/users', [
        'name' => 'Rosa Encoder',
        'username' => 'Encoder_05',
        'password' => 'strong-pass-1',
        'password_confirmation' => 'strong-pass-1',
        'role_id' => ($this->roleId)(Role::ENCODER),
        'status' => User::STATUS_ACTIVE,
    ])->assertRedirect(route('users.index'))->assertSessionHas('status', 'Encoder_05 was added.');

    $user = User::firstWhere('username', 'Encoder_05');
    expect(Hash::check('strong-pass-1', $user->password))->toBeTrue()
        ->and(AuditLog::where('action', 'Added User')->where('record_label', 'Encoder_05')->exists())->toBeTrue();
});

it('rejects duplicate usernames regardless of case and weak passwords', function () {
    $this->actingAs($this->admin)->post('/users', [
        'name' => 'Dup', 'username' => 'admin_01', 'password' => 'short', 'password_confirmation' => 'short',
        'role_id' => null, 'status' => User::STATUS_ACTIVE,
    ])->assertSessionHasErrorsIn('createUser', ['username', 'password']);
});

it('edits a user and keeps the password when left blank', function () {
    $user = userWithRole(Role::ENCODER, ['username' => 'Encoder_03', 'password' => 'keep-this-pass']);

    $this->actingAs($this->admin)->put("/users/{$user->id}", [
        'name' => $user->name, 'username' => 'Encoder_03', 'password' => '', 'password_confirmation' => '',
        'role_id' => ($this->roleId)(Role::AGRITECH), 'status' => User::STATUS_INACTIVE,
    ])->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->role->slug)->toBe(Role::AGRITECH)
        ->and($user->status)->toBe(User::STATUS_INACTIVE)
        ->and(Hash::check('keep-this-pass', $user->password))->toBeTrue();
});

it('stops admins from locking themselves out', function (array $change) {
    $this->actingAs($this->admin)->put("/users/{$this->admin->id}", [
        'name' => $this->admin->name, 'username' => 'Admin_01', 'password' => '', 'password_confirmation' => '',
        'role_id' => ($this->roleId)(Role::ADMIN), 'status' => User::STATUS_ACTIVE, ...$change,
    ])->assertSessionHasErrorsIn('editUser', ['role_id' => 'You cannot deactivate or change the role of your own account.']);

    expect($this->admin->fresh()->canSignIn())->toBeTrue();
})->with([
    'deactivate self' => [['status' => User::STATUS_INACTIVE]],
    'demote self' => [fn () => ['role_id' => Role::where('slug', Role::ENCODER)->value('id')]],
]);

it('configures role permissions', function () {
    $agritech = Role::firstWhere('slug', Role::AGRITECH);
    $matrix = Role::with('permissions')->get()->mapWithKeys(
        fn (Role $role) => [$role->id => $role->permissions->pluck('slug')->all()]
    )->all();
    $matrix[$agritech->id][] = 'inventory.view';

    $this->actingAs($this->admin)->put('/roles/permissions', ['permissions' => $matrix])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('status', 'Role permissions saved.');

    expect($agritech->fresh()->permissions->pluck('slug'))->toContain('inventory.view')
        ->and(AuditLog::where('action', 'Configured Roles')->where('record_label', 'Agricultural Technologist')->exists())->toBeTrue();
});

it('never removes the admin lock-out permissions', function () {
    $admin = Role::firstWhere('slug', Role::ADMIN);

    $this->actingAs($this->admin)->put('/roles/permissions', ['permissions' => [$admin->id => ['dashboard.view']]]);

    expect($admin->fresh()->permissions->pluck('slug')->all())
        ->toContain('users.manage', 'roles.configure', 'dashboard.view');
});

it('ignores unknown permission slugs and role ids', function () {
    $this->actingAs($this->admin)->put('/roles/permissions', ['permissions' => [99999 => ['users.manage'], ($this->roleId)(Role::ENCODER) => ['hack.everything']]])
        ->assertRedirect(route('users.index'));

    expect(Permission::where('slug', 'hack.everything')->exists())->toBeFalse()
        ->and(Role::firstWhere('slug', Role::ENCODER)->permissions)->toBeEmpty();
});

it('limits Configure Roles to roles.configure holders', function () {
    $admin = Role::firstWhere('slug', Role::ADMIN);
    $admin->permissions()->detach(Permission::where('slug', 'roles.configure')->value('id'));

    $this->actingAs($this->admin->fresh())->put('/roles/permissions', ['permissions' => []])->assertForbidden();
});
```

Configure Roles saves the whole matrix, so a role missing from `permissions` loses all its permissions. That's why "ignores unknown" expects Encoder to end up empty. This is intentional: the form always posts every role, and an unchecked box means "remove". The Administrator's locked permissions are re-added regardless.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/UserManagementTest.php`
Expected: FAIL. `/users` returns 404.

- [ ] **Step 3: Write the form requests**

`app/Http/Requests/StoreUserRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    protected $errorBag = 'createUser';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', $this->uniqueUsername()],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ];
    }

    public function messages(): array
    {
        return ['username.regex' => 'Use letters, numbers, dots, dashes or underscores only.'];
    }

    /** Usernames are unique regardless of letter case (login is case-insensitive). */
    protected function uniqueUsername(?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
            $taken = User::whereRaw('LOWER(username) = ?', [Str::lower((string) $value)])
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if ($taken) {
                $fail('This username is already taken.');
            }
        };
    }
}
```

`app/Http/Requests/UpdateUserRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends StoreUserRequest
{
    protected $errorBag = 'editUser';

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            ...parent::rules(),
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', $this->uniqueUsername($target->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var User $target */
                $target = $this->route('user');

                $lockingSelfOut = $target->is($this->user()) && (
                    $this->input('status') !== User::STATUS_ACTIVE
                    || (int) $this->input('role_id') !== $target->role_id
                );

                if ($lockingSelfOut) {
                    $validator->errors()->add('role_id', 'You cannot deactivate or change the role of your own account.');
                }
            },
        ];
    }
}
```

- [ ] **Step 4: Write the controllers and routes**

`app/Http/Controllers/UserController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role = (string) $request->query('role', '');

        $users = User::with('role')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->query('q').'%';
                $query->where(fn ($q) => $q->where('username', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($role === 'none', fn ($query) => $query->whereNull('role_id'))
            ->when($role !== '' && $role !== 'none', fn ($query) => $query->whereHas('role', fn ($r) => $r->where('slug', $role)))
            ->orderBy('id')
            ->get();

        $roles = Role::with('permissions:id,slug')->orderBy('id')->get();

        return view('users.index', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => Permission::orderBy('id')->get()->groupBy('group'),
            'roleOptions' => ['' => 'All'] + $roles->pluck('name', 'slug')->all() + ['none' => 'No Role'],
            'openAdd' => $request->boolean('add') || session('errors')?->getBag('createUser')->any(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return redirect()->route('users.index')->with('status', "{$user->username} was added.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', "{$user->username} was updated.");
    }
}
```

`app/Http/Controllers/RolePermissionController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $submitted = (array) $request->input('permissions', []);
        $permissionIds = Permission::pluck('id', 'slug');

        DB::transaction(function () use ($submitted, $permissionIds) {
            foreach (Role::with('permissions:id,slug')->get() as $role) {
                $slugs = collect((array) ($submitted[$role->id] ?? []))
                    ->filter(fn ($slug) => is_string($slug) && $permissionIds->has($slug));

                if ($role->slug === Role::ADMIN) {
                    $slugs = $slugs->merge(PermissionCatalog::LOCKED_FOR_ADMIN);
                }

                $new = $slugs->unique()->sort()->values()->all();
                $old = $role->permissions->pluck('slug')->sort()->values()->all();

                if ($new === $old) {
                    continue;
                }

                $role->permissions()->sync($permissionIds->only($new)->values());
                AuditLogger::record('Configured Roles', $role, $role->name, ['permissions' => $old], ['permissions' => $new]);
            }
        });

        return redirect()->route('users.index')->with('status', 'Role permissions saved.');
    }
}
```

In `routes/web.php` add the imports `use App\Http\Controllers\UserController;` and `use App\Http\Controllers\RolePermissionController;`, and inside the `['auth', 'active']` group:
```php
    Route::middleware('can:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::put('/roles/permissions', [RolePermissionController::class, 'update'])
        ->middleware('can:roles.configure')->name('roles.permissions.update');
```

- [ ] **Step 5: Write the modal components (Figma "Record Stock Movement" modal 470:1270)**

`resources/views/components/ui/modal.blade.php` (no dimming, as in Figma; 700px card, radius 20, 1.5px black/10 border, 22.5px padding, bold 16px title):
```blade
@props(['title', 'open' => false, 'width' => 700])
<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" x-on:open-modal.window="if ($event.detail === '{{ $attributes->get('name') }}') open = true"
     x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-start justify-center pt-[300px]" @click.self="open = false" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div class="rounded-[20px] border-[1.5px] border-black/10 bg-white p-[22.5px] shadow-[0_4px_20px_rgba(0,0,0,0.08)]" style="width: {{ $width }}px">
            <div class="flex items-center justify-between">
                <h2 class="text-[16px] font-bold leading-[24px]">{{ $title }}</h2>
                <button type="button" @click="open = false" class="text-[20px] leading-none text-muted" aria-label="Close">&times;</button>
            </div>
            <div class="mt-[24px]">{{ $slot }}</div>
        </div>
    </div>
</div>
```

The test `opens the Add User modal` asserts the literal `x-data="{ open: true }"`, which is exactly what the first attribute renders when `:open` is true.

`resources/views/components/ui/inline-field.blade.php`:
```blade
@props(['label', 'name', 'type' => 'text', 'value' => null, 'placeholder' => '', 'error' => null])
<div>
    <label class="flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold leading-[20px] {{ $error ? 'border-bad' : 'border-field' }}">
        <span class="shrink-0">{{ $label }}:</span>
        <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}"
               {{ $attributes->class('ml-[6px] h-full min-w-0 flex-1 bg-transparent font-bold outline-none placeholder:text-muted') }}>
    </label>
    @if ($error)
        <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $error }}</p>
    @endif
</div>
```

`resources/views/components/ui/gradient-button.blade.php`:
```blade
<button {{ $attributes->merge(['type' => 'submit'])->class('bg-brand-button h-[48px] w-full rounded-[50px] text-[20px] font-bold leading-[24px] text-white') }}>
    {{ $slot }}
</button>
```

- [ ] **Step 6: Write the User Management view**

`resources/views/users/index.blade.php` (filters: search 272px, role 280px, 22px gap; "Status" header over the 155px chip column; divider at +12px; 45px rows with usernames at 15px inset and roles at 333.5px; Configure Roles 263×39 `#7e80ff` pill 14px below the card):
```blade
@php($editBag = $errors->getBag('editUser'))
<x-layouts.app title="USER MANAGEMENT">
    @if (session('status'))
        <p class="rounded-[10px] border-[1.5px] border-ok bg-white px-[16px] py-[10px] text-[14px] font-bold" role="status">{{ session('status') }}</p>
    @endif

    <x-ui.card title="User Management">
        <form method="GET" class="mt-[17.5px] flex items-center gap-[22px]">
            <x-ui.pill-input name="q" placeholder="Search by name..." :value="request('q')" width="272" />
            <x-ui.pill-select name="role" label="Role" :options="$roleOptions" :selected="request('role')" width="280" />
            <span class="ml-auto w-[155px] text-center text-[14px] font-medium text-muted">Status</span>
            <button type="submit" class="sr-only">Apply filters</button>
        </form>

        <div class="divider mt-[12px]"></div>

        <ul class="mt-[6.5px] flex flex-col gap-[6px]">
            @forelse ($users as $user)
                <li x-data>
                    <button type="button" @click="$dispatch('open-modal', 'edit-user-{{ $user->id }}')"
                            class="grid h-[39px] w-full grid-cols-[297px_1fr_155px] items-center rounded-[10px] text-left text-[14px] font-bold hover:bg-canvas">
                        <span class="pl-[15px]">{{ $user->username }}</span>
                        <span>{{ $user->roleName() }}</span>
                        <x-ui.status-chip :tone="$user->isActive() ? 'ok' : 'bad'">{{ $user->isActive() ? 'Active' : 'Inactive' }}</x-ui.status-chip>
                    </button>
                </li>
            @empty
                <li class="py-[12px] pl-[15px] text-[14px] font-bold">No users match these filters.</li>
            @endforelse
        </ul>
    </x-ui.card>

    <div>
        <button type="button" x-data @click="$dispatch('open-modal', 'configure-roles')"
                class="ml-[-3px] flex h-[39px] w-[263px] items-center justify-center gap-[2px] rounded-[50px] border-[1.5px] border-brand-soft bg-white text-[20px] font-bold leading-[24px]">
            <img src="{{ asset('images/figma/icons/plus.svg') }}" alt="" class="size-[24px]"> Configure Roles
        </button>
    </div>

    {{-- Add User --}}
    <x-ui.modal name="add-user" title="Add User" :open="$openAdd">
        <form method="POST" action="{{ route('users.store') }}" class="grid grid-cols-[300px_1fr] gap-x-[16px] gap-y-[12px]">
            @csrf
            <x-ui.inline-field label="Full Name" name="name" :value="old('name')" placeholder="e.g. Rosa Mendez" :error="$errors->createUser->first('name')" required />
            <x-ui.inline-field label="Username" name="username" :value="old('username')" placeholder="e.g. Encoder_05" :error="$errors->createUser->first('username')" required />
            <x-ui.inline-field label="Password" name="password" type="password" placeholder="At least 8 characters" :error="$errors->createUser->first('password')" required />
            <x-ui.inline-field label="Confirm" name="password_confirmation" type="password" placeholder="Repeat password" required />
            @include('users.partials.role-status-fields', ['bag' => 'createUser', 'roleId' => old('role_id'), 'status' => old('status', 'active')])
            <x-ui.gradient-button class="col-span-2 mt-[20px]">Save User</x-ui.gradient-button>
        </form>
    </x-ui.modal>

    {{-- Edit User (one per row, re-opened after a failed save) --}}
    @foreach ($users as $user)
        @php($failed = $editBag->any() && (int) old('user_id') === $user->id)
        <x-ui.modal name="edit-user-{{ $user->id }}" title="Edit {{ $user->username }}" :open="$failed">
            <form method="POST" action="{{ route('users.update', $user) }}" class="grid grid-cols-[300px_1fr] gap-x-[16px] gap-y-[12px]">
                @csrf @method('PUT')
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <x-ui.inline-field label="Full Name" name="name" :value="$failed ? old('name') : $user->name" :error="$failed ? $editBag->first('name') : null" required />
                <x-ui.inline-field label="Username" name="username" :value="$failed ? old('username') : $user->username" :error="$failed ? $editBag->first('username') : null" required />
                <x-ui.inline-field label="New Password" name="password" type="password" placeholder="Leave blank to keep" :error="$failed ? $editBag->first('password') : null" />
                <x-ui.inline-field label="Confirm" name="password_confirmation" type="password" placeholder="Repeat new password" />
                @include('users.partials.role-status-fields', [
                    'bag' => 'editUser',
                    'failed' => $failed,
                    'roleId' => $failed ? old('role_id') : $user->role_id,
                    'status' => $failed ? old('status') : $user->status,
                ])
                <x-ui.gradient-button class="col-span-2 mt-[20px]">Save Changes</x-ui.gradient-button>
            </form>
        </x-ui.modal>
    @endforeach

    {{-- Configure Roles: permission matrix --}}
    <x-ui.modal name="configure-roles" title="Configure Roles" width="900">
        <form method="POST" action="{{ route('roles.permissions.update') }}">
            @csrf @method('PUT')
            <div class="max-h-[520px] overflow-y-auto">
                <table class="w-full text-left text-[14px]">
                    <thead class="sticky top-0 bg-white text-muted">
                        <tr class="h-[39px] font-medium">
                            <th class="pl-[4px]">Permission</th>
                            @foreach ($roles as $role)
                                <th class="w-[150px] text-center">{{ $role->short_name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($permissions as $group => $groupPermissions)
                        <tbody>
                            <tr><th colspan="{{ 1 + $roles->count() }}" class="divider pt-[10px] pb-[4px] pl-[4px] font-bold">{{ $group }}</th></tr>
                            @foreach ($groupPermissions as $permission)
                                <tr class="h-[36px] font-bold">
                                    <td class="pl-[4px]">{{ $permission->label }}</td>
                                    @foreach ($roles as $role)
                                        @php($locked = $role->slug === \App\Models\Role::ADMIN && in_array($permission->slug, \App\Support\PermissionCatalog::LOCKED_FOR_ADMIN, true))
                                        <td class="text-center">
                                            <input type="checkbox" name="permissions[{{ $role->id }}][]" value="{{ $permission->slug }}"
                                                   @checked($locked || $role->permissions->contains('slug', $permission->slug))
                                                   @disabled($locked) aria-label="{{ $role->name }}: {{ $permission->label }}"
                                                   class="size-[18px] accent-brand">
                                            @if ($locked)
                                                <input type="hidden" name="permissions[{{ $role->id }}][]" value="{{ $permission->slug }}">
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
            <x-ui.gradient-button class="mt-[20px]">Save Role Permissions</x-ui.gradient-button>
        </form>
    </x-ui.modal>
</x-layouts.app>
```

`resources/views/users/partials/role-status-fields.blade.php` (Role and Status as inline-label selects in the Figma field style):
```blade
@php($roles = \App\Models\Role::orderBy('id')->get())
@php($error = ($failed ?? true) ? $errors->getBag($bag)->first('role_id') : null)
<div>
    <label class="flex h-[44px] items-center rounded-[10px] border bg-white px-[16px] text-[14px] font-bold {{ $error ? 'border-bad' : 'border-field' }}">
        <span class="shrink-0">Role:</span>
        <select name="role_id" class="ml-[6px] h-full flex-1 cursor-pointer bg-transparent font-bold outline-none">
            <option value="">No Role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $roleId === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </label>
    @if ($error)
        <p class="mt-[4px] pl-[4px] text-[12px] font-semibold text-danger">{{ $error }}</p>
    @endif
</div>
<label class="flex h-[44px] items-center rounded-[10px] border border-field bg-white px-[16px] text-[14px] font-bold">
    <span class="shrink-0">Status:</span>
    <select name="status" class="ml-[6px] h-full flex-1 cursor-pointer bg-transparent font-bold outline-none">
        <option value="active" @selected($status === 'active')>Active</option>
        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
    </select>
</label>
```

- [ ] **Step 7: Run the full suite and build**

Run: `./vendor/bin/pest`
Expected: all pass.

Run: `npm run build`. Then manually log in as `Admin_01`, click **Add User** (quick action), add an account, edit it, open **Configure Roles**, untick one box for Agricultural Tech, save, and confirm the three `Added/Updated User` and `Configured Roles` rows appear on `/audit-trail`.

- [ ] **Step 8: Commit**

```bash
git add app resources routes tests
git commit -m "feat: add User Management with add/edit user and Configure Roles matrix

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Visual comparison harness and Phase 1–2 fidelity pass

**Files:**
- Create: `tests/visual/playwright.config.js`, `tests/visual/screens.spec.js`, `tests/visual/figma/*.png`, `tests/visual/.gitignore`
- Modify: `app/Providers/AppServiceProvider.php` (frozen clock for visual runs), `package.json` (script), `.env.example`
- Test: the visual run itself (report of RMSE per screen and diff images)

**Interfaces:**
- Consumes: seeded accounts (`UserSeeder`), `config('agapay.seed_password')`, all routes above.
- Produces: `npm run visual` writes `tests/visual/out/<frame>.png`, `tests/visual/out/<frame>.diff.png`, and `tests/visual/out/report.json` (`[{frame, route, rmse}]`). Later phases append screens to the `SCREENS` list.

- [ ] **Step 1: Freeze the clock for visual runs only**

In `app/Providers/AppServiceProvider.php` `boot()`, first line:
```php
        // Visual regression runs render the Figma date ("Wed, July 22"); never active in production.
        if (! $this->app->isProduction() && ($frozen = env('AGAPAY_FROZEN_NOW'))) {
            \Illuminate\Support\Carbon::setTestNow($frozen);
        }
```

- [ ] **Step 2: Create the visual database and export Figma references**

```bash
/x/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS agapay_visual CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

With the Figma MCP, call `get_screenshot` (fileKey `ZYDqjMYN1h4OBNbQUpadrk`, `maxDimension: 1820`) for frames `482:339`, `310:2`, `310:844`, `237:1470`, `237:1659`, `329:695`, `329:904`, `470:986`. Download each returned URL to `tests/visual/figma/<frame id with ':' replaced by '-'>.png` (e.g. `482-339.png`) and confirm each is 1820×1024 with `magick identify`.

- [ ] **Step 3: Write the Playwright config and spec**

`tests/visual/.gitignore`:
```
out/
```

`tests/visual/playwright.config.js`:
```js
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    timeout: 60_000,
    workers: 1,
    use: {
        baseURL: 'http://127.0.0.1:8123',
        channel: 'msedge',            // uses the installed Edge; no browser download needed
        viewport: { width: 1820, height: 1024 },
        deviceScaleFactor: 1,
    },
    webServer: {
        command: 'php artisan migrate:fresh --seed --force && php artisan serve --port=8123',
        cwd: '../..',
        url: 'http://127.0.0.1:8123/login',
        reuseExistingServer: false,
        env: { DB_DATABASE: 'agapay_visual', AGAPAY_FROZEN_NOW: '2026-07-22 09:00:00', APP_ENV: 'local' },
        timeout: 120_000,
    },
});
```

`tests/visual/screens.spec.js`:
```js
import { test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';

const PASSWORD = process.env.AGAPAY_SEED_PASSWORD ?? 'Agapay@2026';
const OUT = new URL('./out/', import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1');
const FIGMA = new URL('./figma/', import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1');

// frame: Figma node id, as: seeded username (null = guest), before: optional page action.
const SCREENS = [
    { frame: '482-339', route: '/login', as: null },
    { frame: '310-2', route: '/dashboard', as: 'Admin_01' },
    { frame: '310-844', route: '/dashboard', as: 'Admin_01', remember: ['Agritech_02', 'Encoder_03'],
      before: (page) => page.getByRole('button', { name: /Admin_01/ }).click() },
    { frame: '237-1470', route: '/dashboard', as: 'Agritech_02' },
    { frame: '237-1659', route: '/dashboard', as: 'Encoder_03' },
    { frame: '329-695', route: '/users', as: 'Admin_01' },
    { frame: '329-904', route: '/audit-trail', as: 'Admin_01' },
];

const results = [];
mkdirSync(OUT, { recursive: true });

async function login(page, username) {
    await page.goto('/login');
    await page.fill('#username', username);
    await page.fill('#password', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard');
}

// Submits the (hidden) account-menu logout form, CSRF token included.
async function logout(page) {
    await page.evaluate(() => document.querySelector('form[action$="/logout"]').submit());
    await page.waitForURL('**/login');
}

for (const screen of SCREENS) {
    test(`${screen.frame} ${screen.route}`, async ({ page }) => {
        for (const other of screen.remember ?? []) {   // puts these accounts in the remembered-accounts cookie
            await login(page, other);
            await logout(page);
        }
        if (screen.as) await login(page, screen.as);
        await page.goto(screen.route);
        await page.evaluate(() => document.fonts.ready);
        if (screen.before) await screen.before(page);

        const actual = `${OUT}${screen.frame}.png`;
        await page.screenshot({ path: actual, fullPage: false });

        let rmse = null;
        try {
            execFileSync('magick', ['compare', '-metric', 'RMSE', `${FIGMA}${screen.frame}.png`, actual, `${OUT}${screen.frame}.diff.png`], { stdio: 'pipe' });
            rmse = 0;
        } catch (error) {
            // magick exits 1 when images differ and prints "abs (normalized)" to stderr.
            const match = /\(([\d.e-]+)\)/.exec(String(error.stderr));
            rmse = match ? Number(match[1]) : null;
        }
        results.push({ frame: screen.frame, route: screen.route, rmse });
        writeFileSync(`${OUT}report.json`, JSON.stringify(results, null, 2));
    });
}
```

Add to `package.json` `scripts`:
```json
"visual": "playwright test --config tests/visual/playwright.config.js"
```

Add to `.env.example`:
```
# Only used by `npm run visual` (tests/visual); leave empty everywhere else.
AGAPAY_FROZEN_NOW=
```

- [ ] **Step 4: Run the harness**

Run: `npm run visual`
Expected: 7 passed; `tests/visual/out/report.json` lists an `rmse` per frame.

Because Phase 3 data isn't seeded yet, the Figma sample rows in the dashboard and users tables will show up in the diffs. Judge only the shell regions: header, sidebar, greeting, pills, search bar, card chrome, and the login screen.

- [ ] **Step 5: Fidelity pass**

For each frame, open `out/<frame>.png`, `figma/<frame>.png` and `out/<frame>.diff.png` side by side (in the in-app browser or any image viewer). Fix every structural mismatch in the shell: positions off by more than 2px, wrong sizes, wrong colors, missing assets, or wrapped text. Change only the Blade components or CSS from Tasks 1–7, and re-run `npm run visual` after each change. Accept anti-aliasing and font-rendering noise.

Known check-points:
- the `logo-box` gradient stroke vs Figma `310:5`/`482:349` (tune the `170deg` stops in `app.css`)
- nav icon offsets (Figma icons sit 14–20px from the item's left edge)
- account-menu row spacing vs `310:844`
- the Audit Trail frame shows only 3 quick-action pills in Figma while the dashboard shows 5. The build uses the dashboard's 5 on every Admin page. Note this in the report; it's a Figma inconsistency, not a bug.

- [ ] **Step 6: Final checks and commit**

Run: `./vendor/bin/pest && npm run build`
Expected: all tests pass, build OK.

```bash
git add tests/visual app/Providers/AppServiceProvider.php package.json .env.example resources
git commit -m "test: add Figma visual comparison harness and align shell with frames

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage (Phase 1–2 scope):** tokens and assets → T1; roles/permissions/configurable matrix → T2, T7; username login, inactive/no-role block, session timeout (env), account switcher with password → T3, T4; role-based nav, quick actions and stats → T4; audit trail of CRUD, login and logout, append-only → T5, T6; user management → T7; visual fidelity check → T8. Beneficiary, intervention, inventory, import/export, damage and report features belong to the Phase 3–8 plans; their nav links render as `#` until those routes exist, and their stat counters return 0 until their plans replace the `DashboardStats` arms.
- **Deviation from spec:** the spec's data model listed soft deletes on `users`. This plan uses deactivation (`status`) instead, because OMAG never needs to delete an account, and it keeps audit references intact.
- **Type consistency:** `Navigation::items/quickActions/stats`, `KnownAccounts::{remember, ids, others}`, `AuditLogger::record(action, subject, label, old, new, actor)`, and error bags `createUser`/`editUser` are used consistently across tasks.
