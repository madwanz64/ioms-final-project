// Alat bantu verifikasi UI (bukan bagian aplikasi, bukan dependency project).
// Dipakai 2026-10-07 untuk bukti UI-01/VIEW-01/API-01: membuka 21 halaman pada layar
// desktop (1366px) dan mobile (360px) di Chrome headless, memeriksa halaman tidak scroll
// horizontal, menguji Fetch API (form SO & detail produk), lalu menyimpan screenshot.
//
// Cara menjalankan (butuh Node.js, Chrome terpasang, aplikasi Docker di localhost:8080
// dalam kondisi seed):
//   npm install --no-save puppeteer-core@23
//   node docs/testing/tools/ui-check.mjs docs/testing/screenshots
// Path Chrome di bawah (CHROME) sesuaikan bila berbeda.
// Uji UI di Chrome sungguhan (headless): responsif 360px & desktop, Fetch API, console error,
// Uji UI di Chrome sungguhan (headless): responsif 360px & desktop, Fetch API, console error,
// dan screenshot bukti UI-01/VIEW-01. Menguji aplikasi Docker di http://localhost:8080.
import puppeteer from 'puppeteer-core';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';

const BASE = 'http://localhost:8080';
const OUT = process.argv[2];
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
mkdirSync(OUT, { recursive: true });
// Hapus hasil run sebelumnya: bila script gagal di tengah, tidak ada hasil basi yang terbaca sebagai hasil baru.
rmSync(`${OUT}/../ui-check-result.json`, { force: true });

const VIEWPORTS = {
  desktop: { width: 1366, height: 768 },
  mobile: { width: 360, height: 740, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
};
const ACCOUNTS = {
  admin: ['admin@ioms.test', 'admin123'],
  sales: ['sinta@ioms.test', 'sales123'],
  warehouse: ['rudi@ioms.test', 'gudang123'],
};
// [nama file, role (null = tanpa login), path]
const PAGES = [
  ['01-login', null, '/login'],
  ['02-dashboard-admin', 'admin', '/dashboard'],
  ['03-dashboard-sales', 'sales', '/dashboard'],
  ['04-dashboard-warehouse', 'warehouse', '/dashboard'],
  ['05-produk-daftar', 'admin', '/products'],
  ['06-produk-low-stock-hal2', 'admin', '/products?sort=name_desc&page=2'],
  ['07-produk-kosong', 'admin', '/products?q=tidak-ada-produk-ini'],
  ['08-produk-detail', 'warehouse', '/products/SKU-0001'],
  ['09-produk-form', 'admin', '/products/create'],
  ['10-po-daftar', 'warehouse', '/purchase-orders'],
  ['11-po-detail-terima', 'warehouse', '/purchase-orders/5'],
  ['12-po-form', 'warehouse', '/purchase-orders/create'],
  ['13-so-daftar-sales', 'sales', '/sales-orders'],
  ['14-so-detail-approve', 'admin', '/sales-orders/8'],
  ['15-so-form', 'sales', '/sales-orders/create'],
  ['16-so-kosong', 'sales', '/sales-orders?q=tidak-ada'],
  ['17-laporan', 'admin', '/reports?from=2026-08-01&to=2026-08-31'],
  ['18-user-daftar', 'admin', '/users'],
  ['19-profil', 'sales', '/profile'],
  ['20-error-403', 'sales', '/users'],
  ['21-error-404', 'admin', '/products/TIDAK-ADA'],
  ['23-transfer-daftar', 'warehouse', '/stock-transfers'],
  ['24-transfer-form', 'warehouse', '/stock-transfers/create'],
  ['25-po-detail-draft-warehouse', 'warehouse', '/purchase-orders/8'],
  ['26-transfer-sales-403', 'sales', '/stock-transfers'],
];

const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--lang=id-ID'] });
const results = [];
const consoleErrors = [];

async function login(page, role) {
  await page.goto(BASE + '/login', { waitUntil: 'networkidle0' });
  const [email, password] = ACCOUNTS[role];
  await page.type('#email', email);
  await page.type('#password', password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type="submit"]')]);
}

// Elemen yang keluar dari layar TANPA berada di dalam wadah scroll horizontal (overflow-x auto/scroll).
const OVERFLOW_PROBE = () => {
  const vw = document.documentElement.clientWidth;
  const offenders = [];
  for (const el of document.querySelectorAll('body *')) {
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.right <= vw + 1) continue;
    let p = el.parentElement, scrolled = false;
    while (p && p !== document.body) {
      const ox = getComputedStyle(p).overflowX;
      if (ox === 'auto' || ox === 'scroll' || ox === 'hidden') { scrolled = true; break; }
      p = p.parentElement;
    }
    if (!scrolled) offenders.push(`${el.tagName.toLowerCase()}${el.className && typeof el.className === 'string' ? '.' + el.className.split(' ')[0] : ''} (right=${Math.round(r.right)})`);
  }
  return { pageScrollsX: document.documentElement.scrollWidth > vw + 1, scrollWidth: document.documentElement.scrollWidth, vw, offenders: offenders.slice(0, 5) };
};

