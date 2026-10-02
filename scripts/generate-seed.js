// Generator sekali-pakai untuk data seed prototype (prototype/data/*.json).
// Dijalankan manual: node scripts/generate-seed.js
// Data ini akan digantikan oleh database/schema-and-seed.sql saat backend PHP+MySQL dibangun (DB-01).
const fs = require('fs');
const path = require('path');

const OUT = path.join(__dirname, '..', 'prototype', 'data');
fs.mkdirSync(OUT, { recursive: true });

function write(name, data) {
  fs.writeFileSync(path.join(OUT, name), JSON.stringify(data, null, 2) + '\n', 'utf8');
  console.log('wrote', name);
}

// ===== Categories =====
const categories = [
  { id: 1, name: 'Elektronik', description: 'Perangkat dan aksesoris elektronik' },
  { id: 2, name: 'ATK', description: 'Alat tulis kantor' },
  { id: 3, name: 'Aksesoris', description: 'Aksesoris pendukung perangkat' },
];
write('categories.json', categories);

// ===== Warehouses =====
const warehouses = [
  { id: 1, name: 'Gudang Jakarta', location: 'Jl. Industri No. 12, Jakarta Utara', active: true },
  { id: 2, name: 'Gudang Surabaya', location: 'Jl. Rungkut Industri No. 5, Surabaya', active: true },
];
write('warehouses.json', warehouses);

// ===== Users =====
// CATATAN KEAMANAN: password disimpan plaintext & dibandingkan di client-side
// HANYA karena belum ada backend PHP. Ini BUKAN pola aman dan wajib diganti
// dengan password_hash()/password_verify() di server saat AUTH-01 diimplementasikan.
const users = [
  { id: 1, name: 'Budi Santoso', email: 'admin@ioms.test', password: 'admin123', role: 'Admin', active: true },
  { id: 2, name: 'Sinta Wulandari', email: 'sinta@ioms.test', password: 'sales123', role: 'Sales', active: true },
  { id: 3, name: 'Doni Pratama', email: 'doni@ioms.test', password: 'sales123', role: 'Sales', active: true },
  { id: 4, name: 'Rudi Hartono', email: 'rudi@ioms.test', password: 'gudang123', role: 'Warehouse Staff', active: true },
  { id: 5, name: 'Agus Setiawan', email: 'agus@ioms.test', password: 'gudang123', role: 'Warehouse Staff', active: false },
  { id: 6, name: 'Wulan Sari', email: 'wulan@ioms.test', password: 'gudang123', role: 'Warehouse Staff', active: true },
];
write('users.json', users);

// ===== Customers =====
const customers = [
  { id: 1, name: 'PT Sumber Makmur', contact: '021-7778899', address: 'Jakarta Selatan', active: true },
  { id: 2, name: 'Toko Jaya Abadi', contact: '0812-1122-3344', address: 'Surabaya', active: true },
  { id: 3, name: 'CV Berkah', contact: '0819-5566-7788', address: 'Bandung', active: true },
  { id: 4, name: 'UD Makmur Sentosa', contact: '0857-1234-5678', address: 'Semarang', active: true },
  { id: 5, name: 'Toko Elektronik Cahaya', contact: '0813-9900-1122', address: 'Jakarta Barat', active: true },
];
write('customers.json', customers);

// ===== Suppliers =====
const suppliers = [
  { id: 1, name: 'CV Elektronik Jaya', contact: '021-5551234', address: 'Jakarta Barat', active: true },
  { id: 2, name: 'PT Kertas Nusantara', contact: '021-5559876', address: 'Tangerang', active: true },
  { id: 3, name: 'UD Sumber Aksesoris', contact: '0813-9988-2211', address: 'Bekasi', active: true },
  { id: 4, name: 'PT Sumber Plastik', contact: '024-8765432', address: 'Semarang', active: true },
  { id: 5, name: 'Toko Aksesoris Komputer', contact: '0857-2233-4455', address: 'Surabaya', active: false },
];
write('suppliers.json', suppliers);

