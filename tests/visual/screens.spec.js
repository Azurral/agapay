import { test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const PASSWORD = process.env.AGAPAY_SEED_PASSWORD ?? 'Agapay@2026';
const OUT = fileURLToPath(new URL('./out/', import.meta.url));
const FIGMA = fileURLToPath(new URL('./figma/', import.meta.url));

// frame: Figma node id, as: seeded username (null = guest), before: optional page action.
const SCREENS = [
    { frame: '482-339', route: '/login', as: null },
    { frame: '310-2', route: '/dashboard', as: 'Admin_01' },
    { frame: '310-844', route: '/dashboard', as: 'Admin_01', remember: ['Encoder_03', 'Agritech_02'], // most recent first => Figma order
      before: (page) => page.getByRole('button', { name: /Admin_01/ }).click() },
    { frame: '237-1470', route: '/dashboard', as: 'Agritech_02' },
    { frame: '237-1659', route: '/dashboard', as: 'Encoder_03' },
    { frame: '329-695', route: '/users', as: 'Admin_01' },
    { frame: '329-904', route: '/audit-trail', as: 'Admin_01' },
    { frame: '329-1596', route: '/rsbsa/register', as: 'Admin_01' },
    { frame: '329-2822', route: juanProfile, as: 'Admin_01' },
    { frame: '407-1181', route: juanProfile, as: 'Agritech_02' },
    { frame: '430-1461', route: juanProfile, as: 'Encoder_03' },
    { frame: '430-1276', route: '/beneficiaries', as: 'Encoder_03' },
];

// Juan Dela Cruz's profile URL, read from search results (ids differ between seeds).
async function juanProfile(page) {
    await page.goto('/search?q=RSBSA-0231');
    return new URL(await page.locator('a[href*="/beneficiaries/"]').first().getAttribute('href')).pathname;
}

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
    test(`${screen.frame} ${typeof screen.route === 'function' ? screen.route.name : screen.route}`, async ({ page }) => {
        for (const other of screen.remember ?? []) {   // puts these accounts in the remembered-accounts cookie
            await login(page, other);
            await logout(page);
        }
        if (screen.as) await login(page, screen.as);
        const route = typeof screen.route === 'function' ? await screen.route(page) : screen.route;
        await page.goto(route);
        await page.evaluate(() => document.fonts.ready);
        if (screen.before) await screen.before(page);
        await page.waitForTimeout(300); // let Alpine transitions settle

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
        results.push({ frame: screen.frame, route, rmse });
        writeFileSync(`${OUT}report.json`, JSON.stringify(results, null, 2));
    });
}
