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
    const suppliers = IOMS.DataStore.get('suppliers');
    const tbody = document.getElementById('supplier-rows');

    if (suppliers.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-muted">Belum ada supplier.</td></tr>`;
      return;
    }

    tbody.innerHTML = suppliers
      .map(
        (s, i) => `
      <tr class="row-in" style="animation-delay:${i * 0.03}s">
        <td>${escapeHtml(s.name)}</td>
        <td>${escapeHtml(s.contact)}</td>
        <td>${escapeHtml(s.address)}</td>
        <td>${s.active ? '<span class="badge active">Aktif</span>' : '<span class="badge inactive">Nonaktif</span>'}</td>
        <td class="actions"><a class="btn small" href="supplier-form.html?id=${s.id}">Edit</a></td>
      </tr>`
      )
      .join('');
  }
})();
