import { test, expect } from '@playwright/test';
import { join } from 'node:path';

// Rehearses the defense script's live demo (Part 5) in order, from the first sign-in after installing to the audit
// trail. Each test builds on the previous one, so the messages quoted in the script are checked here.
test.describe.configure({ mode: 'serial' });

const ADMIN = ['omag_admin', 'Agapay#2026'];
const ENCODER = ['encoder1', 'Encoder#2026'];
const AGRITECH = ['agritech1', 'Agritech#2026'];
const START = 'Start#2026';
const fixture = (name) => join(process.cwd(), 'tests', 'e2e', 'fixtures', name);
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const TODAY = ymd(new Date());
const yearsAgo = (years) => { const d = new Date(); d.setFullYear(d.getFullYear() - years); return ymd(d); };

async function login(page, [username, password]) {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.fill('#username', username);
    await page.fill('#password', password);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
}

async function pickFarmer(page, name) {
    await page.getByPlaceholder(/Search by name|^Name$/).fill(name);
    await page.getByRole('button', { name: new RegExp(name) }).first().click();
}

async function openProfile(page, name) {
    await page.goto('/search?q=' + encodeURIComponent(name));
    await page.getByRole('listitem').filter({ hasText: name }).first().click();
}

async function balance(page, item) {
    await page.goto('/inventory');
    const row = page.locator('div.grid').filter({ has: page.locator('span', { hasText: new RegExp(`^${item}$`) }) }).first();
    return parseFloat(await row.locator('span').nth(4).innerText());
}

test('5.2 landing page and sign-in', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('link', { name: 'Log in to Agapay' }).click();
    await page.fill('#username', ADMIN[0]);
    await page.fill('#password', 'wrong-password');
    await page.getByRole('button', { name: 'Show password' }).click();
    await expect(page.locator('#password')).toHaveAttribute('type', 'text');
    await page.click('button[type=submit]');
    await expect(page.getByText('These credentials do not match our records.')).toBeVisible();

    await login(page, ADMIN);
    await expect(page.getByText('Hello, Admin')).toBeVisible();
});

