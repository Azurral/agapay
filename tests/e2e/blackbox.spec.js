import { test, expect } from '@playwright/test';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

// Black-box test cases for the manuscript (section 3.4): each case drives AGAPAY only through its pages, like a user,
// and records its input, expected result and what the system actually showed (test annotations, read from the JSON
// report). Cases run in order on fresh sample data; later cases build on earlier ones.
test.describe.configure({ mode: 'serial' });

const PASSWORD = process.env.AGAPAY_SEED_PASSWORD ?? 'Agapay@2026';
// Local dates (not toISOString, which is UTC: before 8 a.m. in the Philippines it gives yesterday).
const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const TODAY = ymd(new Date());
const scratch = mkdtempSync(join(tmpdir(), 'agapay-bb-'));
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

/** A date `years` years before today, moved by `days` (e.g. -1 = one day short of the birthday). */
const birthdate = (years, days = 0) => { const d = new Date(); d.setFullYear(d.getFullYear() - years); d.setDate(d.getDate() + days); return ymd(d); };

/** Records the case's test data for the report. */
function testCase({ module, input, expected, kind = 'Functional' }) {
    const info = test.info().annotations;
    info.push({ type: 'module', description: module }, { type: 'kind', description: kind }, { type: 'input', description: input }, { type: 'expected', description: expected });
}
function actual(text) { test.info().annotations.push({ type: 'actual', description: text }); }

/** Waits for a message on the page and returns its text. */
async function shown(page, text) {
    const el = page.getByText(text).first();
    await expect(el).toBeVisible();
    return (await el.innerText()).replace(/\s+/g, ' ').trim();
}

async function login(page, username, password = PASSWORD) {
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

/** Picks the option whose text starts with `text`. */
async function selectStarting(select, text) {
    const option = select.locator('option').filter({ hasText: new RegExp(`^${text}`) }).first();
    await select.selectOption(await option.getAttribute('value'));
}

/** Lets the server's own validation answer instead of the browser's form checks. */
async function skipBrowserChecks(page) {
    await page.evaluate(() => document.querySelectorAll('form').forEach((form) => { form.noValidate = true; }));
}

async function fillFarmer(page, { first, last, born = '1980-03-15', rsbsa = '', sitio = 'Purok 2', barangay = 'Samoki', area = '', consent = true }) {
    await page.goto('/beneficiaries/create');
    await page.fill('[name=first_name]', first);
    await page.fill('[name=last_name]', last);
    await page.fill('[name=birthdate]', born);
    await page.fill('[name=sitio]', sitio);
    await page.selectOption('[name=barangay_id]', { label: barangay });
    if (rsbsa) await page.fill('[name=rsbsa_number]', rsbsa);
    if (area !== '') await page.fill('[name=farm_area_ha]', area);
    if (consent) await page.check('[name=consent]');
}
const addFarmer = async (page) => { await skipBrowserChecks(page); await page.getByRole('button', { name: /Add Beneficiary/ }).last().click(); };

async function addRecord(page, farmer, source, program, { distributed = false, quantity = '' } = {}) {
    await page.goto('/intervention-records/create');
    await pickFarmer(page, farmer);
    await page.selectOption('[name=source]', source);
    await page.selectOption('[name=intervention_id]', { label: program });
    if (quantity !== '') await page.fill('[name=quantity]', quantity);
    await page.selectOption('[name=distribution_status]', distributed ? 'distributed' : 'not_distributed');
    if (distributed) await page.fill('[name=date_distributed]', TODAY);
    await page.getByRole('button', { name: 'Save Intervention Record' }).click();
}

async function openProfile(page, name) {
    await page.goto('/search?q=' + encodeURIComponent(name));
    await page.getByRole('listitem').filter({ hasText: name }).first().click();
}

/** Administrator's Process Claim on a farmer's profile for the given program. */
async function processClaim(page, farmer, program, fields = {}) {
    await openProfile(page, farmer);
    await page.getByRole('button', { name: 'Process Claim' }).click();
    const modal = page.locator('form').filter({ has: page.getByRole('button', { name: 'Confirm Claim' }) });
    const option = modal.locator('select').first().locator('option', { hasText: program });
    await modal.locator('select').first().selectOption(await option.first().getAttribute('value'));
    for (const [name, value] of Object.entries(fields)) await modal.locator(`[name=${name}]`).fill(value);
    await modal.getByRole('button', { name: 'Confirm Claim' }).click();
}

/** The Balance column of an inventory item. */
async function stock(page, item) {
    await page.goto('/inventory');
    const row = page.locator('div.grid').filter({ has: page.locator('span', { hasText: new RegExp(`^${item}$`) }) }).first();
    return parseFloat(await row.locator('span').nth(4).innerText());
}

// ---------------------------------------------------------------- Login

test('BB-01 Login: valid credentials', async ({ page }) => {
    testCase({ module: 'Login', input: 'Username Admin_01, correct password', expected: 'The user is signed in and sees the Administrator home page.' });
    await login(page, 'Admin_01');
    await expect(page).toHaveURL(/\/dashboard$/);
    actual(`Signed in; home page shows "${await shown(page, 'Hello, Admin')}".`);
});

test('BB-02 Login: wrong password', async ({ page }) => {
    testCase({ module: 'Login', input: 'Username Admin_01, password "wrong-password"', expected: 'Access is denied with an error message.' });
    await login(page, 'Admin_01', 'wrong-password');
    await expect(page).toHaveURL(/\/login$/);
    actual(`Stayed on the login page; message: "${(await page.getByRole('alert').first().innerText()).trim()}".`);
});

test('BB-03 Login: blank username and password', async ({ page }) => {
    testCase({ module: 'Login', kind: 'Equivalence partitioning', input: 'Both fields left blank', expected: 'Access is denied; both fields are asked for.' });
    await page.context().clearCookies();
    await page.goto('/login');
    await skipBrowserChecks(page);
    await page.click('button[type=submit]');
    await expect(page).toHaveURL(/\/login$/);
    actual(`Stayed on the login page; message: "${(await page.getByRole('alert').first().innerText()).replace(/\s+/g, ' ').trim()}".`);
});

test('BB-04 Login: inactive account', async ({ page }) => {
    testCase({ module: 'Login', input: 'Username Encoder_04 (status Inactive), correct password', expected: 'Access is denied because the account is inactive.' });
    await login(page, 'Encoder_04');
    actual(`Not signed in; message: "${await shown(page, /inactive/)}".`);
});

test('BB-05 Login: show password button', async ({ page }) => {
    testCase({ module: 'Login', input: 'Type a password, click the eye button', expected: 'The password becomes readable; clicking again hides it.' });
    await page.goto('/login');
    await page.fill('#password', 'secret12');
    await page.getByRole('button', { name: 'Show password' }).click();
    await expect(page.locator('#password')).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: 'Hide password' }).click();
    await expect(page.locator('#password')).toHaveAttribute('type', 'password');
    actual('Password shown as text after the first click and hidden again after the second.');
});