// ===== Products =====
const productDefs = [
  ['Kabel HDMI 2m', 3, 'pcs', 30000, 45000, 10],
  ['Mouse Wireless', 1, 'pcs', 85000, 120000, 15],
  ['Keyboard Mechanical', 1, 'pcs', 480000, 650000, 8],
  ['Kertas A4 80gr', 2, 'rim', 42000, 52000, 20],
  ['Map Plastik', 2, 'pcs', 3000, 5000, 30],
  ['Monitor LED 24"', 1, 'pcs', 1450000, 1850000, 5],
  ['Headset USB', 3, 'pcs', 95000, 135000, 12],
  ['Flashdisk 32GB', 1, 'pcs', 55000, 80000, 25],
  ['Printer Inkjet', 1, 'pcs', 890000, 1150000, 4],
  ['Tinta Printer Hitam', 2, 'pcs', 38000, 55000, 20],
  ['Pulpen Pilot', 2, 'pcs', 2500, 4000, 50],
  ['Stapler Kecil', 2, 'pcs', 12000, 18000, 15],
  ['Isi Staples No.10', 2, 'box', 4000, 7000, 30],
  ['Kabel LAN 5m', 3, 'pcs', 25000, 38000, 12],
  ['Adaptor Charger Laptop', 1, 'pcs', 210000, 290000, 6],
  ['Speaker Bluetooth Mini', 1, 'pcs', 140000, 195000, 10],
  ['Webcam HD', 1, 'pcs', 175000, 240000, 8],
  ['Tas Laptop', 3, 'pcs', 95000, 140000, 10],
  ['Whiteboard 60x90', 2, 'pcs', 110000, 155000, 5],
  ['Spidol Whiteboard', 2, 'pcs', 6000, 9000, 40],
  ['Router WiFi', 1, 'pcs', 220000, 310000, 6],
  ['Power Bank 10000mAh', 3, 'pcs', 105000, 150000, 10],
  ['Kalkulator Scientific', 2, 'pcs', 65000, 95000, 10],
  ['Ordner Arsip', 2, 'pcs', 15000, 22000, 20],
  ['Mousepad Gaming', 3, 'pcs', 25000, 40000, 15],
  ['Hardisk Eksternal 1TB', 1, 'pcs', 520000, 690000, 5],
  ['Proyektor Mini', 1, 'pcs', 650000, 850000, 4],
  ['Lampu Meja LED', 1, 'pcs', 85000, 125000, 8],
  ['Cutter Kertas', 2, 'pcs', 8000, 14000, 15],
  ['Rak Arsip Kecil', 2, 'pcs', 95000, 140000, 6],
  ['Charger Wireless', 3, 'pcs', 120000, 175000, 10],
  ['Baterai AA (4pcs)', 3, 'pack', 15000, 25000, 20],
];

const products = productDefs.map((p, idx) => ({
  sku: `SKU-${String(idx + 1).padStart(4, '0')}`,
  name: p[0],
  categoryId: p[1],
  unit: p[2],
  buyPrice: p[3],
  sellPrice: p[4],
  reorderPoint: p[5],
  imageUrl: null,
  active: true,
}));
write('products.json', products);

// ===== Product Stock (per warehouse) =====
// Sengaja dibuat beberapa produk di bawah reorder point (untuk demo DASH-01 & badge low-stock),
// dan beberapa produk stoknya 0 di salah satu gudang.
const stockOverrides = {
  'SKU-0001': [3, 1], // Kabel HDMI 2m -> total 4, reorder 10 => low stock
  'SKU-0002': [1, 1], // Mouse Wireless -> total 2, reorder 15 => low stock
  'SKU-0004': [5, 3], // Kertas A4 -> total 8, reorder 20 => low stock
  'SKU-0006': [2, 0], // Monitor LED -> total 2, reorder 5 => low stock
  'SKU-0009': [1, 0], // Printer Inkjet -> total 1, reorder 4 => low stock
};
const productStock = [];
products.forEach((p, idx) => {
  const override = stockOverrides[p.sku];
  const qtyWarehouse1 = override ? override[0] : 20 + ((idx * 7) % 60);
  const qtyWarehouse2 = override ? override[1] : 10 + ((idx * 5) % 40);
  productStock.push({ sku: p.sku, warehouseId: 1, quantity: qtyWarehouse1, updatedAt: '2026-09-01T09:00:00' });
  productStock.push({ sku: p.sku, warehouseId: 2, quantity: qtyWarehouse2, updatedAt: '2026-09-01T09:00:00' });
});
write('product-stock.json', productStock);