test('5.3 first setup: cycle, programs, stock, crop values, crisis', async ({ page }) => {
    await login(page, ADMIN);
    await page.goto('/distribution-cycles');
    await expect(page.getByText('No distribution cycles yet.')).toBeVisible();
    await page.getByRole('button', { name: '+ Add Cycle' }).click();
    const cycle = page.locator('[role=dialog][aria-label="Distribution Cycle"] form');
    await cycle.locator('[name=code]').fill('2026-Q4');
    await cycle.locator('[name=schedule_date]').fill(TODAY);
    await cycle.locator('[name=label]').fill('2026-Q4 Wet Season');
    await cycle.locator('[name=venue]').fill('Bontoc Municipal Gym');
    await cycle.locator('[name=status]').selectOption('ongoing');
    await cycle.getByRole('button', { name: 'Add Cycle' }).click();
    await expect(page.getByText('2026-Q4 Wet Season added.')).toBeVisible();

    await page.goto('/programs');
    await expect(page.getByText('Certified Rice Seeds').first()).toBeVisible();
    await expect(page.getByText('Municipal Cash Subsidy')).toBeVisible();

    await page.goto('/inventory');
    await page.getByRole('button', { name: 'Manage Items' }).click();
    const items = page.locator('[role=dialog][aria-label="Manage Inventory Items"]');
    await items.locator('[name=name]').fill('Certified Rice Seeds');
    await items.locator('[name=unit]').fill('sack');
    await items.locator('[name=unit_label]').fill('sacks');
    await items.locator('[name=low_stock_threshold]').fill('40');
    await items.getByLabel('DA - Certified Rice Seeds').check();
    await items.getByRole('button', { name: 'Add Item' }).click();
    await expect(page.getByText('Certified Rice Seeds saved.')).toBeVisible();

    await page.getByRole('button', { name: '+ Record Movement' }).click();
    const movement = page.locator('[role=dialog][aria-label="Record Stock Movement"]');
    const option = movement.locator('[name=inventory_item_id] option').filter({ hasText: 'Certified Rice Seeds' }).first();
    await movement.locator('[name=inventory_item_id]').selectOption(await option.getAttribute('value'));
    await movement.locator('[name=direction]').selectOption('in');
    await movement.locator('[name=quantity]').fill('50');
    await movement.locator('[name=notes]').fill('Starting stock');
    await movement.getByRole('button', { name: 'Save Stock Movement' }).click();
    await expect(page.getByText('+50 sacks · Certified Rice Seeds recorded.')).toBeVisible();
    expect(await balance(page, 'Certified Rice Seeds')).toBe(50);

    await page.goto('/damage-reports');
    await page.getByRole('button', { name: 'Crop Values' }).click();
    await expect(page.locator('[role=dialog][aria-label="Crop Values"]').getByText('Rice', { exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: '+ Add Crisis' }).click();
    await page.getByPlaceholder('e.g. Rain-Induced Landslide').fill('Typhoon Kristine');
    await page.locator('[role=dialog][aria-label="Add Crisis"] [name=occurred_on]').fill(TODAY);
    await page.locator('[role=dialog][aria-label="Add Crisis"]').getByRole('button', { name: 'Add Crisis' }).click();
    await expect(page.getByText('Typhoon Kristine added.')).toBeVisible();
});

test('5.4 staff accounts and the first sign-in', async ({ page }) => {
    await login(page, ADMIN);
    for (const [last, first, [username], role] of [['Ngalob', 'Liza', ENCODER, 'Data Encoder'], ['Bangsoy', 'Mark', AGRITECH, 'Agricultural Technologist']]) {
        await page.goto('/users?add=1');
        const form = page.locator('[role=dialog][aria-label="Add User"] form');
        await form.locator('[name=last_name]').fill(last);
        await form.locator('[name=first_name]').fill(first);
        await form.locator('[name=username]').fill(username);
        await form.locator('[name=password]').fill(START);
        await form.locator('[name=password_confirmation]').fill(START);
        // The list shows short names (Encoder, AgriTech); the full name is the option's tooltip.
        await form.locator('[name=role_id]').selectOption(await form.locator(`[name=role_id] option[title="${role}"]`).getAttribute('value'));
        await form.getByRole('button', { name: 'Save User' }).click();
        await expect(page.getByText(`${username} was added.`)).toBeVisible();
    }

    for (const [username, password] of [ENCODER, AGRITECH]) {
        await login(page, [username, START]);
        await expect(page).toHaveURL(/\/account\/password$/);
        await page.fill('[name=current_password]', START);
        await page.fill('[name=password]', password);
        await page.fill('[name=password_confirmation]', password);
        await page.getByRole('button', { name: 'Save New Password' }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: /^Hello, / })).toBeVisible();
    }
});

test('5.5 adding farmers: age, duplicate, address parts, N/A, consent', async ({ page }) => {
    await login(page, ENCODER);
    const fill = async (first, last, born, sitio, barangay, extra = {}) => {
        await page.goto('/beneficiaries/create');
        await page.fill('[name=first_name]', first);
        await page.fill('[name=last_name]', last);
        await page.fill('[name=birthdate]', born);
        await page.fill('[name=sitio]', sitio);
        await page.selectOption('[name=barangay_id]', { label: barangay });
        for (const [name, value] of Object.entries(extra)) await page.fill(`[name=${name}]`, value);
        await page.check('[name=consent]');
        await page.getByRole('button', { name: /Add Beneficiary/ }).last().click();
    };

    await fill('Rosita', 'Fagyao', yearsAgo(16), 'Purok 4', 'Samoki');
    await expect(page.getByText('Applicant must be at least 18 years old.')).toBeVisible();

    await fill('Juan', 'Dela Cruz', '1981-03-15', 'Purok 3', 'Poblacion', { rsbsa_number: 'RSBSA-0231', farm_area_ha: '1.5' });
    await expect(page.getByText('Juan Dela Cruz was added.')).toBeVisible();
    await fill('Juan', 'Dela Cruz', '1981-03-15', 'Purok 3', 'Poblacion');
    await expect(page.getByText('This person is already registered.')).toBeVisible();

    await fill('Rosita', 'Fagyao', '1979-04-12', 'Purok 4', 'Samoki', { house_no: '8', street: 'Rizal St.', farm_area_ha: '1.25' });
    await expect(page.getByText('Rosita Fagyao was added.')).toBeVisible();
    await expect(page.getByText(/^Consent confirmed by .+ on /)).toBeVisible();
});