// ---------------------------------------------------------------- Beneficiaries

test('BB-06 Beneficiary: add with complete details', async ({ page }) => {
    testCase({ module: 'Beneficiary', input: 'Bryan Fagyao, born 1980-03-15, Purok 2, Samoki, RSBSA-9001, 1.5 ha, consent ticked', expected: 'The beneficiary record is saved and the profile opens.' });
    await login(page, 'Encoder_03');
    await fillFarmer(page, { first: 'Bryan', last: 'Fagyao', rsbsa: 'RSBSA-9001', area: '1.5' });
    await addFarmer(page);
    actual(`Saved; message "${await shown(page, 'Bryan Fagyao was added.')}" and the profile opened.`);
});

test('BB-07 Beneficiary: required fields left blank', async ({ page }) => {
    testCase({ module: 'Beneficiary', kind: 'Equivalence partitioning', input: 'All fields blank, consent not ticked', expected: 'Nothing is saved; each required field shows a message.' });
    await login(page, 'Encoder_03');
    await page.goto('/beneficiaries/create');
    await addFarmer(page);
    const messages = (await page.locator('p.text-danger').allInnerTexts()).map((m) => m.trim()).filter(Boolean);
    expect(messages.length).toBeGreaterThanOrEqual(4);
    actual(`Not saved; ${messages.length} messages: ${messages.map((m) => `"${m.trim()}"`).join(', ')}.`);
});

test('BB-08 Beneficiary: age one day short of 18', async ({ page }) => {
    testCase({ module: 'Beneficiary', kind: 'Boundary value', input: `Birthdate ${birthdate(18, 1)} (17 years, 364 days old)`, expected: 'Refused: the applicant must be at least 18 years old.' });
    await login(page, 'Encoder_03');
    await fillFarmer(page, { first: 'Joy', last: 'Kiwang', born: birthdate(18, 1) });
    await addFarmer(page);
    actual(`Not saved; message "${await shown(page, 'Applicant must be at least 18 years old.')}".`);
});

test('BB-09 Beneficiary: age exactly 18 today', async ({ page }) => {
    testCase({ module: 'Beneficiary', kind: 'Boundary value', input: `Birthdate ${birthdate(18)} (turns 18 today), no RSBSA No.`, expected: 'Accepted and saved; RSBSA No. shows N/A.' });
    await login(page, 'Encoder_03');
    await fillFarmer(page, { first: 'Joy', last: 'Kiwang', born: birthdate(18) });
    await addFarmer(page);
    actual(`Saved; message "${await shown(page, 'Joy Kiwang was added.')}".`);
});

for (const [id, area, ok] of [['BB-10', '-0.01', false], ['BB-11', '0', true], ['BB-12', '9999.99', true], ['BB-13', '10000', false]]) {
    test(`${id} Beneficiary: farm area ${area} ha`, async ({ page }) => {
        const name = { '0': ['Ramon', 'Chalsiw'], '9999.99': ['Elena', 'Fanged'] }[area] ?? ['Area', 'Test'];
        testCase({
            module: 'Beneficiary', kind: 'Boundary value', input: `Farm area ${area} ha (allowed: 0 to 9,999.99)`,
            expected: ok ? 'Accepted and saved.' : 'Refused with a farm area message.',
        });
        await login(page, 'Encoder_03');
        await fillFarmer(page, { first: name[0], last: name[1], area });
        await addFarmer(page);
        actual(ok
            ? `Saved; message "${await shown(page, `${name[0]} ${name[1]} was added.`)}".`
            : `Not saved; message "${await shown(page, 'Enter the farm area in hectares, e.g. 1.5.')}".`);
    });
}

