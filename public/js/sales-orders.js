(function () {
  const { formatDate, badgeHtml, escapeHtml, skeletonRows } = IOMS.Utils;
  const PAGE_SIZE = 10;

  let session;
  let state = { search: '', status: '', sort: 'date-desc', page: 1 };

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole('any');
    if (!session) return;

    document.getElementById('so-rows').innerHTML = skeletonRows(6, 6);

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    applyRoleView();
    bindToolbar();
    render();
  });

  function applyRoleView() {
    document.getElementById('btn-add-so').hidden = session.role !== 'Sales';
    document.getElementById('page-subtitle').textContent =
      session.role === 'Admin'
        ? 'SO-01, FIND-01 — seluruh Sales Order'
        : session.role === 'Sales'
        ? 'Sales Order milik Anda sendiri'
        : 'Seluruh Sales Order — tindakan goods issue hanya untuk status Approved';
  }

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

  function getScopedOrders() {
    const orders = IOMS.DataStore.get('salesOrders');
    if (session.role === 'Sales') {
      return orders.filter((o) => o.createdBy === session.userId);
    }
    return orders;
  }

  function render() {
    const customers = IOMS.DataStore.get('customers');
    const users = IOMS.DataStore.get('users');
    const customerById = Object.fromEntries(customers.map((c) => [c.id, c]));
    const userById = Object.fromEntries(users.map((u) => [u.id, u]));

    let rows = getScopedOrders();

    if (state.search) {
      rows = rows.filter((o) => {
        const customerName = customerById[o.customerId]?.name.toLowerCase() || '';
        return o.orderNo.toLowerCase().includes(state.search) || customerName.includes(state.search);
      });
    }
    if (state.status) {
      rows = rows.filter((o) => o.status === state.status);
    }
    rows.sort((a, b) => (state.sort === 'date-desc' ? (a.createdAt < b.createdAt ? 1 : -1) : a.createdAt > b.createdAt ? 1 : -1));

    const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    state.page = Math.min(state.page, totalPages);
    const pageRows = rows.slice((state.page - 1) * PAGE_SIZE, state.page * PAGE_SIZE);

    renderRows(pageRows, customerById, userById);
    renderPagination(totalPages);
  }

  function renderRows(rows, customerById, userById) {
    const tbody = document.getElementById('so-rows');
    if (rows.length === 0) {
      tbody.innerHTML = `
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="icon-box">🧾</div>
            <h3>Belum ada Sales Order</h3>
            <p>${session.role === 'Sales' ? 'Buat Sales Order pertama Anda.' : 'Tidak ada order yang cocok dengan filter.'}</p>
          </div>
        </td></tr>`;
      return;
    }
    tbody.innerHTML = rows
      .map((o, i) => {
        const canGoodsIssue = session.role === 'Warehouse Staff' && o.status === 'Approved';
        return `
        <tr class="row-in" style="animation-delay:${Math.min(i, 8) * 0.03}s">
          <td>${escapeHtml(o.orderNo)}</td>
          <td>${escapeHtml(customerById[o.customerId]?.name || '-')}</td>
          <td>${escapeHtml(userById[o.createdBy]?.name || '-')}</td>
          <td>${formatDate(o.createdAt)}</td>
          <td>${badgeHtml(o.status)}</td>
          <td class="actions">
            <a class="btn small ${canGoodsIssue ? 'primary' : ''}" href="sales-order-detail.html?id=${o.id}">
              ${canGoodsIssue ? 'Proses' : 'Lihat'}
            </a>
          </td>
        </tr>`;
      })
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
