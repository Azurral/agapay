import { test, expect } from '@playwright/test';
import { join } from 'node:path';

// Rehearses the defense script (Part 5) on fresh sample data, in order: each test builds on the previous one.
test.describe.configure({ mode: 'serial' });

const PASSWORD = process.env.AGAPAY_SEED_PASSWORD ?? 'Agapay@2026';
const yearsAgo = (years) => { const d = new Date(); d.setFullYear(d.getFullYear() - years); return d.toISOString().slice(0, 10); };

async function login(page, username) {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.fill('#username', username);
    await page.fill('#password', PASSWORD);
    await page.click('button[type=submit]');
}

async function pickFarmer(page, name) {
    await page.getByPlaceholder(/Search by name|^Name$/).fill(name);
    await page.getByRole('button', { name: new RegExp(name) }).first().click();
}

async function openProfile(page, name) {
    await page.goto('/search?q=' + encodeURIComponent(name));
    await page.getByRole('listitem').filter({ hasText: name }).first().click();
}

test('5.1 landing page, sign-in and menu', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('main').getByText('Office of the Municipal Agriculturist').first()).toBeVisible();
    await expect(page.locator('.desktop-only-notice')).toBeHidden();
    await page.getByRole('link', { name: 'Log in to Agapay' }).click();

    await page.fill('#username', 'Admin_01');
    await page.fill('#password', 'wrong-password');
    await page.getByRole('button', { name: 'Show password' }).click();
    await expect(page.locator('#password')).toHaveAttribute('type', 'text');
    await page.click('button[type=submit]');
    await expect(page.getByRole('alert')).not.toBeEmpty();

    await login(page, 'Encoder_04');
    await expect(page.getByText('This account is inactive')).toBeVisible();

    await login(page, 'Admin_01');
    await page.waitForURL('**/dashboard');
    await page.getByRole('button', { name: 'Open menu' }).click();
    await page.locator('#side-panel').getByText('User Management', { exact: true }).click();
    await page.getByRole('button', { name: /Configure Roles/ }).click();
    await expect(page.getByRole('heading', { name: 'Configure Roles' })).toBeVisible();
});

test('5.2 adding a farmer: age, duplicate, address parts, N/A', async ({ page }) => {
    await login(page, 'Encoder_03');
    await openProfile(page, 'Juan Dela Cruz');
    const juanBirthdate = await page.locator('input[name=birthdate]').inputValue();

    const fill = async (first, last, birthdate, sitio, barangay) => {
        await page.goto('/beneficiaries/create');
        await page.fill('[name=first_name]', first);
        await page.fill('[name=last_name]', last);
        await page.fill('[name=birthdate]', birthdate);
        await page.fill('[name=sitio]', sitio);
        await page.selectOption('[name=barangay_id]', { label: barangay });
    };

    await fill('Rosita', 'Fagyao', yearsAgo(16), 'Purok 4', 'Samoki');
    await page.getByRole('button', { name: /Add Beneficiary/ }).last().click();
    await expect(page.getByText('Applicant must be at least 18 years old.')).toBeVisible();

    await fill('Juan', 'Dela Cruz', juanBirthdate, 'Purok 3', 'Poblacion');
    await page.getByRole('button', { name: /Add Beneficiary/ }).last().click();
    await expect(page.getByText('This person is already registered.')).toBeVisible();

    await fill('Rosita', 'Fagyao', '1979-04-12', 'Purok 4', 'Samoki');
    await page.fill('[name=house_no]', '8');
    await page.fill('[name=street]', 'Rizal St.');
    await page.fill('[name=farm_area_ha]', '1.25');
    await expect(page.locator('#field-municipality')).toHaveValue('Bontoc');
    await page.getByRole('button', { name: /Add Beneficiary/ }).last().click();
    await expect(page.getByText('Rosita Fagyao was added.')).toBeVisible();
    // Encoders land on the profile in edit mode: the parts are separate fields there.
    await expect(page.locator('input[name=house_no]')).toHaveValue('8');
    await expect(page.locator('input[name=sitio]')).toHaveValue('Purok 4');
    await expect(page.locator('input[name=rsbsa_number]')).toHaveValue('');
});

test('5.3 the LGU list shows every farmer', async ({ page }) => {
    await login(page, 'Admin_01');
    await page.goto('/interventions/lgu');
    const row = page.locator('div.grid').filter({ hasText: 'Rosita Fagyao' }).first();
    await expect(row).toContainText('N/A');
    await expect(row).toContainText('1.25 ha');
    await page.getByRole('link', { name: 'Program Records' }).click();
    await expect(page.getByText('Qty / Unit')).toBeVisible();
});

test('5.4 giving programs: DA needs an RSBSA number', async ({ page }) => {
    await login(page, 'Encoder_03');
    const add = async (farmer, source, program) => {
        await page.goto('/intervention-records/create');
        await pickFarmer(page, farmer);
        await page.selectOption('[name=source]', source);
        await page.selectOption('[name=intervention_id]', { label: program });
        await page.selectOption('[name=distribution_status]', 'not_distributed');
        await page.getByRole('button', { name: 'Save Intervention Record' }).click();
    };

    await add('Rosita Fagyao', 'da', 'Certified Rice Seeds');
    await expect(page.getByText("Rosita Fagyao has no RSBSA No. DA programs need one; LGU programs don't.")).toBeVisible();

    await add('Maria Dela Cruz', 'lgu', 'Municipal Cash Subsidy');
    await expect(page.getByText('Intervention record saved.')).toBeVisible();
    await add('Ana Dela Cruz', 'lgu', 'Municipal Cash Subsidy');
    await expect(page.getByText('Intervention record saved.')).toBeVisible();
});