test('BB-14 Beneficiary: same person added twice', async ({ page }) => {
    testCase({ module: 'Beneficiary', input: 'Bryan Fagyao again, same birthdate and barangay as BB-06', expected: 'Refused as a duplicate.' });
    await login(page, 'Encoder_03');
    await fillFarmer(page, { first: 'Bryan', last: 'Fagyao' });
    await addFarmer(page);
    actual(`Not saved; message "${await shown(page, 'This person is already registered.')}".`);
});

test('BB-15 Beneficiary: consent not ticked', async ({ page }) => {
    testCase({ module: 'Beneficiary', input: 'Complete details, consent box not ticked', expected: 'Refused until the farmer\'s consent is confirmed.' });
    await login(page, 'Encoder_03');
    await fillFarmer(page, { first: 'Nora', last: 'Pagteng', consent: false });
    await addFarmer(page);
    actual(`Not saved; message "${await shown(page, 'Confirm that the farmer gave consent before saving.')}".`);
});

for (const [id, by, query, expectName] of [['BB-16', 'name', 'Fagyao', 'Bryan Fagyao'], ['BB-17', 'RSBSA No.', 'RSBSA-0231', 'Juan Dela Cruz'], ['BB-18', 'barangay', 'Bontoc Ili', 'Carlos Ibanez']]) {
    test(`${id} Beneficiary: search by ${by}`, async ({ page }) => {
        testCase({ module: 'Beneficiary', input: `Search "${query}"`, expected: `Matching records are listed, including ${expectName}.` });
        await login(page, 'Encoder_03');
        await page.goto('/search?q=' + encodeURIComponent(query));
        const items = page.getByRole('listitem').filter({ hasText: expectName });
        await expect(items.first()).toBeVisible();
        actual(`Results listed, including ${expectName}: "${(await items.first().innerText()).replace(/\s+/g, ' ').trim()}".`);
    });
}

test('BB-19 Beneficiary: update a profile', async ({ page }) => {
    testCase({ module: 'Beneficiary', input: 'Bryan Fagyao: farm area 1.5 → 2.25 ha, contact 0917-555-0101', expected: 'The updated information is saved.' });
    await login(page, 'Encoder_03');
    await openProfile(page, 'Bryan Fagyao');
    await page.fill('input[name=farm_area_ha]', '2.25');
    await page.fill('input[name=contact_number]', '0917-555-0101');
    await page.locator('button[form=profile-edit]').click();
    const message = await shown(page, 'Profile saved.');
    await page.reload();
    await expect(page.locator('input[name=farm_area_ha]')).toHaveValue(/^2\.25?0?$/);
    actual(`Saved; message "${message}"; after reloading the farm area reads ${await page.locator('input[name=farm_area_ha]').inputValue()} ha.`);
});

// ---------------------------------------------------------------- DA interventions

test('BB-20 DA intervention: record for a farmer with an RSBSA No.', async ({ page }) => {
    testCase({ module: 'DA intervention', input: 'Bryan Fagyao (RSBSA-9001), DA Certified Rice Seeds, not yet distributed', expected: 'The intervention record is stored.' });
    await login(page, 'Encoder_03');
    await addRecord(page, 'Bryan Fagyao', 'da', 'Certified Rice Seeds');
    actual(`Stored; message "${await shown(page, 'Intervention record saved.')}".`);
});

test('BB-21 DA intervention: farmer without an RSBSA No.', async ({ page }) => {
    testCase({ module: 'DA intervention', kind: 'Equivalence partitioning', input: 'Joy Kiwang (no RSBSA No.), DA Certified Rice Seeds', expected: 'Refused: DA programs need an RSBSA No.' });
    await login(page, 'Encoder_03');
    await addRecord(page, 'Joy Kiwang', 'da', 'Certified Rice Seeds');
    actual(`Not stored; message "${await shown(page, /has no RSBSA No\./)}".`);
});

test('BB-22 DA intervention: release (process claim)', async ({ page }) => {
    testCase({ module: 'DA intervention', input: 'Administrator: Process Claim for Bryan Fagyao, Certified Rice Seeds, quantity 1', expected: 'The record becomes Claimed.' });
    await login(page, 'Admin_01');
    await processClaim(page, 'Bryan Fagyao', 'Certified Rice Seeds', { quantity: '1' });
    actual(`Claimed; profile shows "${await shown(page, /Certified Rice Seeds \(\d{4}-Q\d\): Claimed/)}".`);
});

