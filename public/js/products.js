(function () {
  const { formatCurrency, escapeHtml, skeletonRows } = IOMS.Utils;
  const PAGE_SIZE = 10;

  let session;
  let state = { search: '', categoryId: '', stockFilter: '', sort: 'name-asc', page: 1 };

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole('any');
    if (!session) return;

    document.querySelector('#product-rows').innerHTML = skeletonRows(7, 6);

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    applyRoleView();
    populateCategoryFilter();
    bindToolbar();
    render();
  });

  function applyRoleView() {
    const isAdmin = session.role === 'Admin';
    document.getElementById('btn-add-product').hidden = !isAdmin;
    document.getElementById('page-subtitle').textContent = isAdmin
      ? 'PRD-01, FIND-01 — akses penuh (Admin)'
      : session.role === 'Sales'
      ? 'Katalog produk (read-only) — harga jual saja yang ditampilkan'
      : 'Produk & stok (read-only) — untuk kebutuhan goods issue/receipt';
  }

  function populateCategoryFilter() {
    const categories = IOMS.DataStore.get('categories');
    const select = document.getElementById('f-category');
    categories.forEach((c) => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.name;
      select.appendChild(opt);
    });
  }

  function bindToolbar() {
    document.getElementById('f-search').addEventListener('input', (e) => {
      state.search = e.target.value.trim().toLowerCase();
      state.page = 1;
      render();
    });
    document.getElementById('f-category').addEventListener('change', (e) => {
      state.categoryId = e.target.value;
      state.page = 1;
      render();
    });
    document.getElementById('f-stock').addEventListener('change', (e) => {
      state.stockFilter = e.target.value;
      state.page = 1;
      render();
    });
    document.getElementById('f-sort').addEventListener('change', (e) => {
      state.sort = e.target.value;
      render();
    });
  }

  function getRowsWithStock() {
    const products = IOMS.DataStore.get('products');
    const stock = IOMS.DataStore.get('productStock');
    const categories = IOMS.DataStore.get('categories');
    const categoryById = Object.fromEntries(categories.map((c) => [c.id, c]));

    const stockBySku = {};
    stock.forEach((s) => {
      stockBySku[s.sku] = (stockBySku[s.sku] || 0) + s.quantity;
    });

    return products.map((p) => ({
      ...p,
      categoryName: categoryById[p.categoryId]?.name || '-',
      totalStock: stockBySku[p.sku] || 0,
    }));
  }

  function render() {
    let rows = getRowsWithStock();

    if (state.search) {
      rows = rows.filter(
        (p) => p.name.toLowerCase().includes(state.search) || p.sku.toLowerCase().includes(state.search)
      );
    }
    if (state.categoryId) {
      rows = rows.filter((p) => String(p.categoryId) === state.categoryId);
    }
    if (state.stockFilter === 'low') {
      rows = rows.filter((p) => p.totalStock < p.reorderPoint);
    } else if (state.stockFilter === 'normal') {
      rows = rows.filter((p) => p.totalStock >= p.reorderPoint);
    }

    rows.sort((a, b) => {
      if (state.sort === 'name-asc') return a.name.localeCompare(b.name);
      if (state.sort === 'name-desc') return b.name.localeCompare(a.name);
      if (state.sort === 'stock-asc') return a.totalStock - b.totalStock;
      return 0;
    });

    const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    state.page = Math.min(state.page, totalPages);
    const pageRows = rows.slice((state.page - 1) * PAGE_SIZE, state.page * PAGE_SIZE);

    renderRows(pageRows);
    renderPagination(totalPages);
  }

  function renderRows(rows) {
    const tbody = document.getElementById('product-rows');
    const isAdmin = session.role === 'Admin';
    const showPrice = session.role !== 'Warehouse Staff';

    if (rows.length === 0) {
      tbody.innerHTML = `
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="icon-box">🔍</div>
            <h3>Tidak ada produk yang cocok</h3>
            <p>Coba ubah kata kunci pencarian atau filter yang digunakan.</p>
          </div>
        </td></tr>`;
      return;
    }

    tbody.innerHTML = rows
      .map((p, i) => {
        const lowStock = p.totalStock < p.reorderPoint;
        const statusBadge = !p.active
          ? '<span class="badge inactive">Nonaktif</span>'
          : lowStock
          ? '<span class="badge lowstock">Low Stock</span>'
          : '<span class="badge active">Normal</span>';
        const action = isAdmin
          ? `<a class="btn small" href="product-detail.html?sku=${p.sku}">Lihat</a> <a class="btn small" href="product-form.html?sku=${p.sku}">Edit</a>`
          : `<a class="btn small" href="product-detail.html?sku=${p.sku}">Lihat</a>`;
        return `
        <tr class="row-in" style="animation-delay:${Math.min(i, 8) * 0.03}s">
          <td>${escapeHtml(p.sku)}</td>
          <td>${escapeHtml(p.name)}</td>
          <td>${escapeHtml(p.categoryName)}</td>
          <td class="num">${showPrice ? formatCurrency(p.sellPrice) : '-'}</td>
          <td class="num">${p.totalStock}</td>
          <td>${statusBadge}</td>
          <td class="actions">${action}</td>
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
