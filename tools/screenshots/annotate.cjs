// Re-captures the walked-through screens with call-out boxes/labels on the specific
// fields and actions the handbook describes. Overwrites those files from capture.cjs.
// Run from the repo root: node tools/screenshots/annotate.cjs
const { chromium } = require('playwright');

const BASE = process.env.SHOT_BASE || 'http://127.0.0.1:8123';
const OUT = process.env.SHOT_OUT || 'docs/user-guide/screenshots';

// Injected into the page: outline + labelled callout for each target.
// A target is { sel } (CSS) or { text, tag } (first element of `tag` containing `text` ),
// plus a `label` and optional `color`.
function annotate(targets) {
  const RED = '#ef4444', PURPLE = '#7c3aed';
  function find(t) {
    if (t.sel) return document.querySelector(t.sel);
    if (t.text) {
      const tags = (t.tag || 'a,button,label,th,h1,h2,p,span,div').split(',');
      for (const tag of tags) {
        for (const el of document.querySelectorAll(tag)) {
          if ((el.textContent || '').trim().includes(t.text)) return el;
        }
      }
    }
    return null;
  }
  for (const t of targets) {
    const el = find(t);
    if (!el) continue;
    const color = t.color || RED;
    el.style.outline = '3px solid ' + color;
    el.style.outlineOffset = '3px';
    el.style.boxShadow = '0 0 0 4px ' + (color === PURPLE ? 'rgba(124,58,237,.20)' : 'rgba(239,68,68,.20)');
    if (t.label) {
      const r = el.getBoundingClientRect();
      const top = Math.max(2, r.top + window.scrollY - 24);
      const left = r.left + window.scrollX;
      const b = document.createElement('div');
      b.textContent = t.label;
      b.style.cssText = 'position:absolute;z-index:99999;background:' + color + ';color:#fff;'
        + 'font:600 12px/1.2 -apple-system,Segoe UI,sans-serif;padding:4px 8px;border-radius:6px;'
        + 'box-shadow:0 2px 6px rgba(0,0,0,.35);white-space:nowrap;pointer-events:none;';
      b.style.top = top + 'px';
      b.style.left = left + 'px';
      document.body.appendChild(b);
    }
  }
}

const jobs = [
  {
    file: '03-asset-create', path: '/assets/create',
    pre: async (p) => {
      await p.selectOption('#category_id', { label: 'IT Equipment' }).catch(() => {});
      await p.waitForSelector('[name^="fields["]', { timeout: 5000 }).catch(() => {});
      await p.waitForTimeout(600);
    },
    targets: [
      { sel: '#category_id', label: '1  Choose a category' },
      { sel: '[name^="fields["]', label: '2  The category’s custom fields load here', color: '#7c3aed' },
      { text: 'Create Asset', tag: 'button', label: 'Save the asset' },
    ],
  },
  {
    file: '08-category-fields', path: '/admin/categories/1/fields',
    targets: [
      { text: 'New Field', tag: 'a', label: 'Add a custom field' },
      { sel: 'table', label: 'Fields defined for this category' },
    ],
  },
  {
    file: '09-category-field-create', path: '/admin/categories/1/fields/create',
    pre: async (p) => { await p.selectOption('#field_type', 'dropdown').catch(() => {}); await p.waitForTimeout(400); },
    targets: [
      { sel: '#field_type', label: 'Field type' },
      { sel: 'input[name="is_required"]', label: 'Required on the form?' },
      { sel: 'input[name="is_searchable"]', label: 'Usable as a filter?' },
      { text: 'Add option', tag: 'button', label: 'Dropdown choices' },
    ],
  },
  {
    file: '04-asset-show', path: '/assets/1',
    targets: [
      { text: 'Kits', tag: 'button', label: 'Kit memberships' },
      { text: 'Request Disposal', tag: 'a', label: 'Asset actions' },
    ],
  },
  {
    file: '21-asset-depreciation', path: '/assets/1/depreciation',
    targets: [
      { sel: '#depreciation_method_id', label: 'Method' },
      { sel: '#salvage_value', label: 'Fixed salvage…' },
      { sel: '#salvage_percent', label: '…or a percentage' },
      { text: 'Submit for Approval', tag: 'button', label: 'Submit for approval' },
    ],
  },
  {
    file: '14-movement-create', path: '/movements/create',
    targets: [
      { sel: '#asset_id', label: 'Pick the asset' },
      { sel: '#movement_type_id', label: 'Type — destination fields adapt' },
    ],
  },
  {
    file: '27-kit-assign', path: '/kit-assignments/create',
    pre: async (p) => { await p.selectOption('#movement_type_id', { label: 'Assignment' }).catch(() => {}); await p.waitForTimeout(400); },
    targets: [
      { sel: 'input[value="kit"]', label: 'Saved kit or ad-hoc bundle' },
      { sel: '#movement_type_id', label: 'Movement type' },
      { sel: '#to_custodian_id', label: 'Destination (varies by type)' },
    ],
  },
  {
    file: '30-report-asset-register', path: '/reports/asset_register',
    targets: [
      { text: 'Export Excel', tag: 'button', label: 'Export to Excel' },
      { text: 'Export PDF', tag: 'button', label: 'or PDF' },
    ],
  },
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

  let ok = 0, fail = 0;
  for (const job of jobs) {
    try {
      await page.goto(`${BASE}${job.path}`, { waitUntil: 'networkidle', timeout: 20000 });
      if (job.pre) await job.pre(page);
      await page.waitForTimeout(300);
      await page.evaluate(annotate, job.targets);
      await page.waitForTimeout(200);
      await page.screenshot({ path: `${OUT}/${job.file}.png`, fullPage: true });
      console.log(`  ok   ${job.file}`);
      ok++;
    } catch (e) {
      console.log(`  FAIL ${job.file} -> ${String(e.message).split('\n')[0]}`);
      fail++;
    }
  }
  console.log(`\nANNOTATED: ${ok} ok, ${fail} failed`);
  await browser.close();
})();