for (const [vpName, viewport] of Object.entries(VIEWPORTS)) {
  const sessions = {};
  for (const [name, role, path] of PAGES) {
    const key = role ?? 'guest';
    if (!sessions[key]) {
      const context = await browser.createBrowserContext();
      const page = await context.newPage();
      await page.setViewport(viewport);
      page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(`[${vpName}/${key}] ${m.text()}`); });
      page.on('pageerror', (e) => consoleErrors.push(`[${vpName}/${key}] pageerror: ${e.message}`));
      if (role) await login(page, role);
      sessions[key] = page;
    }
    const page = sessions[key];
    const response = await page.goto(BASE + path, { waitUntil: 'networkidle0' });
    await new Promise((r) => setTimeout(r, 350)); // animasi masuk CSS selesai sebelum screenshot
    const probe = await page.evaluate(OVERFLOW_PROBE);
    await page.screenshot({ path: `${OUT}/${vpName}-${name}.jpg`, type: 'jpeg', quality: 72, fullPage: true });
    results.push({ vp: vpName, name, status: response.status(), ...probe });
  }
  for (const p of Object.values(sessions)) await p.browserContext().close();
}

// ---- Fetch API di browser sungguhan ----
const fetchChecks = [];
{
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.setViewport(VIEWPORTS.desktop);
  await login(page, 'sales');
  await page.goto(BASE + '/sales-orders/create', { waitUntil: 'networkidle0' });
  await page.select('[name="warehouse_id"]', '1');
  await page.select('[name="items[0][sku]"]', 'SKU-0006');
  await page.type('[name="items[0][qty]"]', '5');
  await page.$eval('[name="items[0][qty]"]', (el) => el.dispatchEvent(new Event('change', { bubbles: true })));
  await page.waitForFunction(() => /Stok tersedia/.test(document.querySelector('[data-line-stock]')?.textContent ?? ''), { timeout: 5000 });
  const hint = await page.$eval('[data-line-stock]', (el) => ({ text: el.textContent, insufficient: el.classList.contains('insufficient') }));
  const priceText = await page.$eval('[name="items[0][price]"]', (el) => el.value);
  fetchChecks.push({ check: 'Form SO: petunjuk stok SKU-0006 @ Gudang Jakarta, qty 5', expected: 'Stok tersedia: 2 pcs + ditandai kurang', actual: `${hint.text} | insufficient=${hint.insufficient} | harga=${priceText}`, pass: hint.text.trim() === 'Stok tersedia: 2 pcs' && hint.insufficient && priceText === '1850000' });

  await page.select('[name="warehouse_id"]', '2');
  await page.$eval('[name="warehouse_id"]', (el) => el.dispatchEvent(new Event('change', { bubbles: true })));
  await page.waitForFunction(() => /Stok tersedia: 0/.test(document.querySelector('[data-line-stock]')?.textContent ?? ''), { timeout: 5000 });
  fetchChecks.push({ check: 'Ganti gudang asal ke Surabaya', expected: 'Stok tersedia: 0 pcs', actual: await page.$eval('[data-line-stock]', (el) => el.textContent), pass: true });

  await page.click('[data-add-line]');
  const rows = await page.$$eval('tr[data-line]', (trs) => trs.map((tr) => tr.querySelector('select').name));
  fetchChecks.push({ check: 'Tambah baris item (re-index nama field)', expected: 'items[3][sku] ada', actual: rows.join(', '), pass: rows.includes('items[3][sku]') });
  await page.screenshot({ path: `${OUT}/desktop-22-so-form-fetch-stok.jpg`, type: 'jpeg', quality: 72, fullPage: true });

  // K-07: harga dari form tersimpan (Sales mengubah harga katalog).
  await page.goto(BASE + '/sales-orders/create', { waitUntil: 'networkidle0' });
  await page.select('[name="customer_id"]', '1');
  await page.select('[name="warehouse_id"]', '1');
  await page.select('[name="items[0][sku]"]', 'SKU-0003');
  await page.type('[name="items[0][qty]"]', '2');
  await page.$eval('[name="items[0][price]"]', (el) => { el.value = '600000'; });
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type="submit"].primary')]);
  const soPrice = await page.$$eval('table.data-table td.num', (tds) => tds.map((td) => td.textContent.trim()));
  fetchChecks.push({ check: 'K-07: SO disimpan dengan harga dari form (600.000, katalog 650.000)', expected: 'Rp 600.000 di detail', actual: page.url() + ' | ' + soPrice.join(' / '), pass: soPrice.includes('Rp 600.000') });

  await page.goto(BASE + '/products/SKU-0001', { waitUntil: 'networkidle0' });
  await page.click('[data-refresh-stock]');
  await page.waitForFunction(() => /Diperbarui/.test(document.querySelector('[data-refresh-status]')?.textContent ?? ''), { timeout: 5000 });
  const status = await page.$eval('[data-refresh-status]', (el) => el.textContent);
  fetchChecks.push({ check: 'Detail produk: tombol Muat ulang stok', expected: 'status "Diperbarui ..."', actual: status, pass: /Diperbarui/.test(status) });

  // Sesi berakhir: fetch dari halaman yang sudah terbuka harus mendapat 401 JSON dan pesan ramah.
  await page.deleteCookie(...(await page.cookies()));
  await page.click('[data-refresh-stock]');
  await page.waitForSelector('.toast', { timeout: 5000 });
  const toast = await page.$eval('.toast', (el) => el.textContent);
  fetchChecks.push({ check: 'Fetch setelah session dihapus', expected: 'toast "Sesi berakhir..." (401 JSON, bukan halaman login)', actual: toast, pass: /Sesi berakhir/.test(toast) });
  await context.close();
}

