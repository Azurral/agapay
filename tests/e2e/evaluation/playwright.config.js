import { defineConfig } from '@playwright/test';

// The 52 black-box test cases of the manuscript, run against an INSTALLED evaluation copy
// (AGAPAY-Setup-Evaluation.exe, http://127.0.0.1:8742), with a video of every case. Proves that the evaluation copy's
// sample records match the paper; the cases change the records, so reinstall the evaluation copy before sending it.
//   npm run e2e:evaluation
export default defineConfig({
    testDir: '..',
    testMatch: 'blackbox.spec.js',
    outputDir: process.env.AGAPAY_URL ? '../../../portable-build/evaluation-mac-run' : '../../../portable-build/evaluation-videos',
    timeout: 90_000,
    workers: 1,
    use: {
        baseURL: process.env.AGAPAY_URL ?? 'http://127.0.0.1:8742',   // the Mac copy: AGAPAY_URL=http://127.0.0.1:8750
        channel: 'msedge',
        viewport: { width: 1600, height: 900 },
        acceptDownloads: true,
        video: { mode: process.env.AGAPAY_URL ? 'off' : 'on', size: { width: 1600, height: 900 } },
    },
});
