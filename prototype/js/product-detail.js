(function () {
  const { formatCurrency, formatDateTime, badgeHtml, escapeHtml } = IOMS.Utils;
  let session;

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole('any');
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    render();
  });

  function render() {
    const params = new URLSearchParams(window.location.search);
    const sku = params.get('sku');
    const product = sku && IOMS.DataStore.findById('products', sku, 'sku');

    if (!product) {
      window.location.href = 'error-404.html';
      return;
    }

    const categories = IOMS.DataStore.get('categories');
    const category = categories.find((c) => c.id === product.categoryId);
    const stock = IOMS.DataStore.get('productStock').filter((s) => s.sku === sku);
    const warehouses = IOMS.DataStore.get('warehouses');
    const warehouseById = Object.fromEntries(warehouses.map((w) => [w.id, w]));
    const ledger = IOMS.DataStore.get('stockLedger')
      .filter((l) => l.sku === sku)
      .sort((a, b) => (a.timestamp < b.timestamp ? 1 : -1));

    document.getElementById('product-name').textContent = product.name;
    document.getElementById('product-meta').textContent = `${product.sku} · ${category ? category.name : '-'}`;

    const isAdmin = session.role === 'Admin';
    document.getElementById('edit-link').hidden = !isAdmin;
    document.getElementById('edit-link').href = `product-form.html?sku=${product.sku}`;

    if (product.imageUrl) {
      const img = document.getElementById('product-image');
      img.src = product.imageUrl;
      img.hidden = false;
      document.getElementById('no-image-text').hidden = true;
    }

    const showBuyPrice = session.role !== 'Sales';
    document.getElementById('info-table').querySelector('tbody').innerHTML = `
      ${showBuyPrice ? `<tr><td>Harga Beli</td><td class="num">${formatCurrency(product.buyPrice)}</td></tr>` : ''}
      <tr><td>Harga Jual</td><td class="num">${formatCurrency(product.sellPrice)}</td></tr>
      <tr><td>Reorder Point</td><td class="num">${product.reorderPoint}</td></tr>
      <tr><td>Status</td><td>${product.active ? '<span class="badge active">Aktif</span>' : '<span class="badge inactive">Nonaktif</span>'}</td></tr>`;

    const totalStock = stock.reduce((sum, s) => sum + s.quantity, 0);
    document.getElementById('stock-heading').innerHTML = `Stok Total: <strong>${totalStock} ${escapeHtml(
      product.unit
    )}</strong> ${totalStock < product.reorderPoint ? '<span class="badge lowstock" style="margin-left:6px;">Low Stock</span>' : ''}`;

    document.getElementById('stock-rows').innerHTML = stock
      .map(
        (s) => `<tr><td>${escapeHtml(warehouseById[s.warehouseId]?.name || '-')}</td><td class="num">${s.quantity}</td></tr>`
      )
      .join('');

    document.getElementById('ledger-rows').innerHTML = ledger.length
      ? ledger
          .slice(0, 10)
          .map(
            (l) => `
        <tr>
          <td>${formatDateTime(l.timestamp)}</td>
          <td>${escapeHtml(warehouseById[l.warehouseId]?.name || '-')}</td>
          <td>${badgeHtml(l.type)}</td>
          <td class="num">${l.quantity > 0 ? '+' : ''}${l.quantity}</td>
          <td>${escapeHtml(l.refId)}</td>
        </tr>`
          )
          .join('')
      : `<tr><td colspan="5" class="text-muted">Belum ada pergerakan stok tercatat.</td></tr>`;

    if (isAdmin) bindApiDemo(product.sku);
  }

  function bindApiDemo(sku) {
    const panel = document.getElementById('api-demo-panel');
    panel.hidden = false;
    const resultBox = document.getElementById('api-result');

    async function callApi(url) {
      resultBox.style.display = 'block';
      resultBox.textContent = 'Memanggil ' + url + ' ...';
      try {
        const res = await fetch(url);
        const body = await res.json();
        resultBox.textContent = `HTTP ${res.status}\nContent-Type: ${res.headers.get('content-type')}\n\n${JSON.stringify(body, null, 2)}`;
      } catch (err) {
        resultBox.textContent = 'Gagal memanggil endpoint: ' + err.message;
      }
    }

    const token = IOMS.Auth.getSession()?.userId;
    document.getElementById('api-call-ok').addEventListener('click', () => callApi(`/api/products/${sku}/availability?token=${token}`));
    document.getElementById('api-call-nouth').addEventListener('click', () => callApi(`/api/products/${sku}/availability`));
    document.getElementById('api-call-404').addEventListener('click', () => callApi(`/api/products/SKU-TIDAK-ADA/availability?token=${token}`));
  }
})();