test('5.5 releasing and the household rule with an override', async ({ page }) => {
    await login(page, 'Admin_01');
    const processClaim = async (name, override = null) => {
        await openProfile(page, name);
        await page.getByRole('button', { name: 'Process Claim' }).click();
        const modal = page.locator('form').filter({ has: page.getByRole('button', { name: 'Confirm Claim' }) });
        const option = modal.locator('select').first().locator('option', { hasText: 'Municipal Cash Subsidy' });
        await modal.locator('select').first().selectOption(await option.getAttribute('value'));
        if (override) {
            await modal.locator('[name=override_reason]').fill(override);
        }
        await modal.getByRole('button', { name: 'Confirm Claim' }).click();
    };

    await processClaim('Maria Dela Cruz');
    await expect(page.getByText(/Municipal Cash Subsidy \(2026-Q3\): Claimed/).first()).toBeVisible();

    await processClaim('Ana Dela Cruz');
    await expect(page.getByText('Maria Dela Cruz already claimed Municipal Cash Subsidy for this household in 2026-Q3.').first()).toBeVisible();

    // The refused claim keeps the Process Claim window open: only the override reason is added.
    const modal = page.locator('form').filter({ has: page.getByRole('button', { name: 'Confirm Claim' }) });
    await modal.locator('[name=override_reason]').fill('Ana farms her own separate parcel; verified on site.');
    await modal.getByRole('button', { name: 'Confirm Claim' }).click();
    await expect(page.getByText(/Municipal Cash Subsidy \(2026-Q3\): Claimed/).first()).toBeVisible();
});

test('5.6 inventory shows the low rice seed stock', async ({ page }) => {
    await login(page, 'Admin_01');
    await page.goto('/inventory');
    const rice = page.locator('div.grid').filter({ has: page.locator('span', { hasText: /^Certified Rice Seeds$/ }) }).first();
    await expect(rice).toContainText('34');
    await expect(rice).toContainText('Low');
});

test('5.7 Excel import and the export list', async ({ page }) => {
    await login(page, 'Encoder_03');
    await page.goto('/import');
    await page.setInputFiles('input[name=file]', join(process.cwd(), 'tests', 'e2e', 'fixtures', 'demo-masterlist.xlsx'));
    await expect(page.getByText(/excluded/).first()).toBeVisible();
    await expect(page.getByText("Unknown barangay 'Atlantis'")).toBeVisible();
    await expect(page.getByText('Missing birthdate')).toBeVisible();
    await expect(page.getByText(/no RSBSA No\. \(DA programs need one/).first()).toBeVisible();
    await page.getByRole('button', { name: /Confirm & Import/ }).click();
    await expect(page.getByText(/Imported 2 new profiles/)).toBeVisible();

    await login(page, 'Admin_01');
    await page.goto('/export');
    await expect(page.getByText('Export Beneficiary List', { exact: true })).toBeVisible();
    await expect(page.getByText('Claim Status').first()).toBeVisible();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: /Export as \.xlsx/ }).click();
    expect((await download).suggestedFilename()).toMatch(/^agapay-beneficiaries-.*\.xlsx$/);
});

test('5.8 crisis report: kept photo, filing, validation, crises list', async ({ page }) => {
    await login(page, 'Agritech_02');
    await page.goto('/damage-reports/create');
    await page.selectOption('[name=disaster_id]', { label: 'Southwest Monsoon Flooding' });
    await page.selectOption('[name=crop_id]', { label: 'Rice' });
    await page.selectOption('[name=crop_stage]', 'vegetative');
    await pickFarmer(page, 'Pablo Ramos');
    // No damaged area: the server refuses the form, and the photo is kept for the next try.
    await page.setInputFiles('input[name="photos[]"]', join(process.cwd(), 'tests', 'e2e', 'fixtures', 'demo-field-photo.jpg'));
    await page.getByRole('button', { name: 'Submit Damage Report' }).click();
    await expect(page.getByText('Enter the damaged area.')).toBeVisible();
    await expect(page.getByText(/Your photos are kept/)).toBeVisible();

    await page.fill('[name=total_area_ha]', '0.5');
    await page.getByRole('button', { name: 'Submit Damage Report' }).click();
    await expect(page.getByText('Damage report filed for Pablo Ramos.')).toBeVisible();
    await expect(page.locator('img[alt="demo-field-photo.jpg"]')).toBeVisible();
    await page.getByRole('button', { name: 'Validate Report' }).click();
    await expect(page.getByText('Damage report validated.')).toBeVisible();

    await login(page, 'Admin_01');
    await page.goto('/damage-reports');
    await page.getByRole('button', { name: /Crises & Crop Values/ }).click();
    await expect(page.getByPlaceholder('e.g. Rain-Induced Landslide')).toBeVisible();
});

test('5.9 to 5.11 cycles, download reports and the audit trail', async ({ page }) => {
    await login(page, 'Admin_01');
    await page.goto('/interventions');
    await page.getByRole('link', { name: /Distribution Cycles/ }).click();
    await expect(page.getByText('2026-Q3').first()).toBeVisible();

    await page.goto('/reports');
    await expect(page.getByText('DOWNLOAD REPORTS', { exact: true }).first()).toBeVisible();
    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: /Generate Report/ }).click();
    expect((await download).suggestedFilename()).toMatch(/\.pdf$/);

    // The Action filter finds an entry however many came after it.
    await page.goto('/audit-trail?action=' + encodeURIComponent('Claimed Intervention (Household Override)'));
    await expect(page.locator('td', { hasText: 'Claimed Intervention (Household Override)' }).first()).toBeVisible();
    await page.goto('/audit-trail?action=' + encodeURIComponent('Validated Damage Report'));
    await expect(page.locator('td', { hasText: 'Validated Damage Report' }).first()).toBeVisible();
});
