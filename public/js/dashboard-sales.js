(function () {
  const { formatDate, badgeHtml, escapeHtml } = IOMS.Utils;
  let session;

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole(['Sales']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    render();
  });

  function render() {
    const allOrders = IOMS.DataStore.get('salesOrders').filter((o) => o.createdBy === session.userId);
    const customers = IOMS.DataStore.get('customers');
    const customerById = Object.fromEntries(customers.map((c) => [c.id, c]));

    const statusCount = { Draft: 0, PendingApproval: 0, Approved: 0, Fulfilled: 0 };
    allOrders.forEach((o) => {
      if (statusCount[o.status] !== undefined) statusCount[o.status] += 1;
    });

    document.getElementById('stat-grid').innerHTML = `
      <div class="stat-tile"><div class="value">${statusCount.Draft}</div><div class="label">Draft</div></div>
      <div class="stat-tile warn"><div class="value">${statusCount.PendingApproval}</div><div class="label">Pending Approval</div></div>
      <div class="stat-tile"><div class="value">${statusCount.Approved}</div><div class="label">Approved (menunggu goods issue)</div></div>
      <div class="stat-tile good"><div class="value">${statusCount.Fulfilled}</div><div class="label">Fulfilled</div></div>`;

    const sorted = [...allOrders].sort((a, b) => (a.createdAt < b.createdAt ? 1 : -1)).slice(0, 6);
    const tbody = document.querySelector('#my-so-table tbody');
    if (sorted.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-muted">Anda belum membuat Sales Order.</td></tr>`;
      return;
    }
    tbody.innerHTML = sorted
      .map((o, i) => {
        const customer = customerById[o.customerId];
        const actionHref = o.status === 'Draft' ? 'sales-order-form.html?id=' + o.id : 'sales-order-detail.html?id=' + o.id;
        const actionLabel = o.status === 'Draft' ? 'Lanjutkan' : 'Lihat';
        return `
        <tr class="row-in" style="animation-delay:${i * 0.03}s">
          <td>${escapeHtml(o.orderNo)}</td>
          <td>${escapeHtml(customer ? customer.name : '-')}</td>
          <td>${formatDate(o.createdAt)}</td>
          <td>${badgeHtml(o.status)}</td>
          <td class="actions"><a class="btn small" href="${actionHref}">${actionLabel}</a></td>
        </tr>`;
      })
      .join('');
  }
})();
