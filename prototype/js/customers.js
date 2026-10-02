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
    const customers = IOMS.DataStore.get('customers');
    const tbody = document.getElementById('customer-rows');

    if (customers.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-muted">Belum ada customer.</td></tr>`;
      return;
    }

    tbody.innerHTML = customers
      .map(
        (c, i) => `
      <tr class="row-in" style="animation-delay:${i * 0.03}s">
        <td>${escapeHtml(c.name)}</td>
        <td>${escapeHtml(c.contact)}</td>
        <td>${escapeHtml(c.address)}</td>
        <td>${c.active ? '<span class="badge active">Aktif</span>' : '<span class="badge inactive">Nonaktif</span>'}</td>
        <td class="actions"><a class="btn small" href="customer-form.html?id=${c.id}">Edit</a></td>
      </tr>`
      )
      .join('');
  }
})();
