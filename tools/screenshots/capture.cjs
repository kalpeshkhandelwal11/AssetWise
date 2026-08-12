// Plain full-page screenshots of every documented screen.
// Run from the repo root (so OUT resolves): node tools/screenshots/capture.cjs
const { chromium } = require('playwright');
const { mkdirSync } = require('fs');

const BASE = process.env.SHOT_BASE || 'http://127.0.0.1:8123';
const OUT = process.env.SHOT_OUT || 'docs/user-guide/screenshots';
mkdirSync(OUT, { recursive: true });

const pages = [
  ['01-dashboard', '/dashboard'],
  ['02-assets-index', '/assets'],
  ['03-asset-create', '/assets/create'],
  ['04-asset-show', '/assets/1'],
  ['05-asset-edit', '/assets/1/edit'],
  ['06-categories', '/admin/categories'],
  ['07-category-create', '/admin/categories/create'],
  ['08-category-fields', '/admin/categories/1/fields'],
  ['09-category-field-create', '/admin/categories/1/fields/create'],
  ['10-bulk-import', '/assets/import'],
  ['11-bulk-export', '/assets/export'],
  ['12-tags', '/admin/tags'],
  ['13-movements', '/movements'],
  ['14-movement-create', '/movements/create'],
  ['15-movement-bulk', '/movements/bulk/create'],
  ['16-approvals', '/approvals'],
  ['17-maintenance', '/maintenance'],
  ['18-amc', '/amc'],
  ['19-warranty', '/warranty'],
  ['20-depreciation-methods', '/admin/depreciation-methods'],
  ['21-asset-depreciation', '/assets/1/depreciation'],
  ['22-depreciation-schedule', '/assets/1/depreciation/schedule'],
  ['23-disposals', '/disposals'],
  ['24-disposal-create', '/disposals/create'],
  ['25-kits', '/kits'],
  ['26-kit-create', '/kits/create'],
  ['27-kit-assign', '/kit-assignments/create'],
  ['28-kit-assignments', '/kit-assignments'],
  ['29-reports', '/reports'],
  ['30-report-asset-register', '/reports/asset_register'],
  ['31-report-depreciation', '/reports/depreciation_schedule'],
  ['32-report-maintenance', '/reports/maintenance'],
  ['33-notifications', '/notifications'],
  ['34-admin-users', '/admin/users'],
  ['35-admin-roles', '/admin/roles'],
  ['36-workflows', '/admin/workflows'],
  ['37-masters', '/admin/masters'],
  ['38-kit-settings', '/admin/settings/kits'],
];

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();

  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'admin@assetwise.test');
  await page.fill('input[name="password"]', 'Admin@1234');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 25000 }).catch(() => {});
  await page.waitForLoadState('load').catch(() => {});
  console.log('after login:', page.url());

  let ok = 0, fail = 0;
  for (const [name, path] of pages) {
    try {
      const resp = await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle', timeout: 20000 });
      await page.waitForTimeout(400);
      await page.screenshot({ path: `${OUT}/${name}.png`, fullPage: true });
      console.log(`  ok   ${name} (${resp && resp.status()}) ${path}`);
      ok++;
    } catch (e) {
      console.log(`  FAIL ${name} ${path} -> ${String(e.message).split('\n')[0]}`);
      fail++;
    }
  }
  console.log(`\nDONE: ${ok} captured, ${fail} failed`);
  await browser.close();
})();
