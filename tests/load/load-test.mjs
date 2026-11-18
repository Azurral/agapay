// AGAPAY load test (panel: scalability, performance, availability). No extra tools: Node 22's fetch.
//
//   node tests/load/load-test.mjs --url=http://localhost:8099 --users=10 --seconds=120 --label="1,000 farmers, 10 users"
//
// Signs in with the sample accounts (Admin_01, Agritech_02, Encoder_03; password AGAPAY_SEED_PASSWORD or Agapay@2026),
// then each simulated staff member keeps opening the pages of their role for --seconds, like people working at the same
// time. Afterwards a few heavy downloads (PDF / Excel) are timed one by one. Prints a Markdown table and writes JSON.
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const args = Object.fromEntries(process.argv.slice(2).map((a) => a.replace(/^--/, '').split('=')).map(([k, ...v]) => [k, v.join('=')]));
const BASE = (args.url ?? 'http://localhost:8099').replace(/\/$/, '');
const USERS = Number(args.users ?? 10);
const SECONDS = Number(args.seconds ?? 60);
const LABEL = args.label ?? `${USERS} users`;
const OUT = args.out ?? 'tests/load/results';
const PASSWORD = process.env.AGAPAY_SEED_PASSWORD ?? 'Agapay@2026';
const FARMERS = Number(args.farmers ?? 100);   // profile ids opened at random, 1..FARMERS

const ACCOUNTS = ['Admin_01', 'Agritech_02', 'Encoder_03'];
const PAGES = {
    Admin_01: ['/dashboard', '/interventions/da', '/interventions/da?page=3', '/interventions/lgu?tab=records&show=all', '/search?q=Dela',
        '/beneficiaries/{id}', '/damage-reports?disaster=all', '/claiming', '/audit-trail', '/beneficiaries/approvals', '/inventory'],
    Agritech_02: ['/dashboard', '/interventions/da', '/interventions/lgu?tab=records', '/damage-reports?disaster=all', '/search?q=Ramos',
        '/beneficiaries/{id}', '/claiming'],
    Encoder_03: ['/dashboard', '/beneficiaries', '/intervention-records', '/inventory', '/search?q=Kinaw', '/beneficiaries/{id}', '/claiming'],
};
const HEAVY = [['Distribution list export (Excel)', 'Admin_01', '/export/download'], ['Crisis damage PDF', 'Admin_01', '/damage-reports/pdf?disaster=all'],
    ['Crisis damage export (Excel)', 'Admin_01', '/damage-reports/export?disaster=all']];

/** A tiny cookie jar per simulated user. */
class Session {
    constructor() { this.jar = new Map(); }
    cookieHeader() { return [...this.jar].map(([k, v]) => `${k}=${v}`).join('; '); }
    keep(res) {
        for (const c of res.headers.getSetCookie?.() ?? []) {
            const [pair] = c.split(';');
            const i = pair.indexOf('=');
            this.jar.set(pair.slice(0, i).trim(), pair.slice(i + 1));
        }
    }
    async get(path) {
        const res = await fetch(BASE + path, { headers: { cookie: this.cookieHeader() }, redirect: 'manual' });
        this.keep(res);
        const body = await res.arrayBuffer();
        return { res, bytes: body.byteLength, text: () => new TextDecoder().decode(body) };
    }
    async login(username) {
        const page = await this.get('/login');
        const token = /name="_token" value="([^"]+)"/.exec(page.text())?.[1];
        const res = await fetch(BASE + '/login', {
            method: 'POST', redirect: 'manual',
            headers: { cookie: this.cookieHeader(), 'content-type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _token: token ?? '', username, password: PASSWORD }),
        });
        this.keep(res);
        await res.arrayBuffer();
        if (![302, 303].includes(res.status) || !(res.headers.get('location') ?? '').includes('dashboard')) {
            throw new Error(`Sign-in as ${username} failed (HTTP ${res.status}).`);
        }
    }
}

