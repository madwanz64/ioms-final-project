(function () {
  const { formatCurrency, badgeHtml, escapeHtml } = IOMS.Utils;

  document.addEventListener('DOMContentLoaded', async () => {
    const session = IOMS.Auth.requireRole(['Admin']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    render();
  });

  function render() {
    const products = IOMS.DataStore.get('products');
    const stock = IOMS.DataStore.get('productStock');
    const salesOrders = IOMS.DataStore.get('salesOrders');

    const stockBySku = {};
    stock.forEach((s) => {
      stockBySku[s.sku] = (stockBySku[s.sku] || 0) + s.quantity;
    });

    let inventoryValue = 0;
    const lowStock = [];
    products.forEach((p) => {
      const totalStock = stockBySku[p.sku] || 0;
      inventoryValue += totalStock * p.buyPrice;
      if (totalStock < p.reorderPoint) {
        lowStock.push({ ...p, totalStock });
      }
    });
    lowStock.sort((a, b) => a.totalStock - b.totalStock);

    const statusCount = {};
    salesOrders.forEach((o) => {
      statusCount[o.status] = (statusCount[o.status] || 0) + 1;
    });

    renderStats(inventoryValue, lowStock.length, statusCount);
    renderLowStock(lowStock);
    renderStatusTable(statusCount);
  }

  function renderStats(inventoryValue, lowStockCount, statusCount) {
    const grid = document.getElementById('stat-grid');
    const pendingCount = statusCount['PendingApproval'] || 0;
    const approvedCount = statusCount['Approved'] || 0;

    grid.innerHTML = `
      <div class="stat-tile">
        <div class="value">${formatCurrency(inventoryValue)}</div>
        <div class="label">Total nilai inventori (harga beli)</div>
      </div>
      <div class="stat-tile ${lowStockCount > 0 ? 'warn' : 'good'}">
        <div class="value">${lowStockCount}</div>
        <div class="label">Produk di bawah reorder point</div>
      </div>
      <div class="stat-tile">
        <div class="value">${pendingCount}</div>
        <div class="label">Sales Order — Pending Approval</div>
      </div>
      <div class="stat-tile">
        <div class="value">${approvedCount}</div>
        <div class="label">Sales Order — Approved (siap issue)</div>
      </div>`;
  }

  function renderLowStock(lowStock) {
    const tbody = document.querySelector('#low-stock-table tbody');
    if (lowStock.length === 0) {
      tbody.innerHTML = `<tr><td colspan="4" class="text-muted">Tidak ada produk di bawah reorder point. 🎉</td></tr>`;
      return;
    }
    tbody.innerHTML = lowStock
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
      .join('');
  }

  function renderStatusTable(statusCount) {
    const order = ['Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled'];
    const tbody = document.querySelector('#so-status-table tbody');
    tbody.innerHTML = order
      .filter((s) => statusCount[s])
      .map(
        (s, i) => `
      <tr class="row-in" style="animation-delay:${i * 0.03}s">
        <td>${badgeHtml(s)}</td>
        <td class="num">${statusCount[s]}</td>
      </tr>`
      )
      .join('');
  }
})();