test('BB-23 DA intervention: one per household per cycle', async ({ page }) => {
    testCase({ module: 'DA intervention', input: 'PAFF (one per household) for Juan and Maria Dela Cruz (same household); claim Juan, then Maria', expected: 'Juan\'s claim succeeds; Maria\'s is refused because her household already claimed.' });
    await login(page, 'Encoder_03');
    for (const farmer of ['Juan Dela Cruz', 'Maria Dela Cruz']) {
        await addRecord(page, farmer, 'da', 'PAFF');
        await expect(page.getByText('Intervention record saved.')).toBeVisible();
    }
    await login(page, 'Admin_01');
    await processClaim(page, 'Juan Dela Cruz', 'PAFF');
    await expect(page.getByText(/PAFF \(\d{4}-Q\d\): Claimed/).first()).toBeVisible();
    await processClaim(page, 'Maria Dela Cruz', 'PAFF');
    actual(`Juan: Claimed. Maria: refused, message "${await shown(page, /already claimed PAFF for this household/)}".`);
});

test('BB-24 DA intervention: deceased beneficiary without a proxy', async ({ page }) => {
    testCase({ module: 'DA intervention', input: 'Agricultural Technologist marks Carlos Ibanez\'s Complete Fertilizer as Deceased; Administrator processes the claim with no proxy name', expected: 'Refused: a proxy and proof note are needed.' });
    await login(page, 'Agritech_02');
    await page.goto('/interventions/da?name=Carlos');
    const row = page.locator('div.grid').filter({ hasText: 'Carlos Ibanez' }).filter({ hasText: 'Complete Fertilizer' }).first();
    await row.locator('select[name=validation_status]').selectOption('deceased');
    await page.waitForLoadState('networkidle');
    await login(page, 'Admin_01');
    await processClaim(page, 'Carlos Ibanez', 'Complete Fertilizer', { quantity: '1' });
    actual(`Not claimed; message "${await shown(page, 'A deceased beneficiary can only be claimed by a proxy with a proof note.')}".`);
});

test('BB-25 DA intervention: deceased beneficiary claimed by a proxy', async ({ page }) => {
    testCase({ module: 'DA intervention', input: 'Same record; proxy "Teresa Ibanez (spouse)", proof note "Death certificate No. 2026-114"', expected: 'Claimed through the proxy.' });
    await login(page, 'Admin_01');
    await processClaim(page, 'Carlos Ibanez', 'Complete Fertilizer', { quantity: '1', proxy_claimant: 'Teresa Ibanez (spouse)', proof_note: 'Death certificate No. 2026-114' });
    actual(`Claimed; profile shows "${await shown(page, /Complete Fertilizer \(\d{4}-Q\d\): Claimed/)}".`);
});

// ---------------------------------------------------------------- LGU interventions

test('BB-26 LGU intervention: farmer without an RSBSA No.', async ({ page }) => {
    testCase({ module: 'LGU intervention', kind: 'Equivalence partitioning', input: 'Joy Kiwang (no RSBSA No.), LGU Municipal Cash Subsidy', expected: 'The local intervention record is stored (LGU programs need no RSBSA No.).' });
    await login(page, 'Encoder_03');
    await addRecord(page, 'Joy Kiwang', 'lgu', 'Municipal Cash Subsidy');
    actual(`Stored; message "${await shown(page, 'Intervention record saved.')}".`);
});

// ---------------------------------------------------------------- Inventory

test('BB-27 Inventory: stock delivery (stock in)', async ({ page }) => {
    testCase({ module: 'Inventory', input: 'Record Movement: Certified Rice Seeds, Stock In, 20, note "Delivery from DA-RFO"', expected: 'The balance goes up by 20.' });
    await login(page, 'Admin_01');
    const before = await stock(page, 'Certified Rice Seeds');
    await page.getByRole('button', { name: '+ Record Movement' }).click();
    await selectStarting(page.locator('[name=inventory_item_id]'), 'Certified Rice Seeds');
    await page.selectOption('[name=direction]', 'in');
    await page.fill('[name=quantity]', '20');
    await page.fill('[name=notes]', 'Delivery from DA-RFO');
    await page.getByRole('button', { name: 'Save Stock Movement' }).click();
    const message = await shown(page, /Certified Rice Seeds recorded\./);
    const after = await stock(page, 'Certified Rice Seeds');
    expect(after).toBe(before + 20);
    actual(`Message "${message}"; balance ${before} → ${after}.`);
});

test('BB-28 Inventory: a release lowers the stock', async ({ page }) => {
    testCase({ module: 'Inventory', input: 'Record a distributed Certified Rice Seeds release of 2 for Liza Domingo', expected: 'The balance goes down by 2 by itself.' });
    await login(page, 'Encoder_03');
    const before = await stock(page, 'Certified Rice Seeds');
    await addRecord(page, 'Liza Domingo', 'da', 'Certified Rice Seeds', { distributed: true, quantity: '2' });
    await expect(page.getByText('Intervention record saved.')).toBeVisible();
    const after = await stock(page, 'Certified Rice Seeds');
    expect(after).toBe(before - 2);
    actual(`Balance ${before} → ${after} with no manual stock entry.`);
});

test('BB-29 Inventory: release larger than the stock', async ({ page }) => {
    testCase({ module: 'Inventory', kind: 'Boundary value', input: 'Process Claim of Certified Rice Seeds for Ana Dela Cruz, quantity 99,999 (more than the balance)', expected: 'Refused: not enough stock; the balance is unchanged.' });
    await login(page, 'Encoder_03');
    await addRecord(page, 'Ana Dela Cruz', 'da', 'Certified Rice Seeds');
    await login(page, 'Admin_01');
    const before = await stock(page, 'Certified Rice Seeds');
    await processClaim(page, 'Ana Dela Cruz', 'Certified Rice Seeds', { quantity: '99999' });
    const message = await shown(page, /Not enough stock/);
    expect(await stock(page, 'Certified Rice Seeds')).toBe(before);
    actual(`Not claimed; message "${message}"; balance stays ${before}.`);
});

