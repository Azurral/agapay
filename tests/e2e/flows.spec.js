import { test, expect } from '@playwright/test';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const PASSWORD = process.env.AGAPAY_SEED_PASSWORD ?? 'Agapay@2026';
const TODAY = new Date().toISOString().slice(0, 10);
const scratch = mkdtempSync(join(tmpdir(), 'agapay-e2e-'));

async function login(page, username) {
    await page.context().clearCookies();   // a signed-in user would be sent past /login
    await page.goto('/login');
    await page.fill('#username', username);
    await page.fill('#password', PASSWORD);
    await page.click('button[type=submit]');
    await page.waitForURL('**/dashboard');
}

/** Types into a farmer search box and picks the matching result. */
async function pickFarmer(page, name) {
    await page.getByPlaceholder(/Search by name|^Name$/).fill(name);
    await page.getByRole('button', { name: new RegExp(name) }).first().click();
}

test('each role lands on its own dashboard', async ({ page }) => {
    for (const [user, greeting, stat] of [
        ['Admin_01', 'Hello, Admin', 'Total Beneficiaries'],
        ['Agritech_02', 'Hello, Agritech', 'Pending Validation'],
        ['Encoder_03', 'Hello, Encoder', 'Encoded This Month'],
    ]) {
        await login(page, user);
        await expect(page.getByText(greeting)).toBeVisible();
        await expect(page.getByText(stat, { exact: true }).first()).toBeVisible();
    }
});

test('an encoder registers a farmer who waits for OMAG validation', async ({ page }) => {
    await login(page, 'Encoder_03');
    await page.goto('/rsbsa/register');
    await page.fill('[name=first_name]', 'Lito');
    await page.fill('[name=last_name]', 'Bayang');
    await page.fill('[name=birthdate]', '1979-04-12');
    await page.fill('[name=address]', 'Purok 4');
    await page.selectOption('[name=barangay_id]', { label: 'Samoki' });
    await page.getByRole('button', { name: /Submit Registration/ }).click();

    await expect(page.getByText('Lito Bayang was registered and is awaiting OMAG validation.')).toBeVisible();

    await login(page, 'Admin_01');
    await page.goto('/rsbsa/register');
    await expect(page.getByText('Lito Bayang').first()).toBeVisible();
});

test('recording a distribution lowers the stock', async ({ page }) => {
    await login(page, 'Encoder_03');
    await page.goto('/inventory');
    // Inventory table row: Item, Unit, Stock-In, Stock-Out, Balance ("33 · Low").
    const balance = async () => parseInt(await page.locator('div.grid').filter({ has: page.locator('span', { hasText: /^Certified Rice Seeds$/ }) })
        .first().locator('span').nth(4).innerText(), 10);
    const before = await balance();

    await page.goto('/intervention-records/create');
    await pickFarmer(page, 'Liza Domingo');
    await page.selectOption('[name=source]', 'da');
    await page.selectOption('[name=intervention_id]', { label: 'Certified Rice Seeds' });
    await page.fill('[name=quantity]', '1');
    await page.selectOption('[name=distribution_status]', 'distributed');
    await page.fill('[name=date_distributed]', TODAY);
    await page.getByRole('button', { name: 'Save Intervention Record' }).click();
    await expect(page.getByText('Intervention record saved.')).toBeVisible();

    await page.goto('/inventory');
    expect(await balance()).toBe(before - 1);
});

test('an encoder imports a masterlist', async ({ page }) => {
    const file = join(scratch, 'masterlist.csv');
    writeFileSync(file, 'Name,Birthdate,Barangay,RSBSA No.\nRamil Kinaw,1980-05-10,Samoki,RSBSA-7001\n');

    await login(page, 'Encoder_03');
    await page.goto('/import');
    await page.setInputFiles('input[name=file]', file);
    await expect(page.getByText('1 row matched automatically')).toBeVisible();
    await page.getByRole('button', { name: /Confirm & Import/ }).click();

    await expect(page.getByText('Imported 1 new profile, updated 0, added 0 intervention records.')).toBeVisible();
});

test('a damage report with a photo is filed and validated', async ({ page }) => {
    const photo = join(scratch, 'field.png');
    writeFileSync(photo, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64'));

    await login(page, 'Encoder_03');
    await page.goto('/damage-reports/create');
    await pickFarmer(page, 'Ana Dela Cruz');
    await page.selectOption('[name=crop_id]', { label: 'Rice' });
    await page.selectOption('[name=crop_stage]', 'vegetative');
    await page.fill('[name=total_area_ha]', '0.5');
    await page.setInputFiles('input[name="photos[]"]', photo);
    await page.getByRole('button', { name: 'Submit Damage Report' }).click();

    await expect(page.getByText('Damage report filed for Ana Dela Cruz.')).toBeVisible();
    const reportUrl = page.url();

    await login(page, 'Agritech_02');
    await page.goto(reportUrl);
    await page.getByRole('button', { name: 'Validate Report' }).click();
    await expect(page.getByText('Damage report validated.')).toBeVisible();
});

test('the administrator downloads a distribution report', async ({ page }) => {
    await login(page, 'Admin_01');
    await page.goto('/reports');
    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: /Generate Report/ }).click();

    expect((await download).suggestedFilename()).toMatch(/^agapay-report-2026-q3-all-\d{4}-\d{2}-\d{2}\.pdf$/);
    await page.goto('/reports');
    await expect(page.getByText('Generated Reports')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Download' }).first()).toBeVisible();
});
