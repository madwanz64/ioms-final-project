(function () {
  const { escapeHtml } = IOMS.Utils;

  document.addEventListener('DOMContentLoaded', async () => {
    const session = IOMS.Auth.requireRole(['Admin']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    render();
  });

  function render() {
    const warehouses = IOMS.DataStore.get('warehouses');
    const stock = IOMS.DataStore.get('productStock');
    const tbody = document.getElementById('warehouse-rows');

    if (warehouses.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-muted">Belum ada gudang.</td></tr>`;
      return;
    }

    tbody.innerHTML = warehouses
      .map((w, i) => {
        const skuCount = stock.filter((s) => s.warehouseId === w.id && s.quantity > 0).length;
        return `
        <tr class="row-in" style="animation-delay:${i * 0.03}s">
          <td>${escapeHtml(w.name)}</td>
          <td>${escapeHtml(w.location)}</td>
          <td class="num">${skuCount}</td>
          <td>${w.active ? '<span class="badge active">Aktif</span>' : '<span class="badge inactive">Nonaktif</span>'}</td>
          <td class="actions"><a class="btn small" href="warehouse-form.html?id=${w.id}">Edit</a></td>
        </tr>`;
      })
      .join('');
  }
})();