const samples = new Map();   // page -> [ms]
const failures = new Map();  // page -> count
const record = (name, ms, ok) => {
    if (!samples.has(name)) samples.set(name, []);
    samples.get(name).push(ms);
    if (!ok) failures.set(name, (failures.get(name) ?? 0) + 1);
};

async function worker(n, until) {
    const account = ACCOUNTS[n % ACCOUNTS.length];
    const s = new Session();
    await s.login(account);
    const pages = PAGES[account];
    let i = n;
    while (Date.now() < until) {
        const path = pages[i++ % pages.length];
        const url = path.replace('{id}', String(1 + Math.floor(Math.random() * FARMERS)));
        const t = performance.now();
        let ok = false;
        try {
            const { res } = await s.get(url);
            ok = res.status === 200 || (path.includes('{id}') && res.status === 404);   // a random id may be archived
        } catch { ok = false; }
        record(path, performance.now() - t, ok);
        await new Promise((r) => setTimeout(r, 300 + Math.random() * 700));   // a person reading the page
    }
}

const pct = (arr, p) => { const s = [...arr].sort((a, b) => a - b); return s[Math.min(s.length - 1, Math.floor((p / 100) * s.length))]; };
const fmt = (ms) => (ms >= 1000 ? `${(ms / 1000).toFixed(2)} s` : `${Math.round(ms)} ms`);

const started = Date.now();
const until = started + SECONDS * 1000;
await Promise.all(Array.from({ length: USERS }, (_, n) => worker(n, until)));
const elapsed = (Date.now() - started) / 1000;

// Heavy downloads, one at a time (as a single staff member would make them).
const heavy = [];
for (const [name, account, path] of HEAVY) {
    const s = new Session();
    await s.login(account);
    const t = performance.now();
    const { res, bytes } = await s.get(path);
    heavy.push({ name, path, ms: Math.round(performance.now() - t), status: res.status, kb: Math.round(bytes / 1024) });
}

const rows = [...samples].map(([page, ms]) => ({
    page, requests: ms.length, failed: failures.get(page) ?? 0,
    avg: ms.reduce((a, b) => a + b, 0) / ms.length, p95: pct(ms, 95), max: Math.max(...ms),
}));
const total = rows.reduce((a, r) => a + r.requests, 0);
const failed = rows.reduce((a, r) => a + r.failed, 0);
const all = [...samples.values()].flat();
const summary = {
    label: LABEL, url: BASE, users: USERS, seconds: SECONDS, requests: total, failed, errorRate: total ? failed / total : 0,
    perSecond: total / elapsed, avg: all.reduce((a, b) => a + b, 0) / all.length, p95: pct(all, 95), max: Math.max(...all),
    pages: rows, heavy, finishedAt: new Date().toISOString(),
};

mkdirSync(OUT, { recursive: true });
const file = join(OUT, `${LABEL.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}.json`);
writeFileSync(file, JSON.stringify(summary, null, 2));

console.log(`\n## ${LABEL}\n`);
console.log(`${USERS} staff at once for ${SECONDS} s: ${total} page loads, ${(summary.perSecond).toFixed(1)}/s, ${failed} failed (${(summary.errorRate * 100).toFixed(2)}%). All pages: average ${fmt(summary.avg)}, 95% within ${fmt(summary.p95)}, slowest ${fmt(summary.max)}.\n`);
console.log('| Page | Loads | Failed | Average | 95% within | Slowest |\n|---|---|---|---|---|---|');
for (const r of rows.sort((a, b) => b.p95 - a.p95)) console.log(`| ${r.page} | ${r.requests} | ${r.failed} | ${fmt(r.avg)} | ${fmt(r.p95)} | ${fmt(r.max)} |`);
console.log('\n| Download (one at a time) | Time | Size | Status |\n|---|---|---|---|');
for (const h of heavy) console.log(`| ${h.name} | ${fmt(h.ms)} | ${h.kb} KB | ${h.status} |`);
console.log(`\nSaved ${file}`);