test('BB-30 Inventory: stock movement of 0', async ({ page }) => {
    testCase({ module: 'Inventory', kind: 'Boundary value', input: 'Record Movement: Stock In, quantity 0 (smallest allowed is 0.01)', expected: 'Refused with a quantity message.' });
    await login(page, 'Admin_01');
    await page.goto('/inventory');
    await page.getByRole('button', { name: '+ Record Movement' }).click();
    await selectStarting(page.locator('[name=inventory_item_id]'), 'Certified Rice Seeds');
    await page.selectOption('[name=direction]', 'in');
    await page.fill('[name=quantity]', '0');
    await skipBrowserChecks(page);
    await page.getByRole('button', { name: 'Save Stock Movement' }).click();
    const message = (await page.locator('p.text-danger').first().innerText()).trim();
    actual(`Not recorded; message "${message}".`);
});

// ---------------------------------------------------------------- Crisis and damage

test('BB-31 Crisis: add a crisis', async ({ page }) => {
    testCase({ module: 'Crisis and damage', input: 'Administrator: + Add Crisis "Typhoon Kristine", date today', expected: 'The crisis is added and offered on the damage form.' });
    await login(page, 'Admin_01');
    await page.goto('/damage-reports');
    await page.getByRole('button', { name: '+ Add Crisis' }).click();
    await page.getByPlaceholder('e.g. Rain-Induced Landslide').fill('Typhoon Kristine');
    await page.locator('[role=dialog][aria-label="Add Crisis"] [name=occurred_on]').fill(TODAY);
    await page.locator('[role=dialog][aria-label="Add Crisis"]').getByRole('button', { name: 'Add Crisis' }).click();
    const message = await shown(page, 'Typhoon Kristine added.');
    await page.goto('/damage-reports/create');
    await expect(page.locator('[name=disaster_id] option', { hasText: 'Typhoon Kristine' })).toHaveCount(1);
    actual(`Message "${message}"; Typhoon Kristine is listed on the damage form.`);
});

test('BB-32 Crisis: file a damage report (loss computed)', async ({ page }) => {
    testCase({ module: 'Crisis and damage', input: 'Typhoon Kristine, Bryan Fagyao, Rice (4 MT/ha, ₱20,000/MT, factor 0.5), 1 ha totally + 1 ha partially damaged, 1 photo', expected: 'The report is stored; loss (1 + 0.5 × 1) × 4 = 6 MT and cost 6 × ₱20,000 = ₱120,000.' });
    const photo = join(scratch, 'field.png');
    writeFileSync(photo, PNG);
    await login(page, 'Encoder_03');
    await page.goto('/damage-reports/create');
    await page.selectOption('[name=disaster_id]', { label: 'Typhoon Kristine' });
    await pickFarmer(page, 'Bryan Fagyao');
    await page.selectOption('[name=crop_id]', { label: 'Rice' });
    await page.selectOption('[name=crop_stage]', 'vegetative');
    await page.fill('[name=total_area_ha]', '1');
    await page.fill('[name=partial_area_ha]', '1');
    await page.setInputFiles('input[name="photos[]"]', photo);
    await page.getByRole('button', { name: 'Submit Damage Report' }).click();
    const message = await shown(page, 'Damage report filed for Bryan Fagyao.');
    await expect(page.getByText(/120,000/).first()).toBeVisible();
    await expect(page.getByText(/\b6(\.0+)? ?MT\b/).first()).toBeVisible();
    actual(`Message "${message}"; the report shows ${await shown(page, /\b6(\.0+)? ?MT\b/)} and ${await shown(page, /120,000/)}.`);
});

async function startDamageReport(page, farmer) {
    await login(page, 'Encoder_03');
    await page.goto('/damage-reports/create');
    await page.selectOption('[name=disaster_id]', { label: 'Typhoon Kristine' });
    await pickFarmer(page, farmer);
    await page.selectOption('[name=crop_id]', { label: 'Rice' });
    await page.selectOption('[name=crop_stage]', 'vegetative');
    await page.fill('[name=total_area_ha]', '0.5');
}

test('BB-33 Crisis: damage report with no photo', async ({ page }) => {
    testCase({ module: 'Crisis and damage', kind: 'Boundary value', input: '0 photos attached (allowed: 1 to 10)', expected: 'Refused: at least one photo is required.' });
    await startDamageReport(page, 'Joy Kiwang');
    await skipBrowserChecks(page);
    await page.getByRole('button', { name: 'Submit Damage Report' }).click();
    actual(`Not filed; message "${await shown(page, 'Attach at least one photo of the damage (JPG or PNG).')}".`);
});

