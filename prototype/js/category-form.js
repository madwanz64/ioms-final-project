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

    const category = IOMS.DataStore.findById('categories', id);
    if (!category) {
      toast('Kategori tidak ditemukan.', 'error');
      window.location.href = 'categories.html';
      return;
    }
    editingId = category.id;
    document.getElementById('form-title').textContent = 'Edit Kategori';
    document.getElementById('editing-id').value = category.id;
    document.getElementById('name').value = category.name;
    document.getElementById('description').value = category.description || '';
  }

  function bindSubmit() {
    document.getElementById('category-form').addEventListener('submit', (e) => {
      e.preventDefault();
      document.getElementById('field-name').classList.remove('has-error');
      document.getElementById('form-error').hidden = true;

      const name = document.getElementById('name').value.trim();
      const description = document.getElementById('description').value.trim();

      if (!name) {
        document.getElementById('field-name').classList.add('has-error');
        document.querySelector('#field-name .error-msg').textContent = 'Nama kategori wajib diisi.';
        document.querySelector('#field-name .error-msg').hidden = false;
        document.getElementById('form-error').hidden = false;
        document.getElementById('form-error').textContent = 'Validasi gagal: periksa kembali field yang ditandai.';
        return;
      }

      const categories = IOMS.DataStore.get('categories');
      if (editingId) {
        const idx = categories.findIndex((c) => c.id === editingId);
        categories[idx] = { ...categories[idx], name, description };
      } else {
        categories.push({ id: IOMS.DataStore.nextId('categories'), name, description });
      }
      IOMS.DataStore.save('categories', categories);

      toast(editingId ? 'Perubahan kategori disimpan.' : 'Kategori baru berhasil ditambahkan.', 'success');
      window.location.href = 'categories.html';
    });
  }
})();