test('5.6 Excel import of the masterlist', async ({ page }) => {
    await login(page, ENCODER);
    await page.goto('/import');
    await page.setInputFiles('input[name=file]', fixture('demo-install-masterlist.xlsx'));
    await expect(page.getByText("Unknown barangay 'Atlantis'")).toBeVisible();
    await expect(page.getByText('Missing birthdate')).toBeVisible();
    await expect(page.getByText(/no RSBSA No\. \(DA programs need one/).first()).toBeVisible();
    await page.check('[name=consent]');
    await page.getByRole('button', { name: /Confirm & Import/ }).click();
    await expect(page.getByText(/^Imported 4 new profiles/)).toBeVisible();
});

test('5.7 the LGU list shows every farmer', async ({ page }) => {
    await login(page, ADMIN);
    await page.goto('/interventions/lgu');
    const rosita = page.locator('div.grid').filter({ hasText: 'Rosita Fagyao' }).first();
    await expect(rosita).toContainText('N/A');
    await expect(rosita).toContainText('1.25 ha');
    await page.getByRole('link', { name: 'Program Records' }).click();
    // Pablo's program came in with the import.
    await expect(page.locator('div.grid').filter({ hasText: 'Pablo Ramos' }).filter({ hasText: 'Emergency Seedlings' })).toHaveCount(1);
});

test('5.8 giving programs: DA needs an RSBSA No.', async ({ page }) => {
    await login(page, ENCODER);
    const add = async (farmer, source, program, quantity = '') => {
        await page.goto('/intervention-records/create');
        await pickFarmer(page, farmer);
        await page.selectOption('[name=source]', source);
        await page.selectOption('[name=intervention_id]', { label: program });
        if (quantity) await page.fill('[name=quantity]', quantity);
        await page.selectOption('[name=distribution_status]', 'not_distributed');
        await page.getByRole('button', { name: 'Save Intervention Record' }).click();
    };

    await add('Rosita Fagyao', 'da', 'Certified Rice Seeds');
    await expect(page.getByText("Rosita Fagyao has no RSBSA No. DA programs need one; LGU programs don't.")).toBeVisible();
    for (const farmer of ['Maria Dela Cruz', 'Ana Dela Cruz']) {
        await add(farmer, 'lgu', 'Municipal Cash Subsidy');
        await expect(page.getByText('Intervention record saved.')).toBeVisible();
    }
    await add('Juan Dela Cruz', 'da', 'Certified Rice Seeds', '2');
    await expect(page.getByText('Intervention record saved.')).toBeVisible();
});

test('5.9 releasing: stock deduction and the household rule', async ({ page }) => {
    await login(page, ADMIN);
    const processClaim = async (name, program, override = null) => {
        await openProfile(page, name);
        await page.getByRole('button', { name: 'Process Claim' }).click();
        const modal = page.locator('form').filter({ has: page.getByRole('button', { name: 'Confirm Claim' }) });
        const option = modal.locator('select').first().locator('option', { hasText: program });
        await modal.locator('select').first().selectOption(await option.getAttribute('value'));
        if (override) await modal.locator('[name=override_reason]').fill(override);
        await modal.getByRole('button', { name: 'Confirm Claim' }).click();
    };

    await processClaim('Juan Dela Cruz', 'Certified Rice Seeds');
    await expect(page.getByText(/Certified Rice Seeds \(2026-Q4\): Claimed/).first()).toBeVisible();
    expect(await balance(page, 'Certified Rice Seeds')).toBe(48);
    await expect(page.getByText(/Auto-deducted: distribution to Juan Dela Cruz/).first()).toBeVisible();

    await processClaim('Maria Dela Cruz', 'Municipal Cash Subsidy');
    await expect(page.getByText(/Municipal Cash Subsidy \(2026-Q4\): Claimed/).first()).toBeVisible();
    await processClaim('Ana Dela Cruz', 'Municipal Cash Subsidy');
    await expect(page.getByText('Maria Dela Cruz already claimed Municipal Cash Subsidy for this household in 2026-Q4.').first()).toBeVisible();
    const modal = page.locator('form').filter({ has: page.getByRole('button', { name: 'Confirm Claim' }) });
    await modal.locator('[name=override_reason]').fill('Ana farms her own separate parcel; verified on site.');
    await modal.getByRole('button', { name: 'Confirm Claim' }).click();
    await expect(page.getByText(/Municipal Cash Subsidy \(2026-Q4\): Claimed/).first()).toBeVisible();
});

test('5.10 crisis report: kept photo, filing, validation, charts', async ({ page }) => {
    await login(page, AGRITECH);
    await page.goto('/damage-reports/create');
    await page.selectOption('[name=disaster_id]', { label: 'Typhoon Kristine' });
    await page.selectOption('[name=crop_id]', { label: 'Rice' });
    await page.selectOption('[name=crop_stage]', 'vegetative');
    await pickFarmer(page, 'Pablo Ramos');
    await page.setInputFiles('input[name="photos[]"]', join(process.cwd(), 'defense', 'demo-field-photo.jpg'));
    await page.getByRole('button', { name: 'Submit Damage Report' }).click();
    await expect(page.getByText('Enter the damaged area.')).toBeVisible();
    await expect(page.getByText(/Your photos are kept/)).toBeVisible();
    await page.fill('[name=total_area_ha]', '0.5');
    await page.getByRole('button', { name: 'Submit Damage Report' }).click();
    await expect(page.getByText('Damage report filed for Pablo Ramos.')).toBeVisible();
    await page.getByRole('button', { name: 'Validate Report' }).click();
    await expect(page.getByText('Damage report validated.')).toBeVisible();

    await login(page, ADMIN);
    await page.goto('/damage-reports');
    await expect(page.getByText('Cost of Damage by Barangay')).toBeVisible();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: /Generate PDF Report/ }).first().click();
    expect((await download).suggestedFilename()).toMatch(/\.pdf$/);
});

