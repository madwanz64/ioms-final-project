// Static file server sederhana (tanpa dependency) untuk menjalankan prototype
// prototype/ secara lokal. Diperlukan karena fetch() ke file .json gagal di
// bawah protokol file:// (kebijakan CORS browser) — harus lewat http://.
// Pakai: node scripts/static-server.js [port]
//
// Server ini JUGA menyediakan satu endpoint JSON asli (API-01):
//   GET /api/products/:sku/availability?token=<userId>
// CATATAN JUJUR soal keterbatasan endpoint ini (dicatat di ai-usage-log.md juga):
// - Server Node ini TIDAK punya akses ke localStorage browser (itu sepenuhnya
//   sisi client), jadi endpoint membaca dari prototype/data/*.json (seed),
//   bukan data runtime yang sudah diubah lewat UI. Demo mengasumsikan data seed.
// - `token` di sini HANYA userId polos yang dicocokkan ke users.json — ini
//   BUKAN autentikasi sungguhan (tidak ada session/JWT), sekadar simulasi agar
//   perilaku 200/401/404 + Content-Type sesuai kontrak API-01 bisa didemokan
//   sebelum backend PHP+MySQL (dengan session PHP asli) menggantikannya.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');

const PORT = process.argv[2] || 5173;
const ROOT = path.join(__dirname, '..', 'prototype');
const DATA_DIR = path.join(ROOT, 'data');

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.svg': 'image/svg+xml',
};

function readJson(file) {
  return JSON.parse(fs.readFileSync(path.join(DATA_DIR, file), 'utf8'));
}

function sendJson(res, status, body) {
  const payload = JSON.stringify(body);
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' });
  res.end(payload);
}

function handleProductAvailability(req, res, sku) {
  const url = new URL(req.url, 'http://localhost');
  const token = url.searchParams.get('token');

  const users = readJson('users.json');
  const authorized = users.some((u) => String(u.id) === String(token) && u.active);
  if (!authorized) {
    sendJson(res, 401, { error: 'Unauthorized', message: 'Token tidak valid atau tidak disertakan.' });
    return;
  }

  const products = readJson('products.json');
  const product = products.find((p) => p.sku.toLowerCase() === sku.toLowerCase());
  if (!product) {
    sendJson(res, 404, { error: 'Not Found', message: `Produk dengan SKU '${sku}' tidak ditemukan.` });
    return;
  }

  const warehouses = readJson('warehouses.json');
  const stock = readJson('product-stock.json').filter((s) => s.sku === product.sku);
  const perWarehouse = stock.map((s) => ({
    warehouseId: s.warehouseId,
    warehouseName: warehouses.find((w) => w.id === s.warehouseId)?.name || null,
    quantity: s.quantity,
  }));

  sendJson(res, 200, {
    sku: product.sku,
    name: product.name,
    totalStock: perWarehouse.reduce((sum, w) => sum + w.quantity, 0),
    stock: perWarehouse,
    _note: 'Data dari seed prototype/data/*.json, bukan localStorage runtime browser.',
  });
}

const server = http.createServer((req, res) => {
  const pathname = req.url.split('?')[0];

  const apiMatch = pathname.match(/^\/api\/products\/([^/]+)\/availability\/?$/);
  if (apiMatch) {
    try {
      handleProductAvailability(req, res, decodeURIComponent(apiMatch[1]));
    } catch (err) {
      sendJson(res, 500, { error: 'Internal Server Error', message: err.message });
    }
    return;
  }

  let filePath = decodeURIComponent(pathname);
  if (filePath === '/') filePath = '/index.html';
  const fullPath = path.join(ROOT, filePath);

  if (!fullPath.startsWith(ROOT)) {
    res.writeHead(403);
    res.end('Forbidden');
    return;
  }

  fs.readFile(fullPath, (err, data) => {
    if (err) {
      res.writeHead(404, { 'Content-Type': 'text/plain' });
      res.end('Not found: ' + filePath);
      return;
    }
    const ext = path.extname(fullPath);
    res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream' });
    res.end(data);
  });
});

server.listen(PORT, () => console.log(`Static server jalan di http://localhost:${PORT}`));
