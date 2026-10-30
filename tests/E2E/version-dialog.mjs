/**
 * Browser test of the version dialog in System → Einstellungen, against a running local installation.
 *
 * Seeds fake runs into var/memo-dev/version-switch (the real ones are moved aside and put back) and never
 * clicks Wechseln, Rollback or Restore, so code, vendor/ and the database stay untouched.
 *
 *   cd tests/E2E && bun install
 *   E2E_BASE_URL=https://project.memo E2E_PROJECT_DIR=/path/to/project \
 *   E2E_BACKEND_USER=… E2E_BACKEND_PASSWORD=… bun version-dialog.mjs
 *
 * E2E_CHROME: browser binary, when Playwright's own browser is not installed.
 */
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

const env = (name) => {
    if (!process.env[name]) {
        throw new Error(`${name} is not set.`);
    }

    return process.env[name];
};

const baseUrl = env('E2E_BASE_URL').replace(/\/$/, '');
const projectDir = env('E2E_PROJECT_DIR');
const runDir = `${projectDir}/var/memo-dev/version-switch`;
const parkedDir = `${runDir}.e2e-parked`;
const backup = 'backup__e2e-version-dialog.sql.gz';
const backupPath = `${projectDir}/var/backups/${backup}`;

const macChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const executablePath = process.env.E2E_CHROME ?? (existsSync(macChrome) ? macChrome : undefined);

let counter = 0;

const run = (status) => {
    counter += 1;

    const runId = `20261030-1200${String(counter).padStart(2, '0')}-e2e0${String(counter).padStart(2, '0')}`;
    const time = new Date(Date.UTC(2026, 9, 30, 11, 0, counter)).toISOString();

    return { runId, startedAt: time, finishedAt: time, ...status };
};

const seed = (runs) => {
    rmSync(runDir, { recursive: true, force: true });
    mkdirSync(runDir, { recursive: true });
    runs.forEach((status) => writeFileSync(`${runDir}/${status.runId}.json`, JSON.stringify(status)));
    runs.forEach((status) => writeFileSync(`${runDir}/${status.runId}.log`, `log of ${status.runId}\n`));
};

const scenarios = [
    {
        name: 'successful switch: rollback offered, restore hidden, Esc removes the dialog without reload',
        runs: () => [run({ action: 'switch', state: 'success', tag: 'v2', previousRef: 'refs/tags/v1', backup, checkedOut: true, step: 'cache' })],
        check: async(page, dialog) => {
            assert.equal(await dialog.locator('[data-steps] li.is-done').count(), 5);
            assert.match(await dialog.locator('[data-do="rollback"]').textContent(), /v1/);
            assert.equal(await dialog.locator('[data-do="restore"]').isHidden(), true);

            await page.keyboard.press('Escape');
            await page.waitForTimeout(1500);
            assert.equal(await page.locator('dialog.memo-version-dialog').count(), 0, 'Esc left the dialog in the DOM');
            assert.equal(await page.evaluate(() => window.e2eMarker), true, 'the page reloaded after Esc');
        },
    },
    {
        name: 'successful rollback: restore offered with the backup of the switch it undid; × removes the dialog',
        runs: () => {
            const switchRun = run({ action: 'switch', state: 'success', tag: 'v2', previousRef: 'refs/tags/v1', backup, checkedOut: true });

            return [switchRun, run({ action: 'rollback', state: 'success', tag: 'refs/tags/v1', previousRef: 'v2', rollbackOf: switchRun.runId, checkedOut: true })];
        },
        check: async(page, dialog) => {
            const restore = dialog.locator('[data-do="restore"]');

            assert.equal(await dialog.locator('[data-run] option').count(), 2);
            assert.equal(await restore.isVisible(), true);
            assert.equal(await restore.isEnabled(), true);
            assert.equal(await restore.getAttribute('title'), backup);

            await dialog.locator('.memo-version-dialog__close').click();
            await page.waitForTimeout(500);
            assert.equal(await page.locator('dialog.memo-version-dialog').count(), 0, '× left the dialog in the DOM');
        },
    },
    {
        name: 'reverted switch with a deleted backup: restore shown disabled with the reason',
        runs: () => [run({ action: 'switch', state: 'failed_reverted', tag: 'v2', previousRef: 'refs/tags/v1', backup: 'backup__gone.sql.gz', failedStep: 'migrate', checkedOut: false })],
        check: async(page, dialog) => {
            const restore = dialog.locator('[data-do="restore"]');

            assert.equal(await restore.isVisible(), true);
            assert.equal(await restore.isDisabled(), true);
            assert.equal(await dialog.locator('[data-steps] li').last().getAttribute('class'), 'memo-version-dialog__step is-done');
        },
    },
    {
        name: 'preflight failure: no revert step, nothing offered',
        runs: () => [run({ action: 'switch', state: 'failed_unchanged', tag: 'v2', failedStep: 'preflight', checkedOut: false })],
        check: async(page, dialog) => {
            assert.equal(await dialog.locator('[data-steps] li').count(), 5);
            assert.equal(await dialog.locator('[data-steps] li.is-failed').count(), 1);
            assert.equal(await dialog.locator('[data-do="rollback"]').isHidden(), true);
            assert.equal(await dialog.locator('[data-do="restore"]').isHidden(), true);
        },
    },
];

if (existsSync(parkedDir)) {
    throw new Error(`${parkedDir} exists: an earlier run did not finish. Move it back to ${runDir} first.`);
}

if (existsSync(runDir)) {
    renameSync(runDir, parkedDir);
}

writeFileSync(backupPath, '');

const browser = await chromium.launch({ executablePath, headless: true, args: ['--ignore-certificate-errors'] });
const failures = [];

try {
    const page = await browser.newPage({ ignoreHTTPSErrors: true, locale: 'de-CH' });
    const errors = [];

    page.on('pageerror', (error) => errors.push(error.message));
    // The console only says "Failed to load resource"; the response names the URL
    page.on('console', (message) => 'error' === message.type() && !message.text().startsWith('Failed to load resource') && errors.push(message.text()));
    page.on('response', (response) => response.status() >= 400 && errors.push(`${response.status()} ${response.url()}`));

    await page.goto(`${baseUrl}/contao/login`);
    await page.fill('input[name="username"]', env('E2E_BACKEND_USER'));
    await page.fill('input[name="password"]', env('E2E_BACKEND_PASSWORD'));
    await Promise.all([page.waitForURL((url) => !url.pathname.endsWith('/login')), page.press('input[name="password"]', 'Enter')]);

    for (const scenario of scenarios) {
        try {
            seed(scenario.runs());
            errors.length = 0;

            await page.goto(`${baseUrl}/contao?do=settings`);
            await page.evaluate(() => {
                window.e2eMarker = true;
            });
            await page.click('[data-memo-version-action="log"]');

            const dialog = page.locator('dialog.memo-version-dialog[open]');

            await dialog.waitFor();
            await dialog.locator('[data-run] option').first().waitFor({ state: 'attached' });
            await scenario.check(page, dialog);
            assert.deepEqual(errors, [], 'console errors');
            console.log(`ok   ${scenario.name}`);
        } catch (error) {
            failures.push(scenario.name);
            console.log(`FAIL ${scenario.name}\n     ${error.message}`);
        }
    }
} finally {
    await browser.close();
    rmSync(runDir, { recursive: true, force: true });
    rmSync(backupPath, { force: true });

    if (existsSync(parkedDir)) {
        renameSync(parkedDir, runDir);
    }
}

process.exit(failures.length > 0 ? 1 : 0);
