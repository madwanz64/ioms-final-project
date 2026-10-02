/**
 * DataStore — "fake repository" berbasis JSON seed + localStorage.
 *
 * Kenapa begini: browser tidak bisa menulis balik ke file .json di disk.
 * Jadi file di /data/*.json hanya dipakai sebagai SEED (data awal), persis
 * seperti fungsi database/schema-and-seed.sql nanti (DB-01). Begitu seed
 * masuk localStorage, semua perubahan (tambah/edit produk, buat/approve
 * order, dst) disimpan di localStorage milik browser ini.
 *
 * KETERBATASAN YANG DISADARI (dicatat sebagai tech-debt, bukan disembunyikan):
 * - Data HANYA ada di browser ini, tidak dibagikan antar user/device seperti
 *   MySQL sungguhan akan lakukan nanti.
 * - Tidak ada transaksi atomik / proteksi race condition nyata (ARCH-02) —
 *   ini murni prototype tampilan & alur sebelum backend PHP dibangun.
 */
(function (global) {
  const PREFIX = 'ioms_';

  const FILES = {
    users: 'users.json',
    categories: 'categories.json',
    warehouses: 'warehouses.json',
    customers: 'customers.json',
    suppliers: 'suppliers.json',
    products: 'products.json',
    productStock: 'product-stock.json',
    salesOrders: 'sales-orders.json',
    purchaseOrders: 'purchase-orders.json',
    stockLedger: 'stock-ledger.json',
  };

  let seeded = false;
  let seedPromise = null;

  function key(name) {
    return PREFIX + name;
  }

  function readRaw(name) {
    const raw = localStorage.getItem(key(name));
    return raw ? JSON.parse(raw) : null;
  }

  function writeRaw(name, value) {
    localStorage.setItem(key(name), JSON.stringify(value));
  }

  async function fetchSeed(name) {
    const res = await fetch(`data/${FILES[name]}`, { cache: 'no-store' });
    if (!res.ok) throw new Error(`Gagal memuat seed ${name}: HTTP ${res.status}`);
    return res.json();
  }

  /** Pastikan semua koleksi sudah ada di localStorage (memuat seed sekali saja). */
  function ensureSeeded() {
    if (seeded) return Promise.resolve();
    if (seedPromise) return seedPromise;

    const names = Object.keys(FILES);
    seedPromise = Promise.all(
      names.map(async (name) => {
        if (readRaw(name) === null) {
          const data = await fetchSeed(name);
          writeRaw(name, data);
        }
      })
    ).then(() => {
      seeded = true;
    });
    return seedPromise;
  }

  function get(name) {
    return readRaw(name) || [];
  }

  function save(name, arr) {
    writeRaw(name, arr);
  }

  function findById(name, id, idField) {
    const field = idField || 'id';
    return get(name).find((row) => String(row[field]) === String(id));
  }

  function nextId(name, idField) {
    const field = idField || 'id';
    const rows = get(name);
    return rows.reduce((max, r) => Math.max(max, Number(r[field]) || 0), 0) + 1;
  }

  /** Reset seluruh data kembali ke isi file JSON asli (mirip "reset database seed"). */
  async function resetAllData() {
    Object.keys(FILES).forEach((name) => localStorage.removeItem(key(name)));
    seeded = false;
    seedPromise = null;
    await ensureSeeded();
  }

  global.IOMS = global.IOMS || {};
  global.IOMS.DataStore = { ensureSeeded, get, save, findById, nextId, resetAllData };
})(window);
