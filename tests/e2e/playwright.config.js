import { defineConfig } from '@playwright/test';

// End-to-end flows on a freshly migrated and seeded database (the visual harness's database; the two never run together).
export default defineConfig({
    testDir: '.',
    timeout: 90_000,
    workers: 1,
    use: {
        baseURL: 'http://127.0.0.1:8124',
        channel: 'msedge',            // uses the installed Edge; no browser download needed
        viewport: { width: 1820, height: 1024 },
        acceptDownloads: true,
    },
    webServer: {
        command: 'php artisan migrate:fresh --seed --force && php artisan serve --port=8124',
        cwd: '../..',
        url: 'http://127.0.0.1:8124/login',
        reuseExistingServer: false,
        env: { DB_DATABASE: 'agapay_visual', APP_ENV: 'local' },
        timeout: 120_000,
    },
});