test('5.11 assistance request: filed and approved', async ({ page }) => {
    await login(page, ENCODER);
    await page.getByRole('link', { name: 'New Request' }).click();
    await pickFarmer(page, 'Gloria Ramos');
    await page.selectOption('[name=source]', 'lgu');
    await page.selectOption('[name=intervention_id]', { label: 'Emergency Seedlings' });
    await page.selectOption('[name=disaster_id]', { label: 'Typhoon Kristine' });
    await page.fill('[name=reason]', 'Seedbed washed out by the typhoon');
    await page.getByRole('button', { name: 'File Request' }).click();
    await expect(page.getByText('Request filed for Gloria Ramos.')).toBeVisible();

    await login(page, ADMIN);
    await page.goto('/interventions/lgu?tab=requests');
    await expect(page.getByText('By Barangay')).toBeVisible();
    const row = page.locator('div.grid').filter({ hasText: 'Gloria Ramos' }).filter({ hasText: 'Emergency Seedlings' }).first();
    await row.getByRole('button', { name: 'Approve' }).click();
    await expect(page.getByText('Approved: Gloria Ramos - Emergency Seedlings added to 2026-Q4.')).toBeVisible();
});

test('5.12 download reports and the export list', async ({ page }) => {
    await login(page, ADMIN);
    await page.goto('/reports');
    const report = page.waitForEvent('download');
    await page.getByRole('button', { name: /Generate Report/ }).click();
    expect((await report).suggestedFilename()).toMatch(/^agapay-report-2026-q4-all-.*\.pdf$/);

    await page.goto('/export');
    const list = page.waitForEvent('download');
    await page.getByRole('link', { name: /Export as \.xlsx/ }).click();
    expect((await list).suggestedFilename()).toMatch(/^agapay-beneficiaries-.*\.xlsx$/);
});

test('5.13 audit trail', async ({ page }) => {
    await login(page, ADMIN);
    for (const action of ['Claimed Intervention (Household Override)', 'Validated Damage Report']) {
        await page.goto('/audit-trail?action=' + encodeURIComponent(action));
        await expect(page.locator('td', { hasText: action }).first()).toBeVisible();
    }
});
