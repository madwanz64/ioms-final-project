(function () {
  const { formatCurrency, formatDate, formatDateTime, badgeHtml, escapeHtml, toast, confirmDialog } = IOMS.Utils;
  let session;
  let order;

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole(['Admin', 'Warehouse Staff']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    load();
  });

  function load() {
    const params = new URLSearchParams(window.location.search);
    const id = Number(params.get('id'));
    order = IOMS.DataStore.findById('purchaseOrders', id);

    if (!order) {
      document.querySelector('main').innerHTML = `
        <div class="empty-state">
          <div class="icon-box">❓</div>
          <h3>Purchase Order tidak ditemukan</h3>
          <p><a href="purchase-orders.html">Kembali ke daftar Purchase Order</a>.</p>
        </div>`;
      return;
    }
    render();
  }

  function render() {
    const suppliers = IOMS.DataStore.get('suppliers');
    const warehouses = IOMS.DataStore.get('warehouses');
    const supplier = suppliers.find((s) => s.id === order.supplierId);
    const warehouse = warehouses.find((w) => w.id === order.warehouseId);

    document.getElementById('po-title').textContent = order.orderNo;
    document.getElementById('po-meta').textContent = `${supplier?.name || '-'} · ${warehouse?.name || '-'} · Dibuat ${formatDate(order.createdAt)}`;
    document.getElementById('status-badge-holder').innerHTML = badgeHtml(order.status);

    renderItems();
    renderDraftPanel();
    renderReceiptPanel();
    renderCancelPanel();
    renderLedger();
  }

  function renderItems() {
    const products = IOMS.DataStore.get('products');
    document.getElementById('item-rows').innerHTML = order.items
      .map((item) => {
        const product = products.find((p) => p.sku === item.sku);
        const remaining = item.qty - item.receivedQty;
        return `
        <tr>
          <td>${escapeHtml(product ? product.name : item.sku)}</td>
          <td class="num">${item.qty}</td>
          <td class="num">${item.receivedQty}</td>
          <td class="num">${remaining}</td>
          <td class="num">${formatCurrency(item.buyPrice)}</td>
          <td class="num">${formatCurrency(item.qty * item.buyPrice)}</td>
        </tr>`;
      })
      .join('');
  }

  function renderDraftPanel() {
    const panel = document.getElementById('draft-panel');
    panel.hidden = order.status !== 'Draft';
    if (panel.hidden) return;

    document.getElementById('btn-send-order').onclick = async () => {
      const ok = await confirmDialog({ title: 'Kirim ke Supplier', message: `Kirim ${order.orderNo} ke supplier? Status akan menjadi Ordered.`, confirmLabel: 'Kirim' });
      if (!ok) return;
      updateOrder({ status: 'Ordered' });
      toast('Purchase Order dikirim ke supplier.', 'success');
      render();
    };
  }

  function renderReceiptPanel() {
    const panel = document.getElementById('receipt-panel');
    const canReceive = ['Ordered', 'PartiallyReceived'].includes(order.status);
    panel.hidden = !canReceive;
    if (!canReceive) return;

    const products = IOMS.DataStore.get('products');
    const tbody = document.querySelector('#receipt-table tbody');
    const pendingItems = order.items.filter((item) => item.qty - item.receivedQty > 0);

    tbody.innerHTML = pendingItems
      .map((item, idx) => {
        const remaining = item.qty - item.receivedQty;
        const product = products.find((p) => p.sku === item.sku);
        return `
        <tr data-sku="${item.sku}">
          <td>${escapeHtml(product ? product.name : item.sku)}</td>
          <td>${remaining}</td>
          <td><input type="number" class="f-receive-qty" min="0" max="${remaining}" value="${remaining}"></td>
        </tr>`;
      })
      .join('');

    document.getElementById('btn-receive').onclick = async () => {
      const ok = await confirmDialog({
        title: 'Konfirmasi Goods Receipt',
        message: `Tambah stok gudang berdasarkan qty yang diterima untuk ${order.orderNo}?`,
        confirmLabel: 'Proses',
      });
      if (!ok) return;
      processGoodsReceipt(tbody);
    };
  }

  function processGoodsReceipt(tbody) {
    const stockRows = IOMS.DataStore.get('productStock');
    const ledger = IOMS.DataStore.get('stockLedger');
    let nextLedgerId = ledger.reduce((max, l) => Math.max(max, l.id), 0) + 1;
    let anyProcessed = false;

    tbody.querySelectorAll('tr').forEach((tr) => {
      const sku = tr.dataset.sku;
      const qtyReceived = Number(tr.querySelector('.f-receive-qty').value) || 0;
      if (qtyReceived <= 0) return;

      const item = order.items.find((i) => i.sku === sku);
      const remaining = item.qty - item.receivedQty;
      const clamped = Math.min(qtyReceived, remaining);
      if (clamped <= 0) return;

      item.receivedQty += clamped;
      anyProcessed = true;

      let stockRow = stockRows.find((s) => s.sku === sku && s.warehouseId === order.warehouseId);
      if (!stockRow) {
        stockRow = { sku, warehouseId: order.warehouseId, quantity: 0, updatedAt: '' };
        stockRows.push(stockRow);
      }
      stockRow.quantity += clamped;
      stockRow.updatedAt = new Date().toISOString();

      ledger.push({
        id: nextLedgerId++,
        sku,
        warehouseId: order.warehouseId,
        type: 'Receipt',
        quantity: clamped,
        refType: 'PO',
        refId: order.orderNo,
        byUserId: session.userId,
        timestamp: new Date().toISOString(),
      });
    });

    if (!anyProcessed) {
      toast('Tidak ada qty yang diproses. Isi minimal satu item dengan qty > 0.', 'error');
      return;
    }

    IOMS.DataStore.save('productStock', stockRows);
    IOMS.DataStore.save('stockLedger', ledger);

    const fullyReceived = order.items.every((i) => i.receivedQty >= i.qty);
    updateOrder({ status: fullyReceived ? 'Received' : 'PartiallyReceived', items: order.items });

    toast(fullyReceived ? 'Semua item diterima. PO Received.' : 'Sebagian item diterima. PO PartiallyReceived.', 'success');
    render();
  }

  function renderCancelPanel() {
    const panel = document.getElementById('cancel-panel');
    const cancellable = ['Draft', 'Ordered', 'PartiallyReceived'].includes(order.status);
    panel.hidden = !cancellable;
    if (!cancellable) return;

    document.getElementById('btn-cancel').onclick = async () => {
      const ok = await confirmDialog({
        title: 'Batalkan Purchase Order',
        message: `Batalkan ${order.orderNo}? Item yang sudah diterima sebagian tetap tercatat di stok.`,
        confirmLabel: 'Batalkan',
        danger: true,
      });
      if (!ok) return;
      updateOrder({ status: 'Cancelled' });
      toast('Purchase Order dibatalkan.', 'success');
      render();
    };
  }

  function renderLedger() {
    const warehouses = IOMS.DataStore.get('warehouses');
    const warehouseById = Object.fromEntries(warehouses.map((w) => [w.id, w]));
    const products = IOMS.DataStore.get('products');
    const productBySku = Object.fromEntries(products.map((p) => [p.sku, p]));

    const ledger = IOMS.DataStore.get('stockLedger')
      .filter((l) => l.refType === 'PO' && l.refId === order.orderNo)
      .sort((a, b) => (a.timestamp < b.timestamp ? 1 : -1));

    document.getElementById('ledger-rows').innerHTML = ledger.length
      ? ledger
          .map(
            (l) => `
        <tr>
          <td>${formatDateTime(l.timestamp)}</td>
          <td>${escapeHtml(productBySku[l.sku]?.name || l.sku)}</td>
          <td class="num">+${l.quantity}</td>
          <td>${escapeHtml(warehouseById[order.warehouseId]?.name || '-')}</td>
        </tr>`
          )
          .join('')
      : `<tr><td colspan="4" class="text-muted">Belum ada penerimaan barang tercatat.</td></tr>`;
  }

  function updateOrder(patch) {
    const orders = IOMS.DataStore.get('purchaseOrders');
    const idx = orders.findIndex((o) => o.id === order.id);
    orders[idx] = { ...orders[idx], ...patch };
    IOMS.DataStore.save('purchaseOrders', orders);
    order = orders[idx];
  }
})();