// ===== Sales Orders =====
const soDefs = [
  // [customerId, createdBy, approvedBy, warehouseId, status, dateOffsetDays, items:[[skuIdx,qty]]]
  [1, 2, 1, 1, 'Fulfilled', 14, [[1, 2], [2, 5]]],
  [2, 2, 1, 1, 'Fulfilled', 13, [[3, 10]]],
  [3, 3, 1, 2, 'Fulfilled', 12, [[4, 20]]],
  [1, 2, 1, 1, 'Cancelled', 11, [[5, 3]]],
  [4, 3, 1, 2, 'Fulfilled', 10, [[6, 4]]],
  [2, 2, 1, 1, 'Approved', 6, [[1, 1]]],
  [5, 3, 1, 1, 'Approved', 5, [[7, 6]]],
  [3, 2, null, 2, 'PendingApproval', 4, [[2, 3]]],
  [1, 3, null, 1, 'PendingApproval', 3, [[8, 2], [9, 1]]],
  [4, 2, null, 1, 'PendingApproval', 2, [[10, 15]]],
  [2, 3, null, 2, 'Draft', 1, [[11, 5]]],
  [5, 2, null, 1, 'Draft', 1, [[12, 10]]],
  [3, 3, 1, 1, 'Fulfilled', 9, [[13, 6]]],
  [1, 2, 1, 2, 'Fulfilled', 8, [[14, 2]]],
  [3, 3, 1, 1, 'Fulfilled', 7, [[15, 2]]],
  [4, 2, null, 2, 'PendingApproval', 1, [[27, 1]]],
];

const productBySkuIdx = (i) => products[i - 1];
let soId = 1;
const salesOrders = soDefs.map((d) => {
  const [customerId, createdBy, approvedBy, warehouseId, status, dayOffset, itemDefs] = d;
  const items = itemDefs.map(([skuIdx, qty]) => {
    const prod = productBySkuIdx(skuIdx);
    return { sku: prod.sku, qty, price: prod.sellPrice };
  });
  const date = new Date('2026-09-04');
  date.setDate(date.getDate() - dayOffset);
  const order = {
    id: soId,
    orderNo: `SO-2026-${String(soId).padStart(4, '0')}`,
    customerId,
    warehouseId,
    createdBy,
    approvedBy,
    status,
    createdAt: date.toISOString().slice(0, 10),
    items,
  };
  soId += 1;
  return order;
});
write('sales-orders.json', salesOrders);

// ===== Purchase Orders =====
const poDefs = [
  // [supplierId, warehouseId, status, dayOffset, items:[[skuIdx, qtyOrdered, receivedQty]]]
  [1, 1, 'Received', 20, [[6, 5, 5]]],
  [2, 1, 'Received', 18, [[4, 50, 50], [5, 100, 100]]],
  [3, 2, 'Received', 16, [[1, 20, 20]]],
  [1, 1, 'PartiallyReceived', 10, [[9, 10, 4]]],
  [2, 1, 'PartiallyReceived', 8, [[4, 30, 10], [10, 40, 40]]],
  [1, 1, 'Ordered', 5, [[2, 15, 0]]],
  [4, 2, 'Ordered', 4, [[5, 50, 0]]],
  [3, 2, 'Draft', 2, [[7, 10, 0]]],
  [2, 1, 'Draft', 1, [[11, 100, 0]]],
  [1, 1, 'Cancelled', 12, [[3, 5, 0]]],
  [4, 1, 'Ordered', 3, [[28, 20, 0]]],
  [2, 2, 'Draft', 1, [[29, 50, 0]]],
];

