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
    const categories = IOMS.DataStore.get('categories');
    const products = IOMS.DataStore.get('products');
    const tbody = document.getElementById('category-rows');

    if (categories.length === 0) {
      tbody.innerHTML = `<tr><td colspan="4" class="text-muted">Belum ada kategori.</td></tr>`;
      return;
    }

    tbody.innerHTML = categories
      .map((c, i) => {
        const count = products.filter((p) => p.categoryId === c.id).length;
        return `
        <tr class="row-in" style="animation-delay:${i * 0.03}s">
          <td>${escapeHtml(c.name)}</td>
          <td>${escapeHtml(c.description || '-')}</td>
          <td class="num">${count}</td>
          <td class="actions"><a class="btn small" href="category-form.html?id=${c.id}">Edit</a></td>
        </tr>`;
      })
      .join('');
  }
})();