test('BB-34 Crisis: damage report with 11 photos', async ({ page }) => {
    testCase({ module: 'Crisis and damage', kind: 'Boundary value', input: '11 photos chosen at once (allowed: 1 to 10)', expected: 'The 11th photo is not accepted; the report is kept to 10 photos.' });
    const photos = Array.from({ length: 11 }, (_, i) => { const f = join(scratch, `photo-${i + 1}.png`); writeFileSync(f, PNG); return f; });
    await startDamageReport(page, 'Joy Kiwang');
    await page.setInputFiles('input[name="photos[]"]', photos);
    const note = await shown(page, /Not added: .*10 photos at most/);
    await page.getByRole('button', { name: 'Submit Damage Report' }).click();
    await expect(page.getByText('Damage report filed for Joy Kiwang.')).toBeVisible();
    actual(`The form showed "${note}"; the filed report has "${await shown(page, /^Photos \(\d+\)$/)}".`);
});

test('BB-35 Crisis: validate a damage report', async ({ page }) => {
    testCase({ module: 'Crisis and damage', input: 'Agricultural Technologist opens Bryan Fagyao\'s report and clicks Validate Report', expected: 'The report becomes Validated.' });
    await login(page, 'Agritech_02');
    await page.goto('/damage-reports?status=');
    await page.locator('a', { hasText: 'Bryan Fagyao' }).first().click();
    await page.getByRole('button', { name: 'Validate Report' }).click();
    actual(`Message "${await shown(page, 'Damage report validated.')}".`);
});

// ---------------------------------------------------------------- Excel import

test('BB-36 Import: valid spreadsheet', async ({ page }) => {
    testCase({ module: 'Excel import', input: 'CSV with 2 farmers (Name, Birthdate, Barangay, RSBSA No.), consent ticked', expected: 'The supported records are imported.' });
    const file = join(scratch, 'masterlist.csv');
    writeFileSync(file, 'Name,Birthdate,Barangay,RSBSA No.\nRamil Kinaw,1980-05-10,Samoki,RSBSA-7001\nGrace Lumiwes,1975-11-02,Tocucan,RSBSA-7002\n');
    await login(page, 'Encoder_03');
    await page.goto('/import');
    await page.setInputFiles('input[name=file]', file);
    const preview = await shown(page, /rows? matched automatically/);
    await page.check('[name=consent]');
    await page.getByRole('button', { name: /Confirm & Import/ }).click();
    actual(`Preview: "${preview}"; then "${await shown(page, /^Imported 2 new profiles/)}".`);
});

test('BB-37 Import: unsupported file type', async ({ page }) => {
    testCase({ module: 'Excel import', kind: 'Equivalence partitioning', input: 'A plain text file (notes.txt)', expected: 'Refused: only spreadsheets are accepted.' });
    const file = join(scratch, 'notes.txt');
    writeFileSync(file, 'not a spreadsheet');
    await login(page, 'Encoder_03');
    await page.goto('/import');
    await page.setInputFiles('input[name=file]', file);
    await page.waitForLoadState('networkidle');
    const message = page.locator('p.text-danger, [role=alert]').first();
    await expect(message).toBeVisible();
    actual(`Not imported; message "${(await message.innerText()).replace(/\s+/g, ' ').trim()}".`);
});

test('BB-38 Import: consent not confirmed', async ({ page }) => {
    testCase({ module: 'Excel import', input: 'Valid CSV, Confirm & Import pressed without ticking consent', expected: 'Refused until consent is confirmed; nothing is imported.' });
    const file = join(scratch, 'masterlist2.csv');
    writeFileSync(file, 'Name,Birthdate,Barangay\nDelia Fomagas,1982-01-20,Samoki\n');
    await login(page, 'Encoder_03');
    await page.goto('/import');
    await page.setInputFiles('input[name=file]', file);
    await expect(page.getByText(/rows? matched automatically/)).toBeVisible();
    await skipBrowserChecks(page);
    await page.getByRole('button', { name: /Confirm & Import/ }).click();
    actual(`Not imported; message "${await shown(page, 'Confirm that the farmers in this file gave consent before importing.')}".`);
});

// ---------------------------------------------------------------- Reports and export

test('BB-39 Reports: distribution report as PDF', async ({ page }) => {
    testCase({ module: 'Reports', input: 'Download Reports: current cycle, all programs, PDF', expected: 'The requested report is generated and downloaded.' });
    await login(page, 'Admin_01');
    await page.goto('/reports');
    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: /Generate Report/ }).click();
    const name = (await download).suggestedFilename();
    expect(name).toMatch(/\.pdf$/);
    actual(`Downloaded "${name}".`);
});

test('BB-40 Reports: distribution report as Excel', async ({ page }) => {
    testCase({ module: 'Reports', input: 'Download Reports: same choices, Excel', expected: 'An Excel (.xlsx) report is downloaded.' });
    await login(page, 'Admin_01');
    await page.goto('/reports');
    await page.locator('[name=format]').first().selectOption('xlsx').catch(async () => page.getByLabel(/Excel/).first().check());
    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: /Generate Report/ }).click();
    const name = (await download).suggestedFilename();
    expect(name).toMatch(/\.xlsx$/);
    actual(`Downloaded "${name}".`);
});