let poId = 1;
const purchaseOrders = poDefs.map((d) => {
  const [supplierId, warehouseId, status, dayOffset, itemDefs] = d;
  const items = itemDefs.map(([skuIdx, qty, receivedQty]) => {
    const prod = productBySkuIdx(skuIdx);
    return { sku: prod.sku, qty, receivedQty, buyPrice: prod.buyPrice };
  });
  const date = new Date('2026-09-04');
  date.setDate(date.getDate() - dayOffset);
  const order = {
    id: poId,
    orderNo: `PO-2026-${String(poId).padStart(4, '0')}`,
    supplierId,
    warehouseId,
    status,
    createdAt: date.toISOString().slice(0, 10),
    items,
  };
  poId += 1;
  return order;
});
write('purchase-orders.json', purchaseOrders);

// ===== Stock Ledger (dari SO Fulfilled + PO yang sudah menerima barang) =====
// Setelah baris Receipt/Issue dari order dibuat, ledger direkonsiliasi (lihat
// reconcileLedger di bawah) supaya SUM(quantity) ledger per produk+gudang
// SAMA PERSIS dengan product-stock.json — brief §1.3: setiap angka stok harus
// bisa ditelusuri ke baris ledger.
let ledgerId = 1;
const stockLedger = [];
salesOrders.filter((o) => o.status === 'Fulfilled').forEach((o) => {
  o.items.forEach((item) => {
    stockLedger.push({
      id: ledgerId++,
      sku: item.sku,
      warehouseId: o.warehouseId,
      type: 'Issue',
      quantity: -item.qty,
      refType: 'SO',
      refId: o.orderNo,
      byUserId: 4,
      timestamp: `${o.createdAt}T14:00:00`,
    });
  });
});
purchaseOrders.forEach((o) => {
  o.items.forEach((item) => {
    if (item.receivedQty > 0) {
      stockLedger.push({
        id: ledgerId++,
        sku: item.sku,
        warehouseId: o.warehouseId,
        type: 'Receipt',
        quantity: item.receivedQty,
        refType: 'PO',
        refId: o.orderNo,
        byUserId: 4,
        timestamp: `${o.createdAt}T10:00:00`,
      });
    }
  });
});
// ===== Rekonsiliasi ledger <-> product stock =====
// Untuk setiap produk+gudang:
//  1. Saldo awal (Adjustment, ref OPENING) secukupnya agar saldo berjalan
//     tidak pernah negatif saat movement order direplay urut waktu.
//  2. Jika total masih berbeda dari product-stock.json, tambahkan koreksi
//     stock opname (Adjustment, ref OPNAME-2026-09) di akhir periode.
// Hasilnya: SUM(ledger.quantity) == product_stock.quantity untuk semua baris.
function reconcileLedger() {
  const adjustments = [];
  productStock.forEach((stock) => {
    const movements = stockLedger
      .filter((l) => l.sku === stock.sku && l.warehouseId === stock.warehouseId)
      .sort((a, b) => a.timestamp.localeCompare(b.timestamp));
    let running = 0;
    let minRunning = 0;
    movements.forEach((m) => {
      running += m.quantity;
      minRunning = Math.min(minRunning, running);
    });
    const opening = Math.max(stock.quantity - running, -minRunning, 0);
    const base = { sku: stock.sku, warehouseId: stock.warehouseId, type: 'Adjustment', refType: 'ADJ', byUserId: 1 };
    if (opening > 0) {
      adjustments.push({ ...base, quantity: opening, refId: 'OPENING', timestamp: '2026-08-01T08:00:00' });
    }
    const correction = stock.quantity - (opening + running);
    if (correction !== 0) {
      adjustments.push({ ...base, quantity: correction, refId: 'OPNAME-2026-09', timestamp: '2026-09-01T09:00:00' });
    }
  });
  adjustments.forEach((a) => stockLedger.push({ id: ledgerId++, ...a }));
}
reconcileLedger();
stockLedger.sort((a, b) => a.timestamp.localeCompare(b.timestamp) || a.id - b.id);
stockLedger.forEach((l, i) => {
  l.id = i + 1;
});
write('stock-ledger.json', stockLedger);

console.log('Selesai. Total produk:', products.length, '| Total SO:', salesOrders.length, '| Total PO:', purchaseOrders.length);
