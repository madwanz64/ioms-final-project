/**
 * Nav — merender sidebar & topbar berdasarkan role yang sedang login.
 * Item navigasi untuk halaman yang belum dibangun (Kategori, Gudang, PO, dst)
 * sengaja belum dimasukkan dulu — menyusul di slice pengembangan berikutnya.
 */
(function (global) {
  const ICONS = {
    dashboard: '<svg viewBox="0 0 20 20" width="18" height="18" fill="none"><rect x="2.5" y="2.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="11.5" y="2.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="2.5" y="11.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="11.5" y="11.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/></svg>',
    box: '<svg viewBox="0 0 20 20" width="18" height="18" fill="none"><path d="M10 2.5 17 6.25v7.5L10 17.5 3 13.75v-7.5L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3 6.25 10 10l7-3.75M10 10v7.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
    cart: '<svg viewBox="0 0 20 20" width="18" height="18" fill="none"><path d="M2.5 3h1.8l1.4 9.4a1.8 1.8 0 0 0 1.8 1.5h6.7a1.8 1.8 0 0 0 1.77-1.47l1.03-5.63H5.1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8.2" cy="17" r="1.1" fill="currentColor"/><circle cx="14.7" cy="17" r="1.1" fill="currentColor"/></svg>',
    logout: '<svg viewBox="0 0 20 20" width="18" height="18" fill="none"><path d="M7.5 17.5h-3a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1h3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M13 13.5 17 10l-4-3.5M17 10H7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  };

  const NAV_ITEMS = {
    Admin: [
      { href: 'dashboard-admin.html', label: 'Dashboard', icon: 'dashboard' },
      { href: 'products.html', label: 'Produk', icon: 'box' },
      { href: 'sales-orders.html', label: 'Sales Order', icon: 'cart' },
    ],
    Sales: [
      { href: 'dashboard-sales.html', label: 'Dashboard', icon: 'dashboard' },
      { href: 'products.html', label: 'Katalog Produk', icon: 'box' },
      { href: 'sales-orders.html', label: 'Sales Order Saya', icon: 'cart' },
    ],
    'Warehouse Staff': [
      { href: 'dashboard-warehouse.html', label: 'Dashboard', icon: 'dashboard' },
      { href: 'products.html', label: 'Produk & Stok', icon: 'box' },
      { href: 'sales-orders.html', label: 'Sales Order', icon: 'cart' },
    ],
  };

  function initial(name) {
    return (name || '?').trim().charAt(0).toUpperCase();
  }

  function render(session) {
    const items = NAV_ITEMS[session.role] || [];
    const current = window.location.pathname.split('/').pop() || 'dashboard-admin.html';

    const sidebar = document.querySelector('[data-sidebar]');
    if (sidebar) {
      const navLinks = items
        .map(
          (item) => `
        <a href="${item.href}" class="${item.href === current ? 'active' : ''}">
          <span class="nav-icon">${ICONS[item.icon]}</span>${item.label}
        </a>`
        )
        .join('');

      sidebar.innerHTML = `
        <div class="brand"><span class="logo-dot"></span> IOMS</div>
        <span class="role-tag">${session.role}</span>
        <nav>
          ${navLinks}
          <a href="#" class="logout-link" data-logout>
            <span class="nav-icon">${ICONS.logout}</span>Logout
          </a>
        </nav>`;

      sidebar.querySelector('[data-logout]').addEventListener('click', (e) => {
        e.preventDefault();
        IOMS.Auth.logout();
        window.location.href = 'login.html';
      });
    }

    document.querySelectorAll('[data-user-name]').forEach((el) => (el.textContent = session.name));
    document.querySelectorAll('[data-user-role]').forEach((el) => (el.textContent = session.role));
    document.querySelectorAll('[data-user-avatar]').forEach((el) => (el.textContent = initial(session.name)));
  }

  global.IOMS = global.IOMS || {};
  global.IOMS.Nav = { render };
})(window);