test('BB-41 Reports: crisis damage report as PDF', async ({ page }) => {
    testCase({ module: 'Reports', input: 'Crisis Reports filtered to Typhoon Kristine, Generate PDF Report', expected: 'A damage report PDF is downloaded.' });
    await login(page, 'Admin_01');
    await page.goto('/damage-reports');
    const crisis = page.locator('select[name=disaster] option').filter({ hasText: 'Typhoon Kristine' }).first();
    await page.locator('select[name=disaster]').selectOption(await crisis.getAttribute('value'));
    await page.waitForLoadState('networkidle');
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: /Generate PDF Report/ }).or(page.getByRole('button', { name: /Generate PDF Report/ })).first().click();
    const name = (await download).suggestedFilename();
    expect(name).toMatch(/\.pdf$/);
    actual(`Downloaded "${name}".`);
});

test('BB-42 Export: beneficiary list', async ({ page }) => {
    testCase({ module: 'Export', input: 'Export List → Export as .xlsx', expected: 'The list is exported as an Excel file with the program, claim status and crisis columns.' });
    await login(page, 'Admin_01');
    await page.goto('/export');
    for (const heading of ['RSBSA No.', 'Program', 'Claim Status', 'Crises']) await expect(page.getByText(heading, { exact: true }).first()).toBeVisible();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: /Export as \.xlsx/ }).click();
    const name = (await download).suggestedFilename();
    expect(name).toMatch(/\.xlsx$/);
    actual(`Preview has RSBSA No., Program, Claim Status and Crises columns; downloaded "${name}".`);
});

// ---------------------------------------------------------------- Users

test('BB-43 Users: add a staff account', async ({ page }) => {
    testCase({ module: 'User accounts', input: 'Add User: Ngalob, Lyn, username "Encoder_05", password of 8 characters, role Data Encoder', expected: 'The account is created and listed as Active.' });
    await login(page, 'Admin_01');
    await page.goto('/users?add=1');
    const form = page.locator('[role=dialog][aria-label="Add User"] form');
    await form.locator('[name=last_name]').fill('Ngalob');
    await form.locator('[name=first_name]').fill('Lyn');
    await form.locator('[name=username]').fill('Encoder_05');
    await form.locator('[name=password]').fill('Start123');
    await form.locator('[name=password_confirmation]').fill('Start123');
    await form.locator('[name=role_id]').selectOption({ label: 'Data Encoder' });
    await form.getByRole('button', { name: 'Save User' }).click();
    const message = await shown(page, 'Encoder_05 was added.');
    await expect(page.getByRole('listitem').filter({ hasText: 'Encoder_05' }).filter({ hasText: 'Active' })).toBeVisible();
    actual(`Message "${message}"; Encoder_05 is listed as Active.`);
});

test('BB-44 Users: password of 7 characters', async ({ page }) => {
    testCase({ module: 'User accounts', kind: 'Boundary value', input: 'Add User with password "Start12" (7 characters; minimum 8)', expected: 'Refused: the password must have at least 8 characters.' });
    await login(page, 'Admin_01');
    await page.goto('/users?add=1');
    const form = page.locator('[role=dialog][aria-label="Add User"] form');
    await form.locator('[name=last_name]').fill('Fakat');
    await form.locator('[name=first_name]').fill('Mark');
    await form.locator('[name=username]').fill('Encoder_06');
    await form.locator('[name=password]').fill('Start12');
    await form.locator('[name=password_confirmation]').fill('Start12');
    const save = form.getByRole('button', { name: 'Save User' });
    await expect(save).toBeDisabled();
    // The page keeps Save User disabled; the server is also asked directly to show it refuses too.
    await save.evaluate((button) => { button.disabled = false; button.removeAttribute('x-bind:disabled'); });
    await skipBrowserChecks(page);
    await save.click();
    actual(`Save User stayed disabled on the page; sent anyway, the server answered "${await shown(page, /at least 8 characters/)}".`);
});

test('BB-45 Users: new account must change its password', async ({ page }) => {
    testCase({ module: 'User accounts', input: 'Sign in as Encoder_05 with the starting password', expected: 'The user is sent to Change Password before using AGAPAY.' });
    await login(page, 'Encoder_05', 'Start123');
    await expect(page).toHaveURL(/password/);
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/password/);
    actual(`Sent to ${new URL(page.url()).pathname}; opening the home page leads back there.`);
});

test('BB-46 Users: deactivated account', async ({ page }) => {
    testCase({ module: 'User accounts', input: 'Administrator sets Encoder_05 to Inactive; Encoder_05 signs in', expected: 'Sign-in is refused; the account stays in the list.' });
    await login(page, 'Admin_01');
    await page.goto('/users');
    await page.getByRole('listitem').filter({ hasText: 'Encoder_05' }).getByRole('button').click();
    const form = page.locator('[role=dialog][aria-label="Edit Encoder_05"] form');
    await form.locator('[name=status]').selectOption('inactive');
    await form.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.getByText('Encoder_05 was updated.')).toBeVisible();
    await login(page, 'Encoder_05', 'Start123');
    actual(`Refused; message "${await shown(page, /inactive/)}".`);
});

// ---------------------------------------------------------------- Role-based access

