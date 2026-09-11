(function () {
  const { formatDateTime, formatDate, badgeHtml, escapeHtml, downloadCsv } = IOMS.Utils;
  let session;

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole(['Admin', 'Warehouse Staff']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    applyRoleView();
    setupDefaultDateRange();
    populateWarehouseFilter();
    bindEvents();
    render();
  });

  function applyRoleView() {
    document.getElementById('order-report-panel').hidden = session.role !== 'Admin';
    document.getElementById('page-subtitle').textContent =
      session.role === 'Admin'
        ? 'REPORT-01 — laporan stok & status order, dihasilkan dari data yang sama dengan dashboard'
        : 'REPORT-01 — laporan pergerakan stok';
  }

  function setupDefaultDateRange() {
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - 30);
    document.getElementById('f-start').value = start.toISOString().slice(0, 10);
    document.getElementById('f-end').value = end.toISOString().slice(0, 10);
  }

  function populateWarehouseFilter() {
    const select = document.getElementById('f-warehouse');
    IOMS.DataStore.get('warehouses').forEach((w) => select.appendChild(new Option(w.name, w.id)));
  }

  function bindEvents() {
    ['f-start', 'f-end', 'f-warehouse', 'f-type'].forEach((id) => {
      document.getElementById(id).addEventListener('change', render);
    });
    document.getElementById('btn-export-ledger').addEventListener('click', exportLedgerCsv);
    document.getElementById('btn-export-orders')?.addEventListener('click', exportOrdersCsv);
  }

  function dateInRange(dateStr) {
    const start = document.getElementById('f-start').value;
    const end = document.getElementById('f-end').value;
    const d = dateStr.slice(0, 10);
    return (!start || d >= start) && (!end || d <= end);
  }

  function getFilteredLedger() {
    const warehouseFilter = document.getElementById('f-warehouse').value;
    const typeFilter = document.getElementById('f-type').value;

    return IOMS.DataStore.get('stockLedger')
      .filter((l) => dateInRange(l.timestamp))
      .filter((l) => !warehouseFilter || String(l.warehouseId) === warehouseFilter)
      .filter((l) => !typeFilter || l.type === typeFilter)
      .sort((a, b) => (a.timestamp < b.timestamp ? 1 : -1));
  }

  function render() {
    renderLedgerTable();
    if (session.role === 'Admin') renderOrderTable();
  }

  function renderLedgerTable() {
    const products = IOMS.DataStore.get('products');
    const productBySku = Object.fromEntries(products.map((p) => [p.sku, p]));
    const warehouses = IOMS.DataStore.get('warehouses');
    const warehouseById = Object.fromEntries(warehouses.map((w) => [w.id, w]));
    const users = IOMS.DataStore.get('users');
    const userById = Object.fromEntries(users.map((u) => [u.id, u]));

    const rows = getFilteredLedger();
    const tbody = document.getElementById('ledger-rows');

    tbody.innerHTML = rows.length
      ? rows
          .map(
            (l) => `
        <tr>
          <td>${formatDateTime(l.timestamp)}</td>
          <td>${escapeHtml(productBySku[l.sku]?.name || l.sku)}</td>
          <td>${escapeHtml(warehouseById[l.warehouseId]?.name || '-')}</td>
          <td>${badgeHtml(l.type)}</td>
          <td class="num">${l.quantity > 0 ? '+' : ''}${l.quantity}</td>
          <td>${escapeHtml(l.refId)}</td>
          <td>${escapeHtml(userById[l.byUserId]?.name || '-')}</td>
        </tr>`
          )
          .join('')
      : `<tr><td colspan="7" class="text-muted">Tidak ada pergerakan stok pada rentang tanggal/filter ini.</td></tr>`;
  }

  function renderOrderTable() {
    const customers = IOMS.DataStore.get('customers');
    const customerById = Object.fromEntries(customers.map((c) => [c.id, c]));
    const suppliers = IOMS.DataStore.get('suppliers');
    const supplierById = Object.fromEntries(suppliers.map((s) => [s.id, s]));

    const salesOrders = IOMS.DataStore.get('salesOrders').filter((o) => dateInRange(o.createdAt));
    const purchaseOrders = IOMS.DataStore.get('purchaseOrders').filter((o) => dateInRange(o.createdAt));

    const rows = [
      ...salesOrders.map((o) => ['SO', o.orderNo, customerById[o.customerId]?.name || '-', o.status, o.createdAt]),
      ...purchaseOrders.map((o) => ['PO', o.orderNo, supplierById[o.supplierId]?.name || '-', o.status, o.createdAt]),
    ].sort((a, b) => (a[4] < b[4] ? 1 : -1));

    document.getElementById('order-rows').innerHTML = rows.length
      ? rows
          .map(
            ([type, no, party, status, date]) => `
        <tr>
          <td>${type}</td>
          <td>${escapeHtml(no)}</td>
          <td>${escapeHtml(party)}</td>
          <td>${badgeHtml(status)}</td>
          <td>${formatDate(date)}</td>
        </tr>`
          )
          .join('')
      : `<tr><td colspan="5" class="text-muted">Tidak ada order pada rentang tanggal ini.</td></tr>`;
  }

  function exportLedgerCsv() {
    const products = IOMS.DataStore.get('products');
    const productBySku = Object.fromEntries(products.map((p) => [p.sku, p]));
    const warehouses = IOMS.DataStore.get('warehouses');
    const warehouseById = Object.fromEntries(warehouses.map((w) => [w.id, w]));
    const users = IOMS.DataStore.get('users');
    const userById = Object.fromEntries(users.map((u) => [u.id, u]));

    const rows = getFilteredLedger().map((l) => [
      formatDateTime(l.timestamp),
      l.sku,
      productBySku[l.sku]?.name || '',
      warehouseById[l.warehouseId]?.name || '',
      l.type,
      l.quantity,
      l.refId,
      userById[l.byUserId]?.name || '',
    ]);

    const start = document.getElementById('f-start').value;
    const end = document.getElementById('f-end').value;
    downloadCsv(
      `stock-ledger_${start}_${end}.csv`,
      ['Tanggal', 'SKU', 'Produk', 'Gudang', 'Tipe', 'Qty', 'Referensi', 'Oleh'],
      rows
    );
  }

  function exportOrdersCsv() {
    const customers = IOMS.DataStore.get('customers');
    const customerById = Object.fromEntries(customers.map((c) => [c.id, c]));
    const suppliers = IOMS.DataStore.get('suppliers');
    const supplierById = Object.fromEntries(suppliers.map((s) => [s.id, s]));

    const salesOrders = IOMS.DataStore.get('salesOrders').filter((o) => dateInRange(o.createdAt));
    const purchaseOrders = IOMS.DataStore.get('purchaseOrders').filter((o) => dateInRange(o.createdAt));

    const rows = [
      ...salesOrders.map((o) => ['SO', o.orderNo, customerById[o.customerId]?.name || '', o.status, o.createdAt]),
      ...purchaseOrders.map((o) => ['PO', o.orderNo, supplierById[o.supplierId]?.name || '', o.status, o.createdAt]),
    ];

    const start = document.getElementById('f-start').value;
    const end = document.getElementById('f-end').value;
    downloadCsv(`order-status_${start}_${end}.csv`, ['Jenis', 'Nomor', 'Pihak Terkait', 'Status', 'Tanggal'], rows);
  }
})();
