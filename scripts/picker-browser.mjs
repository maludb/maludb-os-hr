// picker-browser.mjs — the headless-browser pass for HR's record picker (design-system references/record-picker.md; the model is
// ~/htmx-php-builder/skills/design-system/examples/php/record-picker/picker-browser.mjs). Needs the scratch install running and seeded
// by scripts/prove-picker.sh (hr_dev: HR admin 1001, 38 employees): php -S 127.0.0.1:8394 -t html scripts/dev-router.php
//   mkdir /tmp/hr-picker && cd /tmp/hr-picker && ln -s /var/www/web/node_modules node_modules
//   cp <checkout>/scripts/picker-browser.mjs . && S=$PWD HR=<checkout> BASE=http://127.0.0.1:8394 node picker-browser.mjs
// HR has no login form: each browser context signs on through /sso with a hand-minted hand-off token (scripts/dev-sso.php).
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
const S = process.env.S || '.', B = process.env.BASE || 'http://127.0.0.1:8394', HR = process.env.HR || '/srv/apps/hr-picker';
const sso = (id, name, cap, brole, roles) => B + '/sso?' + execFileSync('php', [HR + '/scripts/dev-sso.php', id, name, cap, brole, roles]).toString().trim();
const FORM = '/reviews/new', FIELD = 'review-form-field-person', OPT = 'review-form-field-reviewer';
const ok = (n, c) => console.log((c ? 'ok   ' : 'FAIL ') + n);
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
const errors = []; page.on('pageerror', e => errors.push(e.message)); page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
await page.goto(sso('1001', 'Picker HR Admin', 'admin', 'user', 'hr_admin'));
await page.goto(B + FORM); await page.waitForSelector('#' + FIELD + '-open');
await page.screenshot({ path: S + '/1-form-desktop.png', fullPage: false });
await page.click('#' + FIELD + '-open');
await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker-list .record-picker-row');
ok('the modal opens with the first page', (await page.$$('#record-picker .record-picker-row')).length === 25);
await page.waitForFunction(() => document.activeElement.id === 'record-picker-search', null, { timeout: 3000 }).catch(() => {}); ok('the search box has focus', await page.evaluate(() => document.activeElement.id === 'record-picker-search'));
ok('the title is the source\'s', (await page.textContent('#record-picker-title')).trim() === 'People');
ok('no create button (people are created in the directory, not here)', await page.evaluate(() => document.getElementById('record-picker-footer').classList.contains('d-none')));
await page.type('#record-picker-search', 'person 1'); await page.waitForTimeout(700);
const n = (await page.$$('#record-picker .record-picker-row')).length; ok('typing narrows the list (' + n + ' rows)', n === 10 || n === 11);
await page.screenshot({ path: S + '/2-picker-desktop.png' });
await page.keyboard.press('ArrowDown'); ok('ArrowDown moves to the first row', await page.evaluate(() => document.activeElement.classList.contains('record-picker-row')));
await page.keyboard.press('ArrowDown'); const label = await page.evaluate(() => document.activeElement.dataset.label);
await page.keyboard.press('Enter'); await page.waitForSelector('#record-picker', { state: 'hidden' });
ok('Enter picks the focused row (' + label + ')', (await page.textContent('#' + FIELD + '-open')).trim() === label && (await page.inputValue('#' + FIELD)) !== '');
await page.waitForFunction((id) => document.activeElement.id === id + '-open', FIELD, { timeout: 3000 }).catch(() => {}); ok('focus returns to the opener', await page.evaluate((id) => document.activeElement.id === id + '-open', FIELD));
// Enter in the search box picks the first match
await page.click('#' + FIELD + '-open'); await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker .record-picker-row');
await page.type('#record-picker-search', 'report b'); await page.waitForTimeout(600); await page.keyboard.press('Enter'); await page.waitForSelector('#record-picker', { state: 'hidden' });
ok('Enter in the search box picks the first match', (await page.textContent('#' + FIELD + '-open')).trim() === 'Picker Report B');
// Show more
await page.click('#' + FIELD + '-open'); await page.waitForSelector('#record-picker .record-picker-more');
await page.click('#record-picker-more'); await page.waitForFunction(() => document.querySelectorAll('#record-picker .record-picker-row').length > 25);
const total = (await page.$$('#record-picker .record-picker-row')).length;
ok('Show more appends page 2 in place (' + total + ' rows)', total === 38 && !(await page.$('#record-picker-more')));
ok('the chosen row is marked active', await page.evaluate(() => document.querySelector('#record-picker .record-picker-row.active')?.dataset.label === 'Picker Report B'));
await page.keyboard.press('Escape'); await page.waitForSelector('#record-picker', { state: 'hidden' });
// the optional reviewer: pick, then Clear
await page.click('#' + OPT + '-open'); await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker .record-picker-row');
await page.click('#record-picker .record-picker-row:nth-child(2)'); await page.waitForSelector('#record-picker', { state: 'hidden' });
ok('an optional picker shows Clear once chosen', await page.evaluate((id) => !document.getElementById(id + '-clear').classList.contains('d-none') && document.getElementById(id).value !== '', OPT));
await page.click('#' + OPT + '-clear');
ok('Clear empties the value and shows the placeholder again', await page.evaluate((id) => document.getElementById(id).value === '' && document.getElementById(id + '-open').textContent.includes('manager'), OPT));
// a manager sees only their reports through the same modal
const mctx = await browser.newContext({ viewport: { width: 1280, height: 800 } }); const mp = await mctx.newPage();
await mp.goto(sso('1002', 'Picker Manager', 'write', 'user', 'manager')); await mp.goto(B + FORM); await mp.click('#' + FIELD + '-open'); await mp.waitForSelector('#record-picker .record-picker-row');
const mine = await mp.$$eval('#record-picker .record-picker-row', r => r.map(x => x.dataset.label));
ok('a manager\'s modal lists exactly their reports (' + mine.join(', ') + ')', mine.length === 3 && mine.every(x => x.startsWith('Picker Report')));
await mctx.close();
// phone, light
const phone = await browser.newContext({ viewport: { width: 375, height: 740 }, storageState: await ctx.storageState() });
const p2 = await phone.newPage(); p2.on('pageerror', e => errors.push(e.message));
await p2.goto(B + FORM); await p2.waitForSelector('#' + FIELD + '-open');
ok('no horizontal scroll at 375px', await p2.evaluate(() => document.documentElement.scrollWidth <= 375));
await p2.screenshot({ path: S + '/3-form-phone.png' });
await p2.click('#' + FIELD + '-open'); await p2.waitForSelector('#record-picker.show'); await p2.waitForSelector('#record-picker .record-picker-row');
await p2.type('#record-picker-search', 'pick'); await p2.waitForTimeout(600);
const box = await p2.evaluate(() => { const r = document.querySelector('#record-picker .modal-content').getBoundingClientRect(); return [r.width, r.height]; });
ok('the modal fills the phone screen (' + box.join('x') + ')', box[0] >= 374 && box[1] >= 700);
const rowH = await p2.evaluate(() => document.querySelector('#record-picker .record-picker-row').getBoundingClientRect().height);
ok('rows are touch targets (' + rowH + 'px)', rowH >= 44);
await p2.screenshot({ path: S + '/4-picker-phone.png' });
await p2.evaluate(() => document.documentElement.classList.add('app-skin-dark')); await p2.waitForTimeout(200);
await p2.screenshot({ path: S + '/5-picker-phone-dark.png' });
await page.evaluate(() => document.documentElement.classList.add('app-skin-dark'));
await page.click('#' + FIELD + '-open'); await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker .record-picker-row'); await page.waitForTimeout(300);
await page.screenshot({ path: S + '/6-picker-desktop-dark.png' });
ok('no page errors', errors.length === 0);
if (errors.length) console.log('errors: ' + errors.join(' | '));
await browser.close();
