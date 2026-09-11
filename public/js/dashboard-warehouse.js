(function () {
  const { escapeHtml } = IOMS.Utils;

  document.addEventListener('DOMContentLoaded', async () => {
    const session = IOMS.Auth.requireRole(['Warehouse Staff']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    render();
  });

  function render() {
    const products = IOMS.DataStore.get('products');
    const stock = IOMS.DataStore.get('productStock');
    const salesOrders = IOMS.DataStore.get('salesOrders');
    const warehouses = IOMS.DataStore.get('warehouses');
    const warehouseById = Object.fromEntries(warehouses.map((w) => [w.id, w]));
    const customers = IOMS.DataStore.get('customers');
    const customerById = Object.fromEntries(customers.map((c) => [c.id, c]));

    const stockBySku = {};
    stock.forEach((s) => {
      stockBySku[s.sku] = (stockBySku[s.sku] || 0) + s.quantity;
    });

    const lowStock = products
      .map((p) => ({ ...p, totalStock: stockBySku[p.sku] || 0 }))
      .filter((p) => p.totalStock < p.reorderPoint)
      .sort((a, b) => a.totalStock - b.totalStock);

    const approvedOrders = salesOrders.filter((o) => o.status === 'Approved');

    document.getElementById('stat-grid').innerHTML = `
      <div class="stat-tile"><div class="value">${approvedOrders.length}</div><div class="label">SO Approved — siap goods issue</div></div>
      <div class="stat-tile ${lowStock.length ? 'warn' : 'good'}"><div class="value">${lowStock.length}</div><div class="label">Produk low-stock</div></div>`;

    const soTbody = document.querySelector('#approved-so-table tbody');
    soTbody.innerHTML = approvedOrders.length
      ? approvedOrders
          .map(
            (o, i) => `
        <tr class="row-in" style="animation-delay:${i * 0.03}s">
          <td>${escapeHtml(o.orderNo)}</td>
          <td>${escapeHtml(customerById[o.customerId]?.name || '-')}</td>
          <td>${escapeHtml(warehouseById[o.warehouseId]?.name || '-')}</td>
          <td class="actions"><a class="btn small primary" href="sales-order-detail.html?id=${o.id}">Proses</a></td>
        </tr>`
          )
          .join('')
      : `<tr><td colspan="4" class="text-muted">Tidak ada SO yang menunggu goods issue.</td></tr>`;

    const lowTbody = document.querySelector('#low-stock-table tbody');
    lowTbody.innerHTML = lowStock.length
      ? lowStock
          .slice(0, 8)
          .map(
            (p, i) => `
        <tr class="row-in" style="animation-delay:${i * 0.03}s">
          <td>${escapeHtml(p.sku)}</td>
          <td>${escapeHtml(p.name)}</td>
          <td class="num">${p.totalStock}</td>
          <td class="num">${p.reorderPoint}</td>
        </tr>`
          )
          .join('')
      : `<tr><td colspan="4" class="text-muted">Tidak ada produk di bawah reorder point.</td></tr>`;
  }
})();
