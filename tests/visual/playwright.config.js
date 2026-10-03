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
        // config:clear first: a cached config would ignore DB_DATABASE below and wipe the real database.
        command: 'php artisan config:clear && php artisan migrate:fresh --seed --force && php artisan serve --port=8123',
        cwd: '../..',
        url: 'http://127.0.0.1:8123/login',
        reuseExistingServer: false,
        env: { DB_DATABASE: 'agapay_visual', AGAPAY_FROZEN_NOW: '2026-07-22 09:00:00', APP_ENV: 'local' },
        timeout: 120_000,
    },
});
