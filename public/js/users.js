(function () {
  const { escapeHtml, toast, confirmDialog } = IOMS.Utils;
  let session;

  document.addEventListener('DOMContentLoaded', async () => {
    session = IOMS.Auth.requireRole(['Admin']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    render();
  });

  function render() {
    const users = IOMS.DataStore.get('users');
    const tbody = document.getElementById('user-rows');

    tbody.innerHTML = users
      .map((u, i) => {
        const isSelf = u.id === session.userId;
        return `
        <tr class="row-in" style="animation-delay:${i * 0.03}s">
          <td>${escapeHtml(u.name)}${isSelf ? ' <span class="role-note" style="display:inline;">(Anda)</span>' : ''}</td>
          <td>${escapeHtml(u.email)}</td>
          <td>${escapeHtml(u.role)}</td>
          <td>${u.active ? '<span class="badge active">Aktif</span>' : '<span class="badge inactive">Nonaktif</span>'}</td>
          <td class="actions">
            <a class="btn small" href="user-form.html?id=${u.id}">Edit</a>
            <button type="button" class="btn small ${u.active ? 'danger' : ''}" data-toggle="${u.id}" ${isSelf ? 'disabled title="Tidak dapat menonaktifkan akun sendiri"' : ''}>
              ${u.active ? 'Nonaktifkan' : 'Aktifkan'}
            </button>
          </td>
        </tr>`;
      })
      .join('');

    tbody.querySelectorAll('[data-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => toggleActive(Number(btn.dataset.toggle)));
    });
  }

  async function toggleActive(id) {
    const users = IOMS.DataStore.get('users');
    const user = users.find((u) => u.id === id);
    const nextState = !user.active;

    const ok = await confirmDialog({
      title: nextState ? 'Aktifkan User' : 'Nonaktifkan User',
      message: `${nextState ? 'Aktifkan' : 'Nonaktifkan'} akun ${user.name}?`,
      confirmLabel: nextState ? 'Aktifkan' : 'Nonaktifkan',
      danger: !nextState,
    });
    if (!ok) return;

    user.active = nextState;
    IOMS.DataStore.save('users', users);
    toast(`Akun ${user.name} berhasil di${nextState ? 'aktifkan' : 'nonaktifkan'}.`, 'success');
    render();
  }
})();
