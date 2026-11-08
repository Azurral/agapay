import { defineConfig } from '@playwright/test';

// Rehearses the defense demo (Part 5) on a freshly installed copy: an empty database set up by agapay:install, the
// same command the Windows installer runs (barangays, default programs and crop values; no farmers, staff or stock).
export default defineConfig({
    testDir: '.',
    timeout: 120_000,
    workers: 1,
    use: {
        baseURL: 'http://127.0.0.1:8124',
        channel: 'msedge',
        viewport: { width: 1820, height: 1024 },
        acceptDownloads: true,
    },
    webServer: {
        // config:clear first: a cached config would ignore DB_DATABASE below and wipe the real database.
        command: 'php artisan config:clear && php artisan migrate:fresh --force && php artisan agapay:install'
            + ' --last-name=Mendez --first-name=Rosa --username=omag_admin --password=Agapay#2026 && php artisan serve --port=8124',
        cwd: '../../..',
        url: 'http://127.0.0.1:8124/login',
        reuseExistingServer: false,
        env: { DB_DATABASE: 'agapay_visual', APP_ENV: 'local' },
        timeout: 300_000,
    },
});