// ---- K-08: transfer stok lewat browser (Warehouse) ----
{
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.setViewport(VIEWPORTS.desktop);
  await login(page, 'warehouse');
  const draftPo = await (await page.goto(BASE + '/purchase-orders/8', { waitUntil: 'networkidle0' })).text();
  fetchChecks.push({ check: 'K-01: Warehouse melihat tombol "Tandai Ordered" pada PO Draft', expected: 'tombol ada', actual: /Tandai Ordered/.test(draftPo) ? 'ada' : 'tidak ada', pass: /Tandai Ordered/.test(draftPo) });
  await page.goto(BASE + '/stock-transfers/create', { waitUntil: 'networkidle0' });
  await page.select('[name="from_warehouse_id"]', '1');
  await page.select('[name="to_warehouse_id"]', '2');
  await page.select('[name="items[0][sku]"]', 'SKU-0004');
  await page.type('[name="items[0][qty]"]', '2');
  await page.$eval('[name="items[0][qty]"]', (el) => el.dispatchEvent(new Event('change', { bubbles: true })));
  await page.waitForFunction(() => /Stok tersedia/.test(document.querySelector('[data-line-stock]')?.textContent ?? ''), { timeout: 5000 });
  const hint = await page.$eval('[data-line-stock]', (el) => el.textContent);
  fetchChecks.push({ check: 'K-08: form transfer menampilkan stok gudang asal (SKU-0004 @ Jakarta)', expected: 'Stok tersedia: 5 rim', actual: hint, pass: hint.trim() === 'Stok tersedia: 5 rim' });
  await page.screenshot({ path: OUT + '/desktop-27-transfer-form-isi.jpg', type: 'jpeg', quality: 72, fullPage: true });
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type="submit"].primary')]);
  const detail = await page.$$eval('tbody tr', (trs) => trs.map((tr) => tr.textContent.replace(/\s+/g, ' ').trim()));
  const ledgerRows = detail.filter((t) => /Issue|Receipt/.test(t));
  fetchChecks.push({ check: 'K-08: transfer 2 rim Jakarta -> Surabaya tersimpan', expected: 'Issue -2 Jakarta + Receipt +2 Surabaya', actual: page.url() + ' | ' + ledgerRows.join(' || '), pass: ledgerRows.length === 2 && /Gudang Jakarta Issue -2/.test(ledgerRows[0]) && /Gudang Surabaya Receipt \+2/.test(ledgerRows[1]) });
  await page.screenshot({ path: OUT + '/desktop-28-transfer-detail.jpg', type: 'jpeg', quality: 72, fullPage: true });
  await context.close();
}

await browser.close();
writeFileSync(`${OUT}/../ui-check-result.json`, JSON.stringify({ results, fetchChecks, consoleErrors }, null, 2));

console.log('=== Responsif (halaman tidak boleh scroll horizontal; tabel lebar boleh scroll di dalam wadahnya)');
for (const r of results) {
  const ok = !r.pageScrollsX && r.offenders.length === 0 && r.status < 500;
  console.log(`${ok ? 'OK   ' : 'GAGAL'} ${r.vp.padEnd(7)} ${r.name.padEnd(26)} HTTP ${r.status}  scrollWidth=${r.scrollWidth}/${r.vw}${r.offenders.length ? '  keluar layar: ' + r.offenders.join('; ') : ''}`);
}
console.log('\n=== Fetch API di browser');
for (const f of fetchChecks) console.log(`${f.pass ? 'OK   ' : 'GAGAL'} ${f.check} -> ${f.actual}`);
console.log(`\n=== Console error JavaScript: ${consoleErrors.length}`);
consoleErrors.forEach((e) => console.log('  ' + e));