test('BB-47 Access: Data Encoder opens Administrator pages', async ({ page }) => {
    const pages = ['/users', '/audit-trail', '/export', '/programs', '/distribution-cycles'];
    testCase({ module: 'Role-based access', input: `Encoder_03 opens ${pages.join(', ')}`, expected: 'Each page is refused (403 Forbidden).' });
    await login(page, 'Encoder_03');
    const results = [];
    for (const url of pages) results.push(`${url} ${(await page.goto(url)).status()}`);
    expect(results.every((r) => r.endsWith('403'))).toBe(true);
    actual(`Refused: ${results.join(', ')}.`);
});

test('BB-48 Access: Agricultural Technologist opens encoder pages', async ({ page }) => {
    const pages = ['/import', '/beneficiaries/create', '/users'];
    testCase({ module: 'Role-based access', input: `Agritech_02 opens ${pages.join(', ')}`, expected: 'Each page is refused (403 Forbidden).' });
    await login(page, 'Agritech_02');
    const results = [];
    for (const url of pages) results.push(`${url} ${(await page.goto(url)).status()}`);
    expect(results.every((r) => r.endsWith('403'))).toBe(true);
    actual(`Refused: ${results.join(', ')}.`);
});

test('BB-49 Access: not signed in', async ({ page }) => {
    testCase({ module: 'Role-based access', input: 'A visitor who is not signed in opens /dashboard and /beneficiaries', expected: 'Sent to the login page.' });
    await page.context().clearCookies();
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('/beneficiaries');
    await expect(page).toHaveURL(/\/login$/);
    actual('Both pages redirected to /login.');
});

// ---------------------------------------------------------------- Audit trail and privacy

test('BB-50 Audit trail: actions are logged', async ({ page }) => {
    testCase({ module: 'Audit trail', input: 'Administrator filters the Audit Trail by "Claimed Intervention" and "Validated Damage Report"', expected: 'The earlier claim and validation are listed with the user and time.' });
    await login(page, 'Admin_01');
    await page.goto('/audit-trail?action=' + encodeURIComponent('Claimed Intervention'));
    const claim = page.locator('tr').filter({ hasText: 'Claimed Intervention' }).filter({ hasText: 'Admin_01' }).first();
    await expect(claim).toBeVisible();
    await page.goto('/audit-trail?action=' + encodeURIComponent('Validated Damage Report'));
    const validated = page.locator('tr').filter({ hasText: 'Validated Damage Report' }).filter({ hasText: 'Agritech_02' }).first();
    await expect(validated).toBeVisible();
    actual(`Listed: "${(await validated.innerText()).replace(/\s+/g, ' ').trim()}".`);
});

test('BB-51 Privacy: notice and consent record', async ({ page }) => {
    testCase({ module: 'Data privacy', input: 'Open /privacy; open Bryan Fagyao\'s profile', expected: 'The privacy notice is shown; the profile shows who confirmed consent and when.' });
    await page.goto('/privacy');
    await expect(page.getByText(/Data Privacy Act/).first()).toBeVisible();
    await login(page, 'Admin_01');
    await openProfile(page, 'Bryan Fagyao');
    actual(`Notice shown; profile: "${await shown(page, /^Consent confirmed by .+ on /)}".`);
});

// ---------------------------------------------------------------- LGU repeat (changes the current cycle, so last)

test('BB-52 LGU intervention: same assistance again in a later cycle', async ({ page }) => {
    testCase({ module: 'LGU intervention', input: 'Joy Kiwang claims Municipal Cash Subsidy; Administrator adds cycle 2026-Q4 (Ongoing); she is listed for it again in 2026-Q4', expected: 'The new record is marked Duplicate and cannot be claimed.' });
    await login(page, 'Admin_01');
    await processClaim(page, 'Joy Kiwang', 'Municipal Cash Subsidy');
    await expect(page.getByText(/Municipal Cash Subsidy \(\d{4}-Q\d\): Claimed/).first()).toBeVisible();
    await page.goto('/distribution-cycles');
    await page.getByRole('button', { name: '+ Add Cycle' }).click();
    const form = page.locator('[role=dialog][aria-label="Distribution Cycle"] form');
    await form.locator('[name=code]').fill('2026-Q4');
    await form.locator('[name=schedule_date]').fill(TODAY);
    await form.locator('[name=label]').fill('2026-Q4 Wet Season');
    await form.locator('[name=status]').selectOption('ongoing');
    await form.getByRole('button', { name: 'Add Cycle' }).click();
    await expect(page.getByText('2026-Q4 Wet Season added.')).toBeVisible();
    await addRecord(page, 'Joy Kiwang', 'lgu', 'Municipal Cash Subsidy');
    await expect(page.getByText('Intervention record saved.')).toBeVisible();
    await page.goto('/interventions/lgu?tab=records&name=Joy');
    const row = page.locator('div.grid').filter({ hasText: 'Joy Kiwang' }).filter({ hasText: 'Municipal Cash Subsidy' });
    await expect(row.filter({ hasText: 'Duplicate' })).toHaveCount(1);
    await openProfile(page, 'Joy Kiwang');
    const claim = page.getByRole('button', { name: 'Process Claim' });
    await expect(claim).toBeDisabled();
    actual(`New 2026-Q4 record marked Duplicate; on her profile Process Claim is disabled ("${await claim.getAttribute('title')}").`);
});
