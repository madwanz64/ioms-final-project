(function () {
  const { toast } = IOMS.Utils;
  let editingId = null;

  document.addEventListener('DOMContentLoaded', async () => {
    const session = IOMS.Auth.requireRole(['Admin']);
    if (!session) return;

    await IOMS.DataStore.ensureSeeded();
    IOMS.Nav.render(session);
    loadForEdit();
    bindSubmit();
  });

  function loadForEdit() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) return;

    const user = IOMS.DataStore.findById('users', id);
    if (!user) {
      toast('User tidak ditemukan.', 'error');
      window.location.href = 'users.html';
      return;
    }
    editingId = user.id;
    document.getElementById('form-title').textContent = 'Edit User';
    document.getElementById('editing-id').value = user.id;
    document.getElementById('name').value = user.name;
    document.getElementById('email').value = user.email;
    document.getElementById('role').value = user.role;
    document.getElementById('status').value = String(user.active);

    // Saat edit, password opsional (kosongkan untuk mempertahankan password lama).
    document.getElementById('password-req').hidden = true;
    document.getElementById('password-hint').textContent = 'Kosongkan jika tidak ingin mengganti password.';
  }

  function setError(fieldId, message) {
    const field = document.getElementById(fieldId);
    field.classList.add('has-error');
    const msg = field.querySelector('.error-msg');
    msg.textContent = message;
    msg.hidden = false;
  }

  function clearErrors() {
    document.querySelectorAll('.field').forEach((f) => {
      f.classList.remove('has-error');
      const msg = f.querySelector('.error-msg');
      if (msg) msg.hidden = true;
    });
    document.getElementById('form-error').hidden = true;
  }

  function bindSubmit() {
    document.getElementById('user-form').addEventListener('submit', (e) => {
      e.preventDefault();
      clearErrors();

      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim().toLowerCase();
      const password = document.getElementById('password').value;
      const role = document.getElementById('role').value;
      const active = document.getElementById('status').value === 'true';

      let hasError = false;
      const users = IOMS.DataStore.get('users');

      if (!name) {
        setError('field-name', 'Nama wajib diisi.');
        hasError = true;
      }
      if (!email) {
        setError('field-email', 'Email wajib diisi.');
        hasError = true;
      } else {
        const duplicate = users.find((u) => u.email.toLowerCase() === email && u.id !== editingId);
        if (duplicate) {
          setError('field-email', 'Email sudah dipakai user lain.');
          hasError = true;
        }
      }
      if (!editingId && !password) {
        setError('field-password', 'Password wajib diisi untuk user baru.');
        hasError = true;
      } else if (password && password.length < 6) {
        setError('field-password', 'Password minimal 6 karakter.');
        hasError = true;
      }

      if (hasError) {
        document.getElementById('form-error').hidden = false;
        document.getElementById('form-error').textContent = 'Validasi gagal: periksa kembali field yang ditandai.';
        return;
      }

      if (editingId) {
        const idx = users.findIndex((u) => u.id === editingId);
        users[idx] = { ...users[idx], name, email, role, active, ...(password ? { password } : {}) };
      } else {
        users.push({ id: IOMS.DataStore.nextId('users'), name, email, password, role, active });
      }
      IOMS.DataStore.save('users', users);

      toast(editingId ? 'Perubahan user disimpan.' : 'User baru berhasil ditambahkan.', 'success');
      window.location.href = 'users.html';
    });
  }
})();
