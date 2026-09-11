(function () {
  const { formatDate, badgeHtml, escapeHtml, skeletonRows } = IOMS.Utils;
  const PAGE_SIZE = 10;

  let state = { search: '', status: '', sort: 'date-desc', page: 1 };

  document.addEventListener('DOMContentLoaded', async () => {
    const session = IOMS.Auth.requireRole(['Admin', 'Warehouse Staff']);
    if (!session) return;

    document.getElementById('po-rows').innerHTML = skeletonRows(6, 6);

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    bindToolbar();
    render();
  });

  function bindToolbar() {
    document.getElementById('f-search').addEventListener('input', (e) => {
      state.search = e.target.value.trim().toLowerCase();
      state.page = 1;
      render();
    });
    document.getElementById('f-status').addEventListener('change', (e) => {
      state.status = e.target.value;
      state.page = 1;
      render();
    });
    document.getElementById('f-sort').addEventListener('change', (e) => {
      state.sort = e.target.value;
      render();
    });
  }

  function render() {
    const suppliers = IOMS.DataStore.get('suppliers');
    const warehouses = IOMS.DataStore.get('warehouses');
    const supplierById = Object.fromEntries(suppliers.map((s) => [s.id, s]));
    const warehouseById = Object.fromEntries(warehouses.map((w) => [w.id, w]));

    let rows = IOMS.DataStore.get('purchaseOrders');

    if (state.search) {
      rows = rows.filter((o) => {
        const supplierName = supplierById[o.supplierId]?.name.toLowerCase() || '';
        return o.orderNo.toLowerCase().includes(state.search) || supplierName.includes(state.search);
      });
    }
    if (state.status) {
      rows = rows.filter((o) => o.status === state.status);
    }
    rows.sort((a, b) => (state.sort === 'date-desc' ? (a.createdAt < b.createdAt ? 1 : -1) : a.createdAt > b.createdAt ? 1 : -1));

    const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    state.page = Math.min(state.page, totalPages);
    const pageRows = rows.slice((state.page - 1) * PAGE_SIZE, state.page * PAGE_SIZE);

    renderRows(pageRows, supplierById, warehouseById);
    renderPagination(totalPages);
  }

  function renderRows(rows, supplierById, warehouseById) {
    const tbody = document.getElementById('po-rows');
    if (rows.length === 0) {
      tbody.innerHTML = `
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="icon-box">📦</div>
            <h3>Belum ada Purchase Order</h3>
            <p>Tidak ada order yang cocok dengan filter, atau belum pernah dibuat.</p>
          </div>
        </td></tr>`;
      return;
    }
    tbody.innerHTML = rows
      .map(
        (o, i) => `
      <tr class="row-in" style="animation-delay:${Math.min(i, 8) * 0.03}s">
        <td>${escapeHtml(o.orderNo)}</td>
        <td>${escapeHtml(supplierById[o.supplierId]?.name || '-')}</td>
        <td>${escapeHtml(warehouseById[o.warehouseId]?.name || '-')}</td>
        <td>${formatDate(o.createdAt)}</td>
        <td>${badgeHtml(o.status)}</td>
        <td class="actions"><a class="btn small" href="purchase-order-detail.html?id=${o.id}">Lihat</a></td>
      </tr>`
      )
      .join('');
  }

  function renderPagination(totalPages) {
    const el = document.getElementById('pagination');
    if (totalPages <= 1) {
      el.innerHTML = '';
      return;
    }
    let html = `<button ${state.page === 1 ? 'disabled' : ''} data-page="${state.page - 1}">&laquo; Prev</button>`;
    for (let p = 1; p <= totalPages; p++) {
      html += `<button class="${p === state.page ? 'active' : ''}" data-page="${p}">${p}</button>`;
    }
    html += `<button ${state.page === totalPages ? 'disabled' : ''} data-page="${state.page + 1}">Next &raquo;</button>`;
    el.innerHTML = html;
    el.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.page = Number(btn.dataset.page);
        render();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  }
})();
